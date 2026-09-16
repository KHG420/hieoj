#!/usr/bin/env python3
# ============================================================
# frp-notify.py —— 公网可达性故障/恢复邮件通知（运行在公网主机）
#
# 与 frp-healthcheck.sh（应用服务器上的有界自动恢复）完全独立：
#   本脚本运行在公网主机 42.194.237.240，由 frp-notify.timer 每 60s 拉起，
#   直接调用本机已安装的 /usr/local/sbin/hnieoj-frp-public-probe 取得外部视角，
#   再通过 PushPlus 默认发信通道推送邮件通知（channel=mail）。即使应用服务器/应用完全不可用，只要公网主机
#   与探针可用，告警链路就仍然工作；本脚本不执行任何重启动作。
#
# 通知语义：
#   * 连续 3 次失败 → 一条不可用告警（首次失败时间 CST、两个监控 URL、当前状态）；
#   * 连续 2 次健康 → 一条恢复通知（含持续时间，按首次确认恢复时刻计算）；
#   * 故障持续时，距上次“已接受”通知 1800s 再发一条提醒；
#   * 提交失败绝不记为已接受，300s 后重试（两次重试之间只记日志）；
#   * PushPlus 返回 code==900（用户当日限额）时按文档暂停到次日 CST 0 点再试，
#     期间探针检查照常进行，避免继续请求延长封禁；
#   * 未知探测结果（超时/非零退出/输出非预期）只按未知处理，不算恢复，并清零连续计数；
#   * 状态落在 /var/lib/hnieoj-frp-notify/state.json（root 0700，flock + 原子写）。
#
# 安全：
#   * token 只从 /etc/hnieoj-frp-notify.json（root 0600）读取，绝不进入
#     argv/日志/输出/Git；PushPlus 返回的 messageID 仅用于追踪。
#   * 探针子进程与 HTTPS 请求都有硬超时；TLS 默认校验；ProxyHandler({}) 忽略代理环境变量。
#   * HTTP 200 且 JSON code==200 只代表“已受理入队”，不代表用户已收到。
# ============================================================
import argparse
import datetime
import fcntl
import json
import os
import subprocess
import sys
import time
import urllib.error
import urllib.request

# 固定部署路径，不从环境变量推断。
PROBE_PATH = "/usr/local/sbin/hnieoj-frp-public-probe"
PROBE_TIMEOUT_SECONDS = 25
CONFIG_PATH = "/etc/hnieoj-frp-notify.json"
STATE_DIR = "/var/lib/hnieoj-frp-notify"
STATE_FILENAME = "state.json"
LOCK_FILENAME = "lock"

PUSHPLUS_URL = "https://www.pushplus.plus/send"
HTTP_TIMEOUT_SECONDS = 10

# 两个域名属于同一个站点、同一次事故，合并为一条消息。
MONITORED_URLS = ("https://hnieacm.com/", "https://www.hnieacm.com/")

FAIL_THRESHOLD = 3
HEALTHY_THRESHOLD = 2
REMINDER_SECONDS = 1800
RETRY_SECONDS = 300

# PushPlus 用户当日限额：https://www.pushplus.plus/doc/help/limit.html
# 继续请求可能延长封禁，因此暂停到次日 CST 0 点再试。
LIMIT_CODE = 900
DAILY_LIMIT_REASON = "api limit code 900"

CST = datetime.timezone(datetime.timedelta(hours=8))

DEFAULT_STATE = {
    "consecutive_failures": 0,
    "consecutive_healthy": 0,
    "first_failure_epoch": 0,
    "incident_active": False,
    "incident_start_epoch": 0,
    "pending": "",  # "" | "outage" | "reminder" | "recovery"
    "recovery_confirmed_epoch": 0,  # 首次确认恢复的时刻（重试期间不变）
    "next_attempt_epoch": 0,
    "last_attempt_epoch": 0,
    "last_accepted_epoch": 0,
    "last_message_id": "",
}


class ConfigError(Exception):
    """配置缺失或格式错误；消息中不含 token 本身。"""


def log(message):
    print("%s frp-notify: %s" % (_now_str(), message), flush=True)


def warn(message):
    print("%s frp-notify: WARN: %s" % (_now_str(), message), file=sys.stderr, flush=True)


def _now_str(epoch=None):
    epoch = time.time() if epoch is None else epoch
    return datetime.datetime.fromtimestamp(epoch, CST).strftime("%Y-%m-%d %H:%M:%S")


def format_cst(epoch):
    return datetime.datetime.fromtimestamp(int(epoch), CST).strftime("%Y-%m-%d %H:%M:%S CST")


def format_duration(seconds):
    seconds = max(0, int(seconds))
    if seconds < 60:
        return "%d 秒" % seconds
    minutes = seconds // 60
    if minutes < 60:
        return "%d 分钟" % minutes
    hours, minutes = divmod(minutes, 60)
    if hours < 24:
        return "%d 小时 %d 分钟" % (hours, minutes)
    days, hours = divmod(hours, 24)
    return "%d 天 %d 小时" % (days, hours)


def next_cst_day_start(epoch):
    """返回 epoch 之后下一个 CST 0 点；用于 PushPlus 当日限额后的暂停。"""
    moment = datetime.datetime.fromtimestamp(int(epoch), CST)
    tomorrow = (moment + datetime.timedelta(days=1)).replace(
        hour=0, minute=0, second=0, microsecond=0)
    return int(tomorrow.timestamp())


# ---------------------------------------------------------------- 配置

def load_config(path=None):
    path = path or CONFIG_PATH
    try:
        with open(path, "r", encoding="utf-8") as handle:
            data = json.load(handle)
    except FileNotFoundError:
        raise ConfigError("config file not found")
    except (OSError, ValueError):
        raise ConfigError("config file unreadable or not valid JSON")
    if not isinstance(data, dict):
        raise ConfigError("config must be a JSON object")
    token = data.get("pushplus_token")
    if not isinstance(token, str) or not token.strip():
        raise ConfigError("pushplus_token missing or empty")
    return token.strip()


# ---------------------------------------------------------------- 外部边界

def run_probe():
    """返回 'OK' | 'FAILED' | 'UNKNOWN'；UNKNOWN 绝不当作恢复。"""
    try:
        completed = subprocess.run(
            [PROBE_PATH],
            stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL,
            timeout=PROBE_TIMEOUT_SECONDS,
            check=False,
        )
    except (OSError, subprocess.SubprocessError):
        return "UNKNOWN"
    if completed.returncode != 0:
        return "UNKNOWN"
    output = completed.stdout.decode("utf-8", "replace").strip()
    if output == "FRP_HEALTH_OK":
        return "OK"
    if output == "FRP_HEALTH_FAILED":
        return "FAILED"
    return "UNKNOWN"


def pushplus_post(payload_bytes):
    """执行一次有界 HTTPS POST；返回 (status, body)。"""
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    request = urllib.request.Request(
        PUSHPLUS_URL,
        data=payload_bytes,
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    with opener.open(request, timeout=HTTP_TIMEOUT_SECONDS) as response:
        return response.getcode(), response.read()


def send_notification(token, title, content):
    """返回 (accepted, message_id, reason)。reason 不含 token 与原始响应体。"""
    payload = json.dumps({
        "token": token,
        "title": title,
        "content": content,
        "template": "txt",
        # 走 PushPlus 默认邮件通道；收件邮箱在用户个人资料中绑定，省略 option。
        "channel": "mail",
    }).encode("utf-8")
    try:
        status, body = pushplus_post(payload)
    except urllib.error.HTTPError as exc:
        return False, "", "http error %s" % exc.code
    except Exception as exc:  # 网络/DNS/TLS/超时等，只暴露异常类型
        return False, "", "network error %s" % type(exc).__name__
    if status != 200:
        return False, "", "http status %s" % status
    try:
        data = json.loads(body.decode("utf-8", "replace"))
    except ValueError:
        return False, "", "invalid json response"
    code = data.get("code") if isinstance(data, dict) else None
    if code != 200:
        # 900 需要暂停到次日，单独标记以便调用方区分处理。
        if code == LIMIT_CODE:
            return False, "", DAILY_LIMIT_REASON
        return False, "", "api code %r" % (code,)
    # code==200 仅代表受理入队；data 是 messageID，只用于追踪。
    return True, str(data.get("data") or ""), "accepted"


# ---------------------------------------------------------------- 状态文件

def _state_path(state_dir):
    return os.path.join(state_dir, STATE_FILENAME)


def _lock_path(state_dir):
    return os.path.join(state_dir, LOCK_FILENAME)


def read_state(state_dir):
    state = dict(DEFAULT_STATE)
    try:
        with open(_state_path(state_dir), "r", encoding="utf-8") as handle:
            loaded = json.load(handle)
    except (OSError, ValueError):
        return state
    if isinstance(loaded, dict):
        for key in state:
            if key in loaded:
                state[key] = loaded[key]
    return state


def write_state(state_dir, state):
    """临时文件 + fsync + rename 原子落盘；持锁期间调用。"""
    path = _state_path(state_dir)
    tmp = "%s.tmp.%d" % (path, os.getpid())
    try:
        with open(tmp, "w", encoding="utf-8") as handle:
            json.dump(state, handle, ensure_ascii=False, sort_keys=True)
            handle.flush()
            os.fsync(handle.fileno())
        os.chmod(tmp, 0o600)
        os.replace(tmp, path)
    except OSError:
        try:
            os.unlink(tmp)
        except OSError:
            pass
        raise


def _persist(state_dir, state):
    try:
        write_state(state_dir, state)
        return True
    except OSError:
        warn("cannot persist state to %s" % _state_path(state_dir))
        return False


def acquire_lock(state_dir):
    """非阻塞独占锁；已被占用返回 None。"""
    os.makedirs(state_dir, mode=0o700, exist_ok=True)
    fd = os.open(_lock_path(state_dir), os.O_RDWR | os.O_CREAT, 0o600)
    try:
        fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        os.close(fd)
        return None
    return fd


def release_lock(fd):
    try:
        fcntl.flock(fd, fcntl.LOCK_UN)
    finally:
        os.close(fd)


# ---------------------------------------------------------------- 状态机

def apply_probe(state, status, now):
    """按一次探测结果更新连续计数与待发通知；返回原始 status。"""
    if status == "UNKNOWN":
        # 未知既不是恢复也不是确认失败：清零两条连续计数，但不结束进行中的事故。
        state["consecutive_failures"] = 0
        state["consecutive_healthy"] = 0
        if state["pending"] == "recovery":
            # 恢复尚未被确认稳定就出现未知：作废待发恢复，事故本身保留。
            state["pending"] = ""
            state["recovery_confirmed_epoch"] = 0
        return status

    if status == "FAILED":
        state["consecutive_healthy"] = 0
        if state["consecutive_failures"] == 0:
            state["first_failure_epoch"] = now
        state["consecutive_failures"] += 1
        if state["pending"] == "recovery":
            # 恢复尚未稳定又失败：取消过期的恢复，继续同一次事故，不重发首次告警。
            state["pending"] = ""
            state["recovery_confirmed_epoch"] = 0
        if not state["incident_active"]:
            if state["pending"] != "outage" and state["consecutive_failures"] >= FAIL_THRESHOLD:
                state["pending"] = "outage"
                state["incident_start_epoch"] = state["first_failure_epoch"] or now
        elif state["pending"] == "" and now - state["last_accepted_epoch"] >= REMINDER_SECONDS:
            state["pending"] = "reminder"
        return status

    # status == "OK"
    state["consecutive_failures"] = 0
    state["consecutive_healthy"] += 1
    if state["pending"] == "outage":
        # 首次告警从未被接受就已恢复：不再单独发“已恢复”，避免只收到恢复的误导。
        state["pending"] = ""
        state["incident_active"] = False
        state["incident_start_epoch"] = 0
        return status
    if state["pending"] == "reminder":
        state["pending"] = ""
    if state["incident_active"] and state["consecutive_healthy"] >= HEALTHY_THRESHOLD:
        if state["pending"] != "recovery":
            # 只在首次确认恢复时记时刻；投递重试不得把恢复时间越推越晚。
            state["recovery_confirmed_epoch"] = now
            state["pending"] = "recovery"
    return status


def build_message(state, kind, now):
    urls = "、".join(MONITORED_URLS)
    start = state["incident_start_epoch"] or now
    if kind == "outage":
        title = "HnieOJ 公网服务不可用"
        content = "\n".join([
            "监控地址：%s" % urls,
            "首次失败：%s" % format_cst(start),
            "连续失败：%d 次（阈值 %d）" % (state["consecutive_failures"], FAIL_THRESHOLD),
            "状态：公网探针连续确认不可用",
        ])
    elif kind == "reminder":
        title = "HnieOJ 公网服务仍不可用（提醒）"
        content = "\n".join([
            "监控地址：%s" % urls,
            "首次失败：%s" % format_cst(start),
            "已持续：%s" % format_duration(now - start),
            "连续失败：%d 次" % state["consecutive_failures"],
        ])
    else:  # recovery
        # 用首次确认恢复的时刻，而非本次重试发送时刻，避免重试拉长故障时长。
        recovered = state["recovery_confirmed_epoch"] or now
        title = "HnieOJ 公网服务已恢复"
        content = "\n".join([
            "监控地址：%s" % urls,
            "故障开始：%s" % format_cst(start),
            "恢复时间：%s" % format_cst(recovered),
            "持续时长：%s" % format_duration(recovered - start),
        ])
    return title, content


def _maybe_notify(state_dir, state, token, now, status):
    pending = state["pending"]
    if not pending:
        if status == "FAILED":
            log("probe FAILED; consecutive failures %d/%d"
                % (state["consecutive_failures"], FAIL_THRESHOLD))
        else:
            log("probe healthy; consecutive healthy %d/%d"
                % (state["consecutive_healthy"], HEALTHY_THRESHOLD))
        return 0

    if now < state["next_attempt_epoch"]:
        log("notification %s pending; next delivery attempt in %ds (retry cooldown)"
            % (pending, state["next_attempt_epoch"] - now))
        return 0

    # 发送前先持久化冷却/尝试时间：即使随后被杀，下一轮也不会立即重发造成风暴。
    state["next_attempt_epoch"] = now + RETRY_SECONDS
    state["last_attempt_epoch"] = now
    if not _persist(state_dir, state):
        warn("cannot persist retry cooldown; not sending (fail closed)")
        return 1

    title, content = build_message(state, pending, now)
    accepted, message_id, reason = send_notification(token, title, content)
    if accepted:
        state["last_accepted_epoch"] = now
        state["last_message_id"] = message_id
        # 成功的受理不该保留重试冷却，否则随后的恢复通知会被无谓推迟。
        state["next_attempt_epoch"] = 0
        if pending == "recovery":
            state["incident_active"] = False
            state["incident_start_epoch"] = 0
            state["recovery_confirmed_epoch"] = 0
        else:
            state["incident_active"] = True
        state["pending"] = ""
        log("%s notification accepted (messageID=%s); accepted for delivery, not delivery-confirmed"
            % (pending, message_id or "-"))
    elif reason == DAILY_LIMIT_REASON:
        # 当日限额：暂停到次日 CST 0 点，继续探测但不再请求，避免延长封禁。
        state["next_attempt_epoch"] = next_cst_day_start(now)
        _persist(state_dir, state)
        warn("%s notification hit PushPlus daily limit (code %d); "
             "pausing delivery until %s, checks continue"
             % (pending, LIMIT_CODE, format_cst(state["next_attempt_epoch"])))
    else:
        warn("%s notification was not accepted (%s); will retry after %ds"
             % (pending, reason, RETRY_SECONDS))
    return 0


def run_tick(config_path=None, state_dir=None, now=None):
    config_path = config_path or CONFIG_PATH
    state_dir = state_dir or STATE_DIR
    now = int(time.time()) if now is None else int(now)

    # 配置优先：缺失/畸形时安全失败，不探测、不触碰状态、不输出 token。
    try:
        token = load_config(config_path)
    except ConfigError as exc:
        warn("configuration error: %s; not sending anything" % exc)
        return 2

    lock_fd = acquire_lock(state_dir)
    if lock_fd is None:
        log("another run is in progress; skipping this tick")
        return 0
    try:
        state = read_state(state_dir)
        status = run_probe()
        apply_probe(state, status, now)
        if status == "UNKNOWN":
            # 未知不触发任何发送；已待发的通知保留在状态里，等下一个已知结论再重试。
            warn("probe result unknown (timeout, non-zero exit, or unexpected output); "
                 "streaks reset, no recovery assumed, no notification")
            rc = 0
        else:
            rc = _maybe_notify(state_dir, state, token, now, status)
        _persist(state_dir, state)
        return rc
    finally:
        release_lock(lock_fd)


# ---------------------------------------------------------------- CLI

def cmd_test_notification(config_path=None, now=None):
    """显式标注的测试通知；不读取/写入任何事故状态。"""
    config_path = config_path or CONFIG_PATH
    now = int(time.time()) if now is None else int(now)
    try:
        token = load_config(config_path)
    except ConfigError as exc:
        warn("configuration error: %s; cannot send test notification" % exc)
        return 2
    title = "HnieOJ 公网监控测试通知"
    content = "\n".join([
        "这是一条由 --test-notification 显式触发的测试通知。",
        "它不代表真实故障或恢复，也不会修改任何事故状态。",
        "监控地址：%s" % "、".join(MONITORED_URLS),
        "时间：%s" % format_cst(now),
    ])
    accepted, message_id, reason = send_notification(token, title, content)
    if accepted:
        log("test notification accepted (messageID=%s); accepted for delivery, not delivery-confirmed"
            % (message_id or "-"))
        return 0
    warn("test notification was not accepted (%s)" % reason)
    return 1


def main(argv=None):
    parser = argparse.ArgumentParser(description="HnieOJ public outage email notifier")
    parser.add_argument(
        "--test-notification",
        action="store_true",
        help="send an explicitly labelled test notification, then exit (incident state untouched)",
    )
    args = parser.parse_args(argv)
    if args.test_notification:
        return cmd_test_notification()
    return run_tick()


if __name__ == "__main__":
    sys.exit(main())
