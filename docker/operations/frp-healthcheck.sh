#!/bin/bash
# ============================================================
# frp-healthcheck.sh —— FRP 外部健康检查与有界自动恢复
# 运行位置：应用服务器 root，由 frp-healthcheck.timer 每 60s 拉起一次。
#
# 背景：
#   frpc 进程存活不代表公网可用；实测出现过「进程在、外部连接超时」。
#   在应用服务器上源发请求公网域名时观测到 404，而公网主机返回 200；
#   该 404 只是应用服务器视角的观测结果，无法代表真实外部用户的访问，
#   因此外部可达性必须从公网主机侧确认（SSH 到 42.194.237.240，密钥登录，
#   公网侧 authorized_keys 用 restrict + 强制命令只允许运行探针脚本）。
#
# 判定流程：
#   1. 先查本机应用 http://127.0.0.1/：状态码恰为 200 且含页面标记才算健康。
#   2. 再用专用密钥 SSH 到公网主机执行探针，整体 timeout 30s；探针 stdout
#      必须严格等于 FRP_HEALTH_OK / FRP_HEALTH_FAILED 才采信，
#      SSH 失败（含 255）、超时（124）、输出非预期都按「未知」处理。
#   3. 只有「本机健康 且 外部探针连续 3 次确认失败」才 systemctl restart frpc；
#      距上次重启尝试不足 600s 时受冷却期抑制。
#   本机不健康或结论未知：连续失败计数清零并告警，绝不重启。
#   外部健康：连续失败计数清零（但不清 last_restart，冷却期不受影响）。
#
# 有界性（R1）：
#   本轮总耗时上限 = 本机 10s + SSH 30s + kill-after 2s + 重启 10s + kill-after 2s
#   = 54s，短于 service 的 TimeoutStartSec=55s。
#   ssh 一律用 -nT：-n 把 stdin 指向 /dev/null，-T 不分配 pty。否则交互式
#   root 手动执行时，ssh 若因读取终端收到 SIGTTIN 会进入 T（stopped）状态；
#   GNU timeout 默认只发 SIGTERM，对已停止的进程会一直等下去，整体就不再
#   有界。因此 ssh 与 systemctl 都套 `timeout --kill-after=2s`：宽限期后补发
#   SIGKILL，对 stopped/无响应进程也能强制收尾，systemctl 挂死同样拖不过 service。
#   冷却时间戳在动作之前就原子落盘：这样即使本进程随后被 service 超时
#   杀死，下一轮仍能读到冷却期而不会每分钟重复重启。落盘失败则 fail-closed，
#   本轮不执行任何重启。
#
# 状态：默认 /var/lib/hnieoj-frp-health（root 0700）。为便于隔离测试，
#       可用 FRP_HEALTH_STATE_DIR 覆盖；除此之外不读取任何外部配置，
#       不 source 任何文件，也没有通用框架。
#       计数与上次重启时间在持锁状态下原子写入（临时文件 + rename）。
# 日志：仅写 stdout/stderr，由 systemd 收进 journald；不发邮件、不接 Slack。
# ============================================================
set -uo pipefail

STATE_DIR=${FRP_HEALTH_STATE_DIR:-/var/lib/hnieoj-frp-health}
LOCK_FILE="$STATE_DIR/lock"
STATE_FILE="$STATE_DIR/state"

# 固定部署路径：专用探针私钥（应用服务器 0600）与公网主机 known_hosts。
PROBE_KEY=/etc/hnieoj-frp/probe_ed25519
PROBE_KNOWN_HOSTS=/etc/hnieoj-frp/known_hosts
PROBE_TARGET=ubuntu@42.194.237.240

LOCAL_URL=http://127.0.0.1/
MARKER='算法设计在线评测系统'

FAIL_THRESHOLD=3
COOLDOWN_SECONDS=600
SSH_TIMEOUT_SECONDS=30
RESTART_TIMEOUT_SECONDS=10
# 宽限期后补 SIGKILL：stopped（如 SIGTTIN）或无响应进程也能被强制收尾。
KILL_AFTER_SECONDS=2
CURL_CONNECT_TIMEOUT=5
CURL_MAX_TIME=10

TMP_DIR=$(mktemp -d "${TMPDIR:-/tmp}/frp-healthcheck.XXXXXX") || {
    printf '%s frp-healthcheck: ERROR cannot create temp dir\n' "$(date '+%F %T')" >&2
    exit 1
}
cleanup() { rm -rf -- "$TMP_DIR"; }
trap cleanup EXIT

log()  { printf '%s frp-healthcheck: %s\n' "$(date '+%F %T')" "$*"; }
warn() { printf '%s frp-healthcheck: WARN: %s\n' "$(date '+%F %T')" "$*" >&2; }

# root 状态目录（已存在时不改权限）；随后的 flock 依赖它存在。
mkdir -p -m 0700 -- "$STATE_DIR" 2>/dev/null || true
if [ ! -d "$STATE_DIR" ]; then
    printf '%s frp-healthcheck: ERROR cannot create state dir %s\n' \
        "$(date '+%F %T')" "$STATE_DIR" >&2
    exit 1
fi

# 非阻塞独占锁：上一轮尚未结束就跳过本轮，不做任何恢复判断。
exec 9>"$LOCK_FILE" || {
    printf '%s frp-healthcheck: ERROR cannot open lock file %s\n' \
        "$(date '+%F %T')" "$LOCK_FILE" >&2
    exit 1
}
if ! flock -n 9; then
    log "another run is in progress; skipping this tick"
    exit 0
fi

consecutive_failures=0
last_restart_epoch=0
if [ -r "$STATE_FILE" ]; then
    while IFS='=' read -r key value; do
        case "$value" in ''|*[!0-9]*) continue ;; esac
        case "$key" in
            consecutive_failures) consecutive_failures=$value ;;
            last_restart_epoch)   last_restart_epoch=$value ;;
        esac
    done < "$STATE_FILE"
fi

write_state() {
    local tmp="$STATE_FILE.tmp.$$"
    {
        printf 'consecutive_failures=%s\n' "$consecutive_failures"
        printf 'last_restart_epoch=%s\n'   "$last_restart_epoch"
    } > "$tmp" || { warn "cannot write state file $STATE_FILE"; rm -f -- "$tmp"; return 1; }
    mv -f -- "$tmp" "$STATE_FILE" || { warn "cannot replace state file $STATE_FILE"; return 1; }
}

check_local() {
    local body="$TMP_DIR/local-body" err="$TMP_DIR/local-err" code rc
    code=$(curl -4 --noproxy '*' --silent --show-error \
        --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" \
        --output "$body" --write-out '%{http_code}' "$LOCAL_URL" 2>"$err")
    rc=$?
    [ "$rc" -eq 0 ] || return 1
    [ "$code" = '200' ] || return 1
    grep -qF -- "$MARKER" "$body" || return 1
    return 0
}

check_remote() {
    # 输出 OK / FAILED / UNKNOWN；UNKNOWN 表示传输或输出不可采信。
    local out rc
    out=$(timeout --kill-after="${KILL_AFTER_SECONDS}s" "${SSH_TIMEOUT_SECONDS}s" \
        ssh -nT -i "$PROBE_KEY" \
        -o BatchMode=yes \
        -o StrictHostKeyChecking=yes \
        -o UserKnownHostsFile="$PROBE_KNOWN_HOSTS" \
        -o IdentitiesOnly=yes \
        -o ConnectTimeout=5 \
        "$PROBE_TARGET" 2>"$TMP_DIR/ssh-err")
    rc=$?
    if [ "$rc" -eq 0 ] && [ "$out" = 'FRP_HEALTH_OK' ]; then
        printf 'OK\n'
    elif [ "$rc" -eq 0 ] && [ "$out" = 'FRP_HEALTH_FAILED' ]; then
        printf 'FAILED\n'
    else
        printf 'UNKNOWN\n'
    fi
}

now=$(date +%s)
case "$now" in ''|*[!0-9]*) now=0 ;; esac

# ---- 1. 本机应用优先：不健康就清零计数、告警、直接结束（不重启）----
if ! check_local; then
    warn "local application check $LOCAL_URL failed (not HTTP 200 or missing page marker); resetting consecutive failures to 0 and NOT restarting frpc"
    consecutive_failures=0
    write_state
    exit 0
fi
log "local application check OK ($LOCAL_URL)"

# ---- 2. 公网主机外部探针 ----
remote=$(check_remote)
case "$remote" in
    OK)
        if [ "$consecutive_failures" -ne 0 ]; then
            log "external probe healthy again; resetting consecutive failures $consecutive_failures -> 0"
        else
            log "external probe healthy (FRP_HEALTH_OK)"
        fi
        consecutive_failures=0
        write_state
        exit 0
        ;;
    UNKNOWN)
        warn "external probe result unknown (SSH transport failed/timed out, or stdout was not an exact known marker); resetting consecutive failures to 0 and NOT restarting frpc"
        consecutive_failures=0
        write_state
        exit 0
        ;;
    FAILED)
        consecutive_failures=$((consecutive_failures + 1))
        log "external probe FAILED (FRP_HEALTH_FAILED); consecutive failures $consecutive_failures/$FAIL_THRESHOLD"
        ;;
esac

# ---- 3. 达到阈值才考虑重启，并受冷却期约束 ----
if [ "$consecutive_failures" -lt "$FAIL_THRESHOLD" ]; then
    write_state
    exit 0
fi

age=$((now - last_restart_epoch))
if [ "$last_restart_epoch" -gt 0 ] && [ "$age" -lt "$COOLDOWN_SECONDS" ]; then
    log "restart suppressed by cooldown: ${age}s since last restart attempt (< ${COOLDOWN_SECONDS}s)"
    write_state
    exit 0
fi

# R1：先把冷却时间戳落盘，再执行动作。
# 若本进程在动作期间被 service 超时杀死，state 里已经有 last_restart_epoch，
# 下一轮会受冷却期抑制，不会每分钟重复重启。落盘失败则 fail-closed。
last_restart_epoch=$now
if ! write_state; then
    warn "cannot persist cooldown timestamp to $STATE_FILE; refusing to restart frpc (fail closed)"
    exit 1
fi

log "ACTION restarting frpc after $consecutive_failures consecutive confirmed external failures"
if timeout --kill-after="${KILL_AFTER_SECONDS}s" "${RESTART_TIMEOUT_SECONDS}s" systemctl restart frpc; then
    rc=0
else
    rc=$?
fi
if [ "$rc" -eq 0 ]; then
    log "ACTION systemctl restart frpc succeeded"
    consecutive_failures=0
else
    # 冷却时间戳已在动作前落盘：每分钟不再重复重启，冷却结束才再试。
    warn "ACTION systemctl restart frpc failed (exit $rc); entering ${COOLDOWN_SECONDS}s cooldown without retry per tick"
fi
write_state || warn "cannot persist post-action state to $STATE_FILE"
exit 0
