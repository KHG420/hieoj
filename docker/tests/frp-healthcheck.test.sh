#!/bin/bash
# ============================================================
# frp-healthcheck.test.sh —— frp-healthcheck.sh 回归测试
#
# 在 hnieoj-unified-web 镜像内运行（自带 bash/curl/flock/timeout）；
# 用 PATH 前置的 curl/ssh/systemctl/timeout 桩驱动真实脚本，
# 状态目录用 FRP_HEALTH_STATE_DIR 隔离到临时目录，不触碰部署路径、不联网。
#
# 运行（仓库根目录）：
#   docker run --rm -v "$PWD":/work:ro --entrypoint bash hnieoj-unified-web:latest \
#       /work/docker/tests/frp-healthcheck.test.sh
# ============================================================
set -uo pipefail

HERE=$(cd "$(dirname "$0")" && pwd)
DOCKER_DIR=$(cd "$HERE/.." && pwd)
HEALTHCHECK="$DOCKER_DIR/operations/frp-healthcheck.sh"

if [ ! -x "$HEALTHCHECK" ]; then
    printf 'FAIL: healthcheck script missing or not executable: %s\n' "$HEALTHCHECK" >&2
    exit 1
fi

WORK=$(mktemp -d "${TMPDIR:-/tmp}/frp-hc-test.XXXXXX")
trap 'rm -rf -- "$WORK"' EXIT
STUBS="$WORK/stubs"; mkdir -p "$STUBS"
STATE="$WORK/state"
TMPROOT="$WORK/tmp"
SYSTEMCTL_LOG="$WORK/systemctl.log"
SSH_LOG="$WORK/ssh.log"
TIMEOUT_LOG="$WORK/timeout.log"
CURL_LOG="$WORK/curl.log"
: > "$SYSTEMCTL_LOG"; : > "$SSH_LOG"; : > "$TIMEOUT_LOG"; : > "$CURL_LOG"

export PATH="$STUBS:$PATH"
export FRP_TEST_SYSTEMCTL_LOG="$SYSTEMCTL_LOG"
export FRP_TEST_SSH_LOG="$SSH_LOG"
export FRP_TEST_TIMEOUT_LOG="$TIMEOUT_LOG"
export FRP_TEST_CURL_LOG="$CURL_LOG"
export FRP_TEST_STATE_FILE="$STATE/state"

cat > "$STUBS/curl" <<'STUB'
#!/bin/bash
printf '%s\n' "$*" >> "${FRP_TEST_CURL_LOG:?}"
out=""
while [ $# -gt 0 ]; do
    case "$1" in
        --output) out="$2"; shift 2 ;;
        --write-out) shift 2 ;;
        --connect-timeout|--max-time) shift 2 ;;
        --noproxy) shift 2 ;;
        --noproxy=*) shift ;;
        -4|--silent|--show-error) shift ;;
        *) shift ;;
    esac
done
if [ -n "$out" ]; then printf '%s' "${FRP_TEST_LOCAL_BODY:-算法设计在线评测系统}" > "$out"; fi
printf '%s' "${FRP_TEST_LOCAL_CODE:-200}"
exit "${FRP_TEST_CURL_RC:-0}"
STUB

cat > "$STUBS/ssh" <<'STUB'
#!/bin/bash
printf '%s\n' "$*" >> "${FRP_TEST_SSH_LOG:?}"
printf '%s' "${FRP_TEST_SSH_OUT-FRP_HEALTH_OK}"
exit "${FRP_TEST_SSH_RC:-0}"
STUB

cat > "$STUBS/systemctl" <<'STUB'
#!/bin/bash
printf '%s\n' "$*" >> "${FRP_TEST_SYSTEMCTL_LOG:?}"
if [ "${1:-}" = restart ] && [ "${2:-}" = frpc ]; then
    # R1：抓取动作发生“当时”的 state，用于断言冷却时间戳先于动作落盘。
    if [ -n "${FRP_TEST_SYSTEMCTL_STATE_SNAPSHOT:-}" ]; then
        cat "${FRP_TEST_STATE_FILE:-}" >> "$FRP_TEST_SYSTEMCTL_STATE_SNAPSHOT" 2>/dev/null || true
    fi
    exit "${FRP_TEST_SYSTEMCTL_RC:-0}"
fi
exit 0
STUB

cat > "$STUBS/timeout" <<'STUB'
#!/bin/bash
printf '%s\n' "$*" >> "${FRP_TEST_TIMEOUT_LOG:?}"
# 脚本对探针 ssh 与 systemctl 重启都加了 GNU `--kill-after=Ns` 兜底；
# 先剥离该前导选项，再判断被包裹的命令，避免把探针 ssh 调用也一起拦截。
kill_after=()
case "${1:-}" in
    --kill-after=*) kill_after=("$1"); shift ;;
esac
# FRP_TEST_TIMEOUT_RC 只拦截 `timeout ... systemctl ...`（模拟重启超时 124），
# 不影响 `timeout ... ssh ...` 探针调用。
if [ -n "${FRP_TEST_TIMEOUT_RC:-}" ] && [ "${2:-}" = systemctl ]; then
    exit "$FRP_TEST_TIMEOUT_RC"
fi
if [ "${#kill_after[@]}" -gt 0 ]; then
    exec /usr/bin/timeout "${kill_after[0]}" "$@"
fi
exec /usr/bin/timeout "$@"
STUB

chmod +x "$STUBS/curl" "$STUBS/ssh" "$STUBS/systemctl" "$STUBS/timeout"

PASS=0; FAIL=0
ok()  { printf 'PASS: %s\n' "$1"; PASS=$((PASS + 1)); }
bad() { printf 'FAIL: %s\n' "$1"; FAIL=$((FAIL + 1)); }
expect() { # desc expected actual
    if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (expected [$2], got [$3])"; fi
}

reset_state() {
    rm -rf -- "$STATE" "$TMPROOT"; mkdir -p -- "$STATE" "$TMPROOT"
    : > "$SYSTEMCTL_LOG"; : > "$SSH_LOG"; : > "$TIMEOUT_LOG"; : > "$CURL_LOG"
    unset FRP_TEST_LOCAL_CODE FRP_TEST_LOCAL_BODY FRP_TEST_CURL_RC
    unset FRP_TEST_SSH_OUT FRP_TEST_SSH_RC FRP_TEST_SYSTEMCTL_RC
    unset FRP_TEST_TIMEOUT_RC FRP_TEST_SYSTEMCTL_STATE_SNAPSHOT
    rm -f -- "$WORK/systemctl-state.snapshot"
}

seed_state() { # counter, last_restart_epoch
    printf 'consecutive_failures=%s\nlast_restart_epoch=%s\n' "$1" "$2" > "$STATE/state"
}

state_val() { grep "^$1=" "$STATE/state" 2>/dev/null | cut -d= -f2-; }
restart_count() { wc -l < "$SYSTEMCTL_LOG" | tr -d ' '; }

run_hc() {
    FRP_HEALTH_STATE_DIR="$STATE" TMPDIR="$TMPROOT" "$HEALTHCHECK" >"$WORK/out" 2>"$WORK/err"
    HC_RC=$?
    HC_OUT=$(cat "$WORK/out")
    HC_ERR=$(cat "$WORK/err")
}

# ---- 1. 健康：不重启，计数为 0 ----
reset_state
export FRP_TEST_SSH_OUT=FRP_HEALTH_OK FRP_TEST_SSH_RC=0
run_hc
expect "healthy: exit 0" 0 "$HC_RC"
expect "healthy: no restart" 0 "$(restart_count)"
expect "healthy: counter 0" 0 "$(state_val consecutive_failures)"
grep -q 'external probe healthy' "$WORK/out" && ok "healthy: logged" || bad "healthy: logged"
grep -qF -- 'http://127.0.0.1/' "$CURL_LOG" && ok "local check uses http://127.0.0.1/" || bad "local check uses http://127.0.0.1/"

# ---- 2. 前两次失败不重启，第三次重启一次 ----
reset_state
export FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED FRP_TEST_SSH_RC=0 FRP_TEST_SYSTEMCTL_RC=0
run_hc
expect "failure 1: counter 1" 1 "$(state_val consecutive_failures)"
expect "failure 1: no restart" 0 "$(restart_count)"
run_hc
expect "failure 2: counter 2" 2 "$(state_val consecutive_failures)"
expect "failure 2: no restart" 0 "$(restart_count)"
run_hc
expect "failure 3: restart attempted once" 1 "$(restart_count)"
expect "failure 3: counter reset after successful restart" 0 "$(state_val consecutive_failures)"
expect "failure 3: exit 0" 0 "$HC_RC"

# ---- 3. 恢复（健康）清零连续失败 ----
reset_state
export FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED FRP_TEST_SSH_RC=0
run_hc
expect "recovery: counter 1 before" 1 "$(state_val consecutive_failures)"
export FRP_TEST_SSH_OUT=FRP_HEALTH_OK
run_hc
expect "recovery: counter reset to 0" 0 "$(state_val consecutive_failures)"
expect "recovery: no restart" 0 "$(restart_count)"
grep -q 'healthy again' "$WORK/out" && ok "recovery: transition logged" || bad "recovery: transition logged"

# ---- 4. 冷却期内不重启，冷却结束后重启 ----
reset_state
seed_state 2 "$(( $(date +%s) - 100 ))"
export FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED FRP_TEST_SSH_RC=0
run_hc
expect "cooldown active: no restart" 0 "$(restart_count)"
expect "cooldown active: exit 0" 0 "$HC_RC"
grep -q 'cooldown' "$WORK/out" && ok "cooldown active: logged" || bad "cooldown active: logged"

reset_state
seed_state 2 "$(( $(date +%s) - 700 ))"
export FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED FRP_TEST_SSH_RC=0
run_hc
expect "cooldown expired: restart attempted" 1 "$(restart_count)"

# ---- 5. 健康检查清零计数，但不影响冷却期 ----
reset_state
lr=$(( $(date +%s) - 100 ))
seed_state 0 "$lr"
export FRP_TEST_SSH_OUT=FRP_HEALTH_OK
run_hc
expect "cooldown persistent: last_restart unchanged after healthy" "$lr" "$(state_val last_restart_epoch)"
export FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED
run_hc; run_hc; run_hc
expect "cooldown persistent: no restart within cooldown" 0 "$(restart_count)"

# ---- 6. 本机不健康：清零计数、告警、不重启、不发起远程探测 ----
reset_state
seed_state 2 0
export FRP_TEST_LOCAL_CODE=500 FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED FRP_TEST_SSH_RC=0
run_hc
expect "local HTTP 500: no restart" 0 "$(restart_count)"
expect "local HTTP 500: counter reset" 0 "$(state_val consecutive_failures)"
grep -q 'WARN' "$WORK/err" && ok "local HTTP 500: warning logged" || bad "local HTTP 500: warning logged"
[ ! -s "$SSH_LOG" ] && ok "local HTTP 500: remote probe skipped" || bad "local HTTP 500: remote probe skipped"

reset_state
seed_state 2 0
export FRP_TEST_LOCAL_BODY='not the expected page' FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED FRP_TEST_SSH_RC=0
run_hc
expect "local missing marker: no restart" 0 "$(restart_count)"
expect "local missing marker: counter reset" 0 "$(state_val consecutive_failures)"

# ---- 7. SSH 255 / 输出非预期 / 空输出：未知，不重启 ----
reset_state
seed_state 2 0
export FRP_TEST_SSH_OUT='' FRP_TEST_SSH_RC=255
run_hc
expect "ssh 255: no restart" 0 "$(restart_count)"
expect "ssh 255: counter reset" 0 "$(state_val consecutive_failures)"
grep -q 'WARN' "$WORK/err" && ok "ssh 255: warning logged" || bad "ssh 255: warning logged"

reset_state
seed_state 2 0
export FRP_TEST_SSH_OUT='FRP_HEALTH_OK extra' FRP_TEST_SSH_RC=0
run_hc
expect "unexpected stdout: no restart" 0 "$(restart_count)"
expect "unexpected stdout: counter reset" 0 "$(state_val consecutive_failures)"

reset_state
seed_state 2 0
export FRP_TEST_SSH_OUT='' FRP_TEST_SSH_RC=0
run_hc
expect "empty stdout: no restart" 0 "$(restart_count)"
expect "empty stdout: counter reset" 0 "$(state_val consecutive_failures)"

# ---- 8. 重启失败：记录冷却，冷却期内本分钟内不重试 ----
reset_state
seed_state 2 0
export FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED FRP_TEST_SSH_RC=0 FRP_TEST_SYSTEMCTL_RC=1
run_hc
expect "restart fails: attempted once" 1 "$(restart_count)"
lr=$(state_val last_restart_epoch)
if [ "${lr:-0}" -gt 0 ]; then ok "restart fails: cooldown recorded"; else bad "restart fails: cooldown recorded"; fi
grep -q 'systemctl restart frpc failed' "$WORK/err" && ok "restart fails: failure logged" || bad "restart fails: failure logged"
run_hc
expect "restart fails: not retried within cooldown" 1 "$(restart_count)"

# ---- 9. SSH 调用契约与整体 30s 上限（含 -nT / --kill-after）----
reset_state
export FRP_TEST_SSH_OUT=FRP_HEALTH_OK
run_hc
grep -qF -- '-i /etc/hnieoj-frp/probe_ed25519' "$SSH_LOG" && ok "ssh: dedicated key" || bad "ssh: dedicated key"
grep -qF -- 'BatchMode=yes' "$SSH_LOG" && ok "ssh: BatchMode=yes" || bad "ssh: BatchMode=yes"
grep -qF -- 'StrictHostKeyChecking=yes' "$SSH_LOG" && ok "ssh: StrictHostKeyChecking=yes" || bad "ssh: StrictHostKeyChecking=yes"
grep -qF -- 'UserKnownHostsFile=/etc/hnieoj-frp/known_hosts' "$SSH_LOG" && ok "ssh: pinned known_hosts" || bad "ssh: pinned known_hosts"
grep -qF -- 'ConnectTimeout=5' "$SSH_LOG" && ok "ssh: ConnectTimeout=5" || bad "ssh: ConnectTimeout=5"
grep -qF -- 'ubuntu@42.194.237.240' "$SSH_LOG" && ok "ssh: probe host" || bad "ssh: probe host"
# REWORK 回归：ssh 必须 -nT（-n 关 stdin、-T 不分配 pty），否则交互式手动执行时
# ssh 会因读取终端收到 SIGTTIN 进入 stopped 状态，GNU timeout 只发 SIGTERM 会一直等。
grep -qF -- '-nT' "$SSH_LOG" && ok "ssh: -nT (no stdin, no pty)" || bad "ssh: -nT (no stdin, no pty) (ssh log=[$(cat "$SSH_LOG")])"
# REWORK 回归：ssh 外层必须带 --kill-after=2s，宽限期后补 SIGKILL，保证整体有界。
grep -qE -- '^--kill-after=2s 30s ssh' "$TIMEOUT_LOG" && ok "ssh: bounded by timeout --kill-after=2s 30s" || bad "ssh: bounded by timeout --kill-after=2s 30s (timeout log=[$(cat "$TIMEOUT_LOG")])"

# ---- 10. flock 非阻塞：锁被占用时跳过本轮，不重启 ----
reset_state
seed_state 2 0
export FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED FRP_TEST_SSH_RC=0
touch "$STATE/lock"
rm -f "$WORK/lock-held"
( flock -x 8; touch "$WORK/lock-held"; sleep 2 ) 8>"$STATE/lock" &
holder=$!
i=0
while [ ! -e "$WORK/lock-held" ] && [ "$i" -lt 50 ]; do sleep 0.1; i=$((i + 1)); done
run_hc
expect "flock busy: no restart" 0 "$(restart_count)"
grep -q 'in progress' "$WORK/out" && ok "flock busy: skip logged" || bad "flock busy: skip logged"
wait "$holder" 2>/dev/null || true
rm -f "$WORK/lock-held"

# ---- 11. 临时文件清理 ----
reset_state
export FRP_TEST_SSH_OUT=FRP_HEALTH_OK
run_hc
left=$(find "$TMPROOT" -mindepth 1 -maxdepth 1 -name 'frp-healthcheck.*' | wc -l | tr -d ' ')
expect "temp files cleaned up" 0 "$left"

# ---- 12. R1：冷却时间戳在动作前落盘，动作由 timeout --kill-after=2s 10s 兜底 ----
reset_state
seed_state 2 0
export FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED FRP_TEST_SSH_RC=0 FRP_TEST_SYSTEMCTL_RC=0
export FRP_TEST_SYSTEMCTL_STATE_SNAPSHOT="$WORK/systemctl-state.snapshot"
: > "$FRP_TEST_SYSTEMCTL_STATE_SNAPSHOT"
run_hc
expect "pre-action: restart attempted once" 1 "$(restart_count)"
expect "pre-action: exit 0" 0 "$HC_RC"
snap_lr=$(grep '^last_restart_epoch=' "$FRP_TEST_SYSTEMCTL_STATE_SNAPSHOT" 2>/dev/null | tail -1 | cut -d= -f2-)
if [ "${snap_lr:-0}" -gt 0 ]; then
    ok "pre-action: cooldown timestamp persisted before systemctl ran"
else
    bad "pre-action: cooldown timestamp persisted before systemctl ran (snapshot=[$(cat "$FRP_TEST_SYSTEMCTL_STATE_SNAPSHOT" 2>/dev/null)])"
fi
grep -qE -- '^--kill-after=2s 10s systemctl restart frpc$' "$TIMEOUT_LOG" \
    && ok "action: restart bounded by timeout --kill-after=2s 10s" \
    || bad "action: restart bounded by timeout --kill-after=2s 10s (timeout log=[$(cat "$TIMEOUT_LOG")])"

# ---- 13. R1：重启被 timeout 掐断（124）也要进入冷却，下一轮不重复 ----
reset_state
seed_state 2 0
export FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED FRP_TEST_SSH_RC=0 FRP_TEST_TIMEOUT_RC=124
run_hc
expect "restart timeout 124: exit 0" 0 "$HC_RC"
lr=$(state_val last_restart_epoch)
if [ "${lr:-0}" -gt 0 ]; then
    ok "restart timeout 124: cooldown timestamp persisted"
else
    bad "restart timeout 124: cooldown timestamp persisted (state=[$(cat "$STATE/state" 2>/dev/null)])"
fi
grep -q 'systemctl restart frpc failed' "$WORK/err" && ok "restart timeout 124: failure logged" || bad "restart timeout 124: failure logged"
grep -qF -- 'exit 124' "$WORK/err" && ok "restart timeout 124: exit code surfaced" || bad "restart timeout 124: exit code surfaced (err=[$(cat "$WORK/err")])"
restart_timeouts() { grep -cE ' systemctl restart frpc$' "$TIMEOUT_LOG" | tr -d ' '; }
expect "restart timeout 124: bordered attempt made" 1 "$(restart_timeouts)"
unset FRP_TEST_TIMEOUT_RC
run_hc
expect "restart timeout 124: next tick not retried" 0 "$(restart_count)"
grep -q 'cooldown' "$WORK/out" && ok "restart timeout 124: next tick suppressed by cooldown" || bad "restart timeout 124: next tick suppressed by cooldown"
expect "restart timeout 124: no second timeout attempt" 1 "$(restart_timeouts)"

# ---- 14. R1：冷却时间戳落盘失败 => fail-closed，绝不重启 ----
reset_state
seed_state 2 0
export FRP_TEST_SSH_OUT=FRP_HEALTH_FAILED FRP_TEST_SSH_RC=0
# 以非 root（nobody）运行，让只读 state 目录真正导致写盘失败（root 会绕过权限）。
: > "$STATE/lock"
chown -R 65534:65534 "$STUBS" "$TMPROOT" "$STATE"
chmod 0755 "$WORK"
chmod 0777 "$TMPROOT" "$STUBS"
chmod 0666 "$SYSTEMCTL_LOG" "$SSH_LOG" "$TIMEOUT_LOG" "$CURL_LOG"
chmod 0600 "$STATE/state" "$STATE/lock"
chmod 0500 "$STATE"
setpriv --reuid=65534 --regid=65534 --clear-groups \
    env PATH="$STUBS:$PATH" FRP_HEALTH_STATE_DIR="$STATE" TMPDIR="$TMPROOT" \
        FRP_TEST_SYSTEMCTL_LOG="$SYSTEMCTL_LOG" FRP_TEST_SSH_LOG="$SSH_LOG" \
        FRP_TEST_TIMEOUT_LOG="$TIMEOUT_LOG" FRP_TEST_CURL_LOG="$CURL_LOG" \
        FRP_TEST_STATE_FILE="$FRP_TEST_STATE_FILE" \
        FRP_TEST_SSH_OUT="$FRP_TEST_SSH_OUT" FRP_TEST_SSH_RC="$FRP_TEST_SSH_RC" \
        "$HEALTHCHECK" >"$WORK/out" 2>"$WORK/err"
HC_RC=$?
HC_OUT=$(cat "$WORK/out"); HC_ERR=$(cat "$WORK/err")
expect "persist fail: no restart" 0 "$(restart_count)"
if [ "$HC_RC" -ne 0 ]; then
    ok "persist fail: exits nonzero"
else
    bad "persist fail: exits nonzero (rc=$HC_RC out=[$HC_OUT])"
fi
grep -q 'refusing to restart' "$WORK/err" && ok "persist fail: fail-closed warned" || bad "persist fail: fail-closed warned (err=[$HC_ERR])"
expect "persist fail: cooldown not recorded" 0 "$(state_val last_restart_epoch)"
chmod 0700 "$STATE"

# ---- 15. REWORK：--kill-after 对无响应子进程真实有效 ----
# 用一个忽略 SIGTERM 的子进程代表「不响应」的 ssh/systemctl：只发 SIGTERM 会
# 一直等；`--kill-after` 在宽限期后补 SIGKILL 才能有界收尾（rc=137）。
if [ -x /usr/bin/timeout ]; then
    started=$(date +%s)
    # `{ ...; } 2>/dev/null` 吸收父 shell 对被信号杀死子进程的 "Killed" 提示。
    rc=$( { /usr/bin/timeout --kill-after=1s 1s bash -c 'trap "" TERM; sleep 30'; printf '%s' "$?"; } 2>/dev/null )
    elapsed=$(( $(date +%s) - started ))
    if [ "$rc" -eq 137 ] && [ "$elapsed" -le 6 ]; then
        ok "kill-after bounds a SIGTERM-ignoring child (rc=$rc, ${elapsed}s)"
    else
        bad "kill-after bounds a SIGTERM-ignoring child (rc=$rc, ${elapsed}s)"
    fi
else
    bad "kill-after bounds a SIGTERM-ignoring child (/usr/bin/timeout missing)"
fi

printf '\n%s passed, %s failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
