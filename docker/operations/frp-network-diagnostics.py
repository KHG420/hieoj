#!/usr/bin/env python3
# ============================================================
# frp-network-diagnostics.py —— FRP 网络证据记录（应用服务器，只读诊断）
#
# 定位：
#   与 frp-healthcheck.sh（有界自动恢复）和 frp-notify（公网邮件通知）完全独立。
#   本脚本只做**只读观测**：不重启、不登录、不改配置、不读任何配置文件，
#   由 frp-network-diagnostics.timer 每 60s 拉起一次，把一份 JSONL 快照追加到
#   /var/log/hnieoj-frp-network/events.jsonl。
#
# 背景（2026-09-21 10:22–10:39 CST 实测）：
#   应用服务器 172.31.0.96 能到网关 172.31.0.1 与本地 Web，但到腾讯
#   42.194.237.240 的 TCP 22/7000 与公网 DNS 均失败；SYN 已离开网卡、
#   没有回包；10:39:45 同一个 frpc PID 自行恢复重连，networkd 无事件。
#   上游触发原因未知，因此需要把「当时本机看到了什么」逐分钟留证，
#   供事后与云侧/校园网上游日志对齐，而不是在本机猜测结论。
#
# 判定语义：
#   * checks.<name>.ok 表示该观测项是否正常；error 给出可区分的原始标签
#     （refused / timeout / dns_error / missing_command / http_5xx /
#      marker_missing / no_default_route ...）。
#   * failed_checks 列出所有异常观测；probes_complete=false 表示至少有一项
#     观测没能正常完成（例如命令缺失），此时绝不能被读成「一切正常」。
#   * primary_failure 只看直接影响公网可达性的观测项（本机 Web、腾讯
#     22/7000、223.5.5.5:53、域名解析）；网关 ping、邻居、接口、frpc 服务
#     只是旁证：网关 ping 单独失败不构成任何结论。
#   * issues 只是「原始观测 + 建议标签」，不做校园网/门户过期等推断。
#
# 证据有界性：
#   * 每项检查 3–5s 硬超时，固定小并发（8 线程），正常一轮 <15s；
#     service TimeoutStartSec=45s。
#   * 只在「primary 失败」或「上一次 primary 失败且本轮恢复」时附带最近的
#     frpc journal（最近 30 行 / 2 分钟内 / 最多 8KiB），并做敏感键脱敏。
#   * 绝不记录 HTTP 正文、配置、环境变量、凭据；HTTP 只记状态码/标记/字节数。
#   * 日志目录 0700，events.jsonl / state.json / lock 0600；events.jsonl
#     按 4MiB 轮转，保留 current + 7 个备份（<=32MiB + 一条有界记录）。
#   * 写证据失败 → stderr 可见、退出非零；探测失败但证据写入成功 → 退出 0。
#
# flock：非阻塞独占锁，手动执行与 timer 重叠时本轮直接跳过，不写半份证据。
# ============================================================
import concurrent.futures
import datetime
import errno
import fcntl
import json
import os
import re
import shutil
import socket
import subprocess
import sys
import tempfile
import time

# ---- 固定部署路径与常量：不读环境变量、不读配置文件、无命令行开关 ----
LOG_DIR = "/var/log/hnieoj-frp-network"
EVENTS_FILENAME = "events.jsonl"
STATE_FILENAME = "state.json"
LOCK_FILENAME = "lock"
LOG_DIR_MODE = 0o700
FILE_MODE = 0o600
MAX_BYTES = 4 * 1024 * 1024
BACKUP_COUNT = 7

APP_IFACE = "ens160"
LOCAL_URL = "http://127.0.0.1/"
OJ_MARKER = "算法设计在线评测系统"
FRP_HOST = "42.194.237.240"
FRP_PORT = 7000
SSH_PORT = 22
DNS_HOST = "223.5.5.5"
DNS_PORT = 53
RESOLVE_NAME = "www.baidu.com"
SERVICE_NAME = "frpc"

CURL_CONNECT_TIMEOUT = 3
# curl 自身 max-time=4s，外层子进程超时 5s 作为兜底 → 本项检查硬上界 5s。
CURL_MAX_TIME = 4
IP_TIMEOUT = 3
CONNECT_TIMEOUT_SECONDS = 3
GETENT_TIMEOUT = 5
PING_TIMEOUT = 4
SYSTEMCTL_TIMEOUT = 3
JOURNAL_TIMEOUT = 3
KILL_GRACE_SECONDS = 2
MAX_WORKERS = 8

OUTPUT_CAP = 4096
HTTP_BODY_CAP = 262144
JOURNAL_LINES = 30
JOURNAL_SINCE = "2 min ago"
JOURNAL_MAX_BYTES = 8192
JOURNAL_CAPTURE_CAP = 65536
STATE_ISSUE_LIMIT = 12

# 直接影响公网可达性的观测项；其余（网关/邻居/接口/frpc 服务）只作旁证。
PRIMARY_CHECKS = (
    "local_http",
    "tcp_frp_7000",
    "tcp_ssh_22",
    "tcp_dns_53",
    "resolver",
)

SCHEMA = "hnieoj-frp-network-diagnostics/1"

# journal 行里的敏感键值一律脱敏，避免任何凭据进入证据文件。
_REDACT_RE = re.compile(
    r"(?i)\b(token|secret|password|passwd|api[_-]?key)\b(\s*[=:]\s*)(\S+)")


# ---------------------------------------------------------------- 输出与时间

def _now_iso(epoch=None):
    moment = time.time() if epoch is None else epoch
    return datetime.datetime.fromtimestamp(moment).astimezone().isoformat(
        timespec="seconds")


def log(message):
    print("%s frp-network-diagnostics: %s" % (_now_iso(), message), flush=True)


def warn(message):
    print("%s frp-network-diagnostics: WARN: %s" % (_now_iso(), message),
          file=sys.stderr, flush=True)


# ---------------------------------------------------------------- 命令边界

def _capture(data, cap):
    text = data.decode("utf-8", "replace")
    if len(text) > cap:
        return text[:cap], True
    return text, False


def _empty(rc=None, error=None):
    return {"rc": rc, "stdout": "", "stderr": "", "error": error,
            "stdout_truncated": False, "stderr_truncated": False}


def run_command(args, timeout, cap=OUTPUT_CAP):
    """唯一的子进程边界：执行固定只读命令，返回有界结果 dict。

    错误标签：missing_command（命令不存在）、timeout（超时后 SIGKILL）、
    os_error_<errno>（其他 exec 失败）。
    """
    try:
        proc = subprocess.Popen(
            list(args),
            stdin=subprocess.DEVNULL,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            close_fds=True,
        )
    except FileNotFoundError:
        return _empty(error="missing_command")
    except OSError as exc:
        return _empty(error="os_error_%s" % exc.errno)
    try:
        out, err = proc.communicate(timeout=timeout)
    except subprocess.TimeoutExpired:
        proc.kill()
        try:
            proc.communicate(timeout=KILL_GRACE_SECONDS)
        except subprocess.TimeoutExpired:
            pass
        return _empty(error="timeout")
    out_text, out_truncated = _capture(out, cap)
    err_text, err_truncated = _capture(err, cap)
    return {"rc": proc.returncode, "stdout": out_text, "stderr": err_text,
            "error": None, "stdout_truncated": out_truncated,
            "stderr_truncated": err_truncated}


def _os_error_label(exc):
    if isinstance(exc, TimeoutError) or exc.errno == errno.ETIMEDOUT:
        return "timeout"
    labels = {
        errno.ECONNREFUSED: "refused",
        errno.ECONNRESET: "reset",
        errno.EHOSTUNREACH: "host_unreachable",
        errno.ENETUNREACH: "network_unreachable",
        errno.EADDRNOTAVAIL: "address_unavailable",
    }
    return labels.get(exc.errno, "os_error_%s" % exc.errno)


def tcp_probe(host, port, timeout=CONNECT_TIMEOUT_SECONDS):
    """对字面 IPv4 地址做 TCP 连接；只构造 AF_INET socket，不做 DNS 解析。"""
    try:
        socket.inet_aton(host)
    except OSError:
        return {"ok": False, "error": "non_literal_address"}
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.settimeout(timeout)
    try:
        sock.connect((host, port))
        return {"ok": True, "error": None}
    except socket.timeout:
        return {"ok": False, "error": "timeout"}
    except ConnectionRefusedError:
        return {"ok": False, "error": "refused"}
    except socket.gaierror:
        return {"ok": False, "error": "dns_error"}
    except OSError as exc:
        return {"ok": False, "error": _os_error_label(exc)}
    finally:
        sock.close()


def _read_prefix(path, cap):
    try:
        size = os.path.getsize(path)
    except OSError:
        return "", 0, False
    try:
        with open(path, "rb") as handle:
            data = handle.read(cap)
    except OSError:
        return "", size, False
    return data.decode("utf-8", "replace"), size, size > len(data)


# ---------------------------------------------------------------- 各项检查

def check_local_http(work_dir):
    """curl 取本机首页：只记状态码/标记/字节数，绝不记录正文。"""
    check = {"ok": False, "error": None, "observed": False, "url": LOCAL_URL,
             "http_code": None, "marker": False, "body_bytes": 0,
             "body_truncated": False}
    if not work_dir:
        check["error"] = "no_temp_dir"
        return check
    body_path = os.path.join(work_dir, "local-body")
    result = run_command([
        "curl", "-4", "--noproxy", "*", "--silent", "--show-error",
        "--connect-timeout", str(CURL_CONNECT_TIMEOUT),
        "--max-time", str(CURL_MAX_TIME),
        "--max-filesize", str(HTTP_BODY_CAP),
        "--output", body_path,
        "--write-out", "%{http_code}",
        LOCAL_URL,
    ], timeout=CURL_MAX_TIME + 1)
    if result["error"]:
        check["error"] = result["error"]
        return check
    check["observed"] = True
    code = result["stdout"].strip()
    if code == "000":  # curl 未能拿到响应时的占位码，不当作 HTTP 状态
        code = ""
    check["http_code"] = code or None
    body, size, truncated = _read_prefix(body_path, HTTP_BODY_CAP)
    check["body_bytes"] = size
    check["body_truncated"] = truncated
    if result["rc"] != 0:
        check["error"] = "curl_exit_%s" % result["rc"]
        return check
    if code != "200":
        check["error"] = "http_%s" % (code or "unknown")
        return check
    if OJ_MARKER not in body:
        check["error"] = "marker_missing"
        return check
    check["ok"] = True
    check["marker"] = True
    return check


def check_tcp(host, port):
    probe = tcp_probe(host, port, CONNECT_TIMEOUT_SECONDS)
    return {"ok": probe["ok"], "error": probe["error"],
            "observed": probe["error"] != "non_literal_address",
            "target": "%s:%d" % (host, port)}


def check_default_route(iface):
    check = {"ok": False, "error": None, "observed": False, "gateway": None,
             "dev": None, "routes": []}
    result = run_command(["ip", "-4", "route", "show", "default"],
                         timeout=IP_TIMEOUT)
    if result["error"]:
        check["error"] = result["error"]
        return check
    if result["rc"] != 0:
        check["error"] = "ip_exit_%s" % result["rc"]
        return check
    check["observed"] = True
    candidates = []
    for line in result["stdout"].splitlines():
        match = re.match(r"^default\s+via\s+(\S+)\s+dev\s+(\S+)", line.strip())
        if match:
            candidates.append((match.group(1), match.group(2)))
    check["routes"] = [{"gateway": gw, "dev": dev}
                       for gw, dev in candidates][:4]
    # 证据只认 ens160 上的默认路由；存在其他网卡默认路由时如实记录 dev。
    chosen = next((item for item in candidates if item[1] == iface),
                  candidates[0] if candidates else None)
    if chosen is None:
        check["error"] = "no_default_route"
        return check
    check["gateway"], check["dev"] = chosen
    if check["dev"] != iface:
        check["error"] = "other_interface"
    else:
        check["ok"] = True
    return check


def _parse_counters(text, check):
    # iproute2 5.x 的 `ip -s -o link` 会把统计块用 '\' 连成一行（实测
    # 5.15.0：... default \    link/ether ... \    RX: bytes ... \    152 2 ...），
    # 这里先统一还原成多行，再找 RX:/TX: 表头后的第一组数字。
    lines = [line.strip() for line in text.replace("\\", "\n").splitlines()]
    for index, line in enumerate(lines):
        for prefix, keys in (
                ("RX:", ("rx_bytes", "rx_packets", "rx_errors", "rx_dropped")),
                ("TX:", ("tx_bytes", "tx_packets", "tx_errors", "tx_dropped"))):
            if not line.startswith(prefix):
                continue
            for candidate in lines[index:index + 3]:
                values = re.findall(r"\d+", candidate)
                if not values:
                    continue
                for key, value in zip(keys, values):
                    check[key] = int(value)
                break


def check_interface(iface):
    check = {"ok": False, "error": None, "observed": False, "iface": iface,
             "addresses": [], "operstate": None, "carrier": None, "mtu": None,
             "rx_bytes": None, "tx_bytes": None, "rx_packets": None,
             "tx_packets": None, "rx_errors": None, "tx_errors": None,
             "rx_dropped": None, "tx_dropped": None}
    addr = run_command(["ip", "-4", "-o", "addr", "show", "dev", iface],
                       timeout=IP_TIMEOUT)
    link = run_command(["ip", "-s", "-o", "link", "show", "dev", iface],
                       timeout=IP_TIMEOUT)
    for result in (addr, link):
        if result["error"]:
            check["error"] = result["error"]
            return check
        if result["rc"] != 0:
            check["error"] = "ip_exit_%s" % result["rc"]
            return check
    check["observed"] = True
    for line in addr["stdout"].splitlines():
        for match in re.finditer(r"inet\s+(\d+\.\d+\.\d+\.\d+/\d+)", line):
            if match.group(1) not in check["addresses"]:
                check["addresses"].append(match.group(1))
    text = link["stdout"]
    state = re.search(r"\bstate\s+(\S+)", text)
    if state:
        check["operstate"] = state.group(1)
    flags = re.search(r"<([^>]*)>", text)
    if flags:
        check["carrier"] = "LOWER_UP" in flags.group(1)
    mtu = re.search(r"\bmtu\s+(\d+)", text)
    if mtu:
        check["mtu"] = int(mtu.group(1))
    _parse_counters(text, check)
    if check["operstate"] != "UP":
        check["error"] = "down"
    elif not check["addresses"]:
        check["error"] = "no_ipv4_address"
    else:
        check["ok"] = True
    return check


def check_neighbor(iface, gateway):
    """ARP/邻居表：显式 FAILED/INCOMPLETE 才算异常；无条目不算异常。"""
    check = {"ok": False, "error": None, "observed": False, "iface": iface,
             "gateway": gateway, "gateway_state": None,
             "gateway_has_lladdr": False, "entry_count": 0}
    result = run_command(["ip", "-4", "neigh", "show", "dev", iface],
                         timeout=IP_TIMEOUT)
    if result["error"]:
        check["error"] = result["error"]
        return check
    if result["rc"] != 0:
        check["error"] = "ip_exit_%s" % result["rc"]
        return check
    check["observed"] = True
    entries = []
    for line in result["stdout"].splitlines():
        fields = line.split()
        if len(fields) < 2:
            continue
        entries.append({"ip": fields[0],
                        "state": fields[-1] if fields[-1].isupper() else None,
                        "has_lladdr": "lladdr" in fields})
    check["entry_count"] = len(entries)
    for entry in entries:
        if entry["ip"] == gateway:
            check["gateway_state"] = entry["state"]
            check["gateway_has_lladdr"] = entry["has_lladdr"]
            break
    if check["gateway_state"] in ("FAILED", "INCOMPLETE"):
        check["error"] = str(check["gateway_state"]).lower()
    else:
        check["ok"] = True
    return check


def check_gateway_ping(gateway):
    check = {"ok": False, "error": None, "observed": False, "gateway": gateway,
             "rc": None, "packet_loss_percent": None}
    if not gateway:
        check["error"] = "no_gateway"
        return check
    result = run_command(
        ["ping", "-4", "-n", "-q", "-c", "1", "-W", "2", "-w", "4", gateway],
        timeout=PING_TIMEOUT)
    check["rc"] = result["rc"]
    if result["error"]:
        check["error"] = result["error"]
        return check
    check["observed"] = True
    loss = re.search(r"(\d+)%\s*packet loss", result["stdout"])
    if loss:
        check["packet_loss_percent"] = int(loss.group(1))
    if result["rc"] == 0:
        check["ok"] = True
    elif result["rc"] == 1:
        check["error"] = "no_reply"
    else:
        check["error"] = "ping_exit_%s" % result["rc"]
    return check


def check_resolver(name=RESOLVE_NAME):
    check = {"ok": False, "error": None, "observed": False, "name": name,
             "rc": None, "addresses": []}
    result = run_command(["getent", "ahostsv4", name], timeout=GETENT_TIMEOUT)
    check["rc"] = result["rc"]
    if result["error"]:
        check["error"] = result["error"]
        return check
    check["observed"] = True
    if result["rc"] != 0:
        check["error"] = "dns_error"
        return check
    for line in result["stdout"].splitlines():
        fields = line.split()
        if not fields or not re.match(r"^\d+\.\d+\.\d+\.\d+$", fields[0]):
            continue
        if fields[0] not in check["addresses"]:
            check["addresses"].append(fields[0])
    check["addresses"] = check["addresses"][:4]
    if not check["addresses"]:
        check["error"] = "no_address"
        return check
    check["ok"] = True
    return check


def check_frpc_service(unit=SERVICE_NAME):
    check = {"ok": False, "error": None, "observed": False, "unit": unit,
             "active": None, "sub": None, "load_state": None, "main_pid": None,
             "since": None}
    result = run_command([
        "systemctl", "show", unit, "--no-pager",
        "-p", "LoadState", "-p", "ActiveState", "-p", "SubState",
        "-p", "MainPID", "-p", "ActiveEnterTimestamp",
        "-p", "ExecMainStartTimestamp",
    ], timeout=SYSTEMCTL_TIMEOUT)
    if result["error"]:
        check["error"] = result["error"]
        return check
    if result["rc"] != 0:
        check["error"] = "systemctl_exit_%s" % result["rc"]
        return check
    check["observed"] = True
    props = {}
    for line in result["stdout"].splitlines():
        key, sep, value = line.partition("=")
        if sep:
            props[key.strip()] = value.strip()
    check["load_state"] = props.get("LoadState") or None
    check["active"] = props.get("ActiveState") or None
    check["sub"] = props.get("SubState") or None
    main_pid = props.get("MainPID") or ""
    if main_pid.isdigit():
        check["main_pid"] = int(main_pid)
    check["since"] = (props.get("ActiveEnterTimestamp")
                      or props.get("ExecMainStartTimestamp") or None)
    if check["load_state"] == "not-found":
        check["error"] = "unit_not_found"
    elif check["active"] != "active":
        check["error"] = "not_active"
    else:
        check["ok"] = True
    return check


# ---------------------------------------------------------------- journal

def _redact(text):
    return _REDACT_RE.sub(r"\1\2<redacted>", text)


def collect_journal(reason):
    """最近 30 行 / 2 分钟内 / 最多 8KiB 的 frpc journal，只保留尾部。"""
    entry = {"reason": reason, "unit": SERVICE_NAME, "since": JOURNAL_SINCE,
             "lines": [], "truncated": False, "error": None}
    result = run_command([
        "journalctl", "-u", SERVICE_NAME, "--no-pager",
        "-n", str(JOURNAL_LINES), "--since", JOURNAL_SINCE,
        "-o", "short-iso",
    ], timeout=JOURNAL_TIMEOUT, cap=JOURNAL_CAPTURE_CAP)
    if result["error"]:
        entry["error"] = result["error"]
        return entry
    if result["rc"] != 0:
        entry["error"] = "journalctl_exit_%s" % result["rc"]
        return entry
    lines = [_redact(line)
             for line in result["stdout"].splitlines()[-JOURNAL_LINES:]]
    entry["truncated"] = bool(result["stdout_truncated"])
    while lines and len("\n".join(lines).encode("utf-8")) > JOURNAL_MAX_BYTES:
        lines.pop(0)
        entry["truncated"] = True
    entry["lines"] = lines
    return entry


# ---------------------------------------------------------------- 快照组装

def _timed(name, func, *args):
    started = time.monotonic()
    try:
        result = func(*args)
        if not isinstance(result, dict):
            result = {"ok": False, "error": "bad_check_result"}
    except Exception as exc:  # 单项检查异常绝不终止整轮
        result = {"ok": False, "error": "exception_%s" % type(exc).__name__}
    result["duration_ms"] = int((time.monotonic() - started) * 1000)
    return name, result


def collect_checks(work_dir):
    """两批有界并发：先取网关/接口上下文，再并行其余检查。"""
    checks = {}
    with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_WORKERS) as pool:
        context = [("default_route", check_default_route, APP_IFACE),
                   ("interface", check_interface, APP_IFACE)]
        for name, result in pool.map(lambda item: _timed(*item), context):
            checks[name] = result
        gateway = checks["default_route"].get("gateway")
        tasks = [
            ("local_http", check_local_http, work_dir),
            ("tcp_frp_7000", check_tcp, FRP_HOST, FRP_PORT),
            ("tcp_ssh_22", check_tcp, FRP_HOST, SSH_PORT),
            ("tcp_dns_53", check_tcp, DNS_HOST, DNS_PORT),
            ("resolver", check_resolver),
            ("gateway_ping", check_gateway_ping, gateway),
            ("neighbor", check_neighbor, APP_IFACE, gateway),
            ("frpc_service", check_frpc_service),
        ]
        for name, result in pool.map(lambda item: _timed(*item), tasks):
            checks[name] = result
    return checks


def _issues(checks):
    return ["%s: %s" % (name, checks[name].get("error") or "failed")
            for name in sorted(checks) if not checks[name].get("ok")]


def build_record(epoch, started, checks, journal):
    primary_issues = [name for name in PRIMARY_CHECKS
                      if not checks.get(name, {}).get("ok")]
    record = {
        "schema": SCHEMA,
        "ts": _now_iso(epoch),
        "epoch": round(epoch, 3),
        "elapsed_ms": int((time.monotonic() - started) * 1000),
        "hostname": socket.gethostname(),
        "iface": APP_IFACE,
        "checks": checks,
        "failed_checks": sorted(name for name in checks
                                if not checks[name].get("ok")),
        "issues": _issues(checks),
        "primary_issues": primary_issues,
        "primary_failure": bool(primary_issues),
        "probes_complete": all(check.get("observed")
                               for check in checks.values()),
    }
    if journal is not None:
        record["journal"] = journal
    return record


# ---------------------------------------------------------------- 日志与状态

def _rotate(log_dir):
    base = os.path.join(log_dir, EVENTS_FILENAME)
    for index in range(BACKUP_COUNT, 0, -1):
        src = base if index == 1 else "%s.%d" % (base, index - 1)
        dst = "%s.%d" % (base, index)
        if os.path.exists(src):
            os.replace(src, dst)
            os.chmod(dst, FILE_MODE)


def write_event(log_dir, record):
    """有界 JSONL 追加 + 4MiB 轮转（current + 7 备份）；失败向上抛 OSError。"""
    path = os.path.join(log_dir, EVENTS_FILENAME)
    line = (json.dumps(record, ensure_ascii=False, sort_keys=True)
            + "\n").encode("utf-8")
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_APPEND, FILE_MODE)
    try:
        os.chmod(path, FILE_MODE)
        if os.fstat(fd).st_size + len(line) > MAX_BYTES:
            os.close(fd)
            fd = -1
            _rotate(log_dir)
            fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_APPEND, FILE_MODE)
            os.chmod(path, FILE_MODE)
        with os.fdopen(fd, "ab", closefd=True) as handle:
            fd = -1
            handle.write(line)
            handle.flush()
            os.fsync(handle.fileno())
    finally:
        if fd >= 0:
            os.close(fd)


def read_state(log_dir):
    state = {"last_epoch": 0, "primary_failure": False, "issues": []}
    try:
        with open(os.path.join(log_dir, STATE_FILENAME), "r",
                  encoding="utf-8") as handle:
            loaded = json.load(handle)
    except (OSError, ValueError):
        return state
    if not isinstance(loaded, dict):
        return state
    last_epoch = loaded.get("last_epoch")
    if isinstance(last_epoch, (int, float)) and not isinstance(last_epoch, bool):
        state["last_epoch"] = last_epoch
    state["primary_failure"] = bool(loaded.get("primary_failure"))
    issues = loaded.get("issues")
    if isinstance(issues, list):
        state["issues"] = [str(item) for item in issues][:STATE_ISSUE_LIMIT]
    return state


def write_state(log_dir, state):
    path = os.path.join(log_dir, STATE_FILENAME)
    tmp = "%s.tmp.%d" % (path, os.getpid())
    try:
        with open(tmp, "w", encoding="utf-8") as handle:
            json.dump(state, handle, ensure_ascii=False, sort_keys=True)
            handle.flush()
            os.fsync(handle.fileno())
        os.chmod(tmp, FILE_MODE)
        os.replace(tmp, path)
    except OSError:
        try:
            os.unlink(tmp)
        except OSError:
            pass
        raise


def acquire_lock(log_dir):
    """非阻塞独占锁；已被占用返回 None（跳过本轮，不写半份证据）。"""
    fd = os.open(os.path.join(log_dir, LOCK_FILENAME),
                 os.O_RDWR | os.O_CREAT, FILE_MODE)
    try:
        fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        os.close(fd)
        return None
    os.chmod(os.path.join(log_dir, LOCK_FILENAME), FILE_MODE)
    return fd


def release_lock(fd):
    try:
        fcntl.flock(fd, fcntl.LOCK_UN)
    finally:
        os.close(fd)


# ---------------------------------------------------------------- 一轮执行

def run_tick(log_dir=None):
    log_dir = log_dir or LOG_DIR
    epoch = time.time()
    started = time.monotonic()

    try:
        os.makedirs(log_dir, mode=LOG_DIR_MODE, exist_ok=True)
    except OSError as exc:
        warn("cannot create log directory %s (%s); no evidence written"
             % (log_dir, type(exc).__name__))
        return 1
    if not os.path.isdir(log_dir):
        warn("log path %s is not a directory; no evidence written" % log_dir)
        return 1
    try:
        os.chmod(log_dir, LOG_DIR_MODE)
    except OSError:
        warn("cannot set mode 0700 on %s; continuing" % log_dir)

    try:
        lock_fd = acquire_lock(log_dir)
    except OSError as exc:
        warn("cannot open lock file in %s (%s); no evidence written"
             % (log_dir, type(exc).__name__))
        return 1
    if lock_fd is None:
        log("another run is in progress; skipping this tick")
        return 0
    try:
        previous = read_state(log_dir)
        try:
            work_dir = tempfile.mkdtemp(prefix="hnieoj-frp-net-diag.")
        except OSError:
            work_dir = None
        try:
            checks = collect_checks(work_dir)
        finally:
            if work_dir:
                shutil.rmtree(work_dir, ignore_errors=True)

        primary_failure = any(not checks.get(name, {}).get("ok")
                              for name in PRIMARY_CHECKS)
        journal = None
        if primary_failure or previous["primary_failure"]:
            # 当前失败优先；否则说明上一轮失败、本轮恢复。
            reason = "primary_failure" if primary_failure else "recovery"
            journal = collect_journal(reason)
        record = build_record(epoch, started, checks, journal)

        try:
            write_event(log_dir, record)
        except OSError as exc:
            warn("cannot write evidence to %s (%s); exiting nonzero"
                 % (os.path.join(log_dir, EVENTS_FILENAME),
                    type(exc).__name__))
            return 1
        state = {"last_epoch": int(epoch), "primary_failure": primary_failure,
                 "issues": record["issues"][:STATE_ISSUE_LIMIT]}
        try:
            write_state(log_dir, state)
        except OSError:
            warn("cannot update state %s; evidence was written"
                 % os.path.join(log_dir, STATE_FILENAME))
        log("snapshot written: primary_failure=%s probes_complete=%s issues=%s "
            "elapsed_ms=%d"
            % (primary_failure, record["probes_complete"],
               ",".join(record["issues"]) or "-", record["elapsed_ms"]))
        return 0
    finally:
        release_lock(lock_fd)


def main():
    try:
        return run_tick()
    except Exception as exc:  # 兜底：错误可见、退出非零，但不打印无界 traceback
        warn("unexpected failure (%s); evidence may be incomplete"
             % type(exc).__name__)
        return 1


if __name__ == "__main__":
    sys.exit(main())
