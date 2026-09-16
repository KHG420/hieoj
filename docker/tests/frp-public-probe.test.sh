#!/bin/bash
# ============================================================
# frp-public-probe.test.sh —— frp-public-probe.sh 回归测试
#
# 在 hnieoj-unified-web 镜像内运行（自带 bash/curl/flock/timeout）；
# 用 PATH 前置的 curl 桩驱动真实探针脚本，临时目录用 TMPDIR 隔离。
# 不安装依赖、不触碰部署路径、不访问网络。
#
# 运行（仓库根目录）：
#   docker run --rm -v "$PWD":/work:ro --entrypoint bash hnieoj-unified-web:latest \
#       /work/docker/tests/frp-public-probe.test.sh
# ============================================================
set -uo pipefail

HERE=$(cd "$(dirname "$0")" && pwd)
DOCKER_DIR=$(cd "$HERE/.." && pwd)
PROBE="$DOCKER_DIR/operations/frp-public-probe.sh"

if [ ! -x "$PROBE" ]; then
    printf 'FAIL: probe script missing or not executable: %s\n' "$PROBE" >&2
    exit 1
fi

WORK=$(mktemp -d "${TMPDIR:-/tmp}/frp-probe-test.XXXXXX")
trap 'rm -rf -- "$WORK"' EXIT
STUBS="$WORK/stubs"; mkdir -p "$STUBS"
TMPROOT="$WORK/tmp"; mkdir -p "$TMPROOT"
CURL_LOG="$WORK/curl.log"; : > "$CURL_LOG"
export PATH="$STUBS:$PATH"
export FRP_TEST_CURL_LOG="$CURL_LOG"

cat > "$STUBS/curl" <<'STUB'
#!/bin/bash
printf '%s\n' "$*" >> "${FRP_TEST_CURL_LOG:?}"
out=""; url=""
while [ $# -gt 0 ]; do
    case "$1" in
        --output) out="$2"; shift 2 ;;
        --write-out) shift 2 ;;
        --connect-timeout|--max-time) shift 2 ;;
        --noproxy) shift 2 ;;
        --noproxy=*) shift ;;
        -4|--silent|--show-error) shift ;;
        *) url="$1"; shift ;;
    esac
done
case "$url" in
    *www.hnieacm.com*)
        code=${FRP_TEST_WWW_CODE:-200}
        body=${FRP_TEST_WWW_BODY:-算法设计在线评测系统}
        rc=${FRP_TEST_WWW_RC:-0}
        ;;
    *hnieacm.com*)
        code=${FRP_TEST_APEX_CODE:-200}
        body=${FRP_TEST_APEX_BODY:-算法设计在线评测系统}
        rc=${FRP_TEST_APEX_RC:-0}
        ;;
    *)
        code=000; body=''; rc=1
        ;;
esac
if [ -n "$out" ]; then printf '%s' "$body" > "$out"; fi
printf '%s' "$code"
exit "$rc"
STUB
chmod +x "$STUBS/curl"

PASS=0; FAIL=0
ok()  { printf 'PASS: %s\n' "$1"; PASS=$((PASS + 1)); }
bad() { printf 'FAIL: %s\n' "$1"; FAIL=$((FAIL + 1)); }
expect() { # desc expected actual
    if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (expected [$2], got [$3])"; fi
}

reset_probe() {
    rm -rf -- "$TMPROOT"; mkdir -p -- "$TMPROOT"
    : > "$CURL_LOG"
    unset FRP_TEST_APEX_CODE FRP_TEST_APEX_BODY FRP_TEST_APEX_RC
    unset FRP_TEST_WWW_CODE FRP_TEST_WWW_BODY FRP_TEST_WWW_RC
}

run_probe() {
    TMPDIR="$TMPROOT" "$PROBE" >"$WORK/out" 2>"$WORK/err"
    P_RC=$?
    P_OUT=$(cat "$WORK/out")
    P_ERR=$(cat "$WORK/err")
}

# ---- 1. 两个域名都健康 ----
reset_probe
run_probe
expect "both healthy: exit 0" 0 "$P_RC"
expect "both healthy: exact FRP_HEALTH_OK" FRP_HEALTH_OK "$P_OUT"

# ---- 2. 任一域名失败即为失败 ----
reset_probe
export FRP_TEST_WWW_CODE=500
run_probe
expect "one bad domain (www 500): exit 0" 0 "$P_RC"
expect "one bad domain: FRP_HEALTH_FAILED" FRP_HEALTH_FAILED "$P_OUT"

# ---- 3. 带标记的 404 页面不算健康 ----
reset_probe
export FRP_TEST_APEX_CODE=404
run_probe
expect "404 with correct marker: FRP_HEALTH_FAILED" FRP_HEALTH_FAILED "$P_OUT"

# ---- 4. 200 但页面内容错误不算健康 ----
reset_probe
export FRP_TEST_APEX_BODY='HTTP 200 but not the OJ page'
run_probe
expect "200 with wrong body: FRP_HEALTH_FAILED" FRP_HEALTH_FAILED "$P_OUT"

# ---- 5. 传输失败也归入已知失败（退出 0），而不是未知 ----
reset_probe
export FRP_TEST_APEX_RC=7 FRP_TEST_WWW_RC=7
run_probe
expect "curl transport error: exit 0" 0 "$P_RC"
expect "curl transport error: FRP_HEALTH_FAILED" FRP_HEALTH_FAILED "$P_OUT"

# ---- 6. 请求参数契约：IPv4、禁代理、超时、TLS 校验、两个 URL、两次请求 ----
reset_probe
run_probe
expect "probe makes exactly two requests" 2 "$(grep -c . "$CURL_LOG")"
grep -q -- '-4' "$CURL_LOG" && ok "arg: -4 (IPv4 only)" || bad "arg: -4 (IPv4 only)"
grep -q -- '--noproxy' "$CURL_LOG" && ok "arg: --noproxy" || bad "arg: --noproxy"
grep -q -- '--connect-timeout 5' "$CURL_LOG" && ok "arg: --connect-timeout 5" || bad "arg: --connect-timeout 5"
grep -q -- '--max-time 10' "$CURL_LOG" && ok "arg: --max-time 10" || bad "arg: --max-time 10"
grep -qF -- 'https://hnieacm.com/' "$CURL_LOG" && ok "arg: apex URL" || bad "arg: apex URL"
grep -qF -- 'https://www.hnieacm.com/' "$CURL_LOG" && ok "arg: www URL" || bad "arg: www URL"
if grep -Eq -- '--insecure|(^| )-k( |$)' "$CURL_LOG"; then
    bad "TLS validation must stay enabled (no -k/--insecure)"
else
    ok "TLS validation stays enabled"
fi

# ---- 7. 临时文件清理 ----
reset_probe
run_probe
left=$(find "$TMPROOT" -mindepth 1 -maxdepth 1 -name 'frp-public-probe.*' | wc -l | tr -d ' ')
expect "temp files cleaned up" 0 "$left"

printf '\n%s passed, %s failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
