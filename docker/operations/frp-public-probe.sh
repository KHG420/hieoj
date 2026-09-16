#!/bin/bash
# ============================================================
# frp-public-probe.sh —— FRP 外部可达性探针（运行在公网主机）
#
# 只读脚本：仅对两个公网域名发起 HTTPS 请求，不修改系统状态。
# 由受限 SSH 强制命令在公网主机（42.194.237.240）上执行，见
#   /home/ubuntu/.ssh/authorized_keys:
#     restrict,command="/usr/local/sbin/hnieoj-frp-public-probe" ssh-ed25519 ...
# 因此客户端只执行 `ssh ubuntu@42.194.237.240`，不传远程命令。
#
# 为什么必须从公网主机探测：
#   应用服务器源发请求公网域名时实测得到 404，而公网主机得到 200；该 404
#   只是应用服务器视角的观测结果，不能代表真实外部用户看到的页面。
#   只有公网主机侧的请求才是外部视角。
#
# 判定（两个域名都要过）：
#   * TLS 证书默认校验（不传 -k/--insecure）；
#   * 仅 IPv4（-4），忽略代理（--noproxy '*'）；
#   * HTTP 状态码必须恰为 200；
#   * 响应体必须包含稳定页面标记「算法设计在线评测系统」。
#   先判状态码再判标记：只查标记会把「带标记的 404 页面」误判为健康。
#
# 输出约定：
#   两个域名都健康 → 恰好一行 FRP_HEALTH_OK
#   任一域名失败   → 恰好一行 FRP_HEALTH_FAILED
#   两种已知结论都以 0 退出，从而与「SSH 传输失败」（非零退出）区分开。
#   调用方 frp-healthcheck.sh 对 stdout 做严格整串匹配；未知输出/非零退出
#   都按「未知」处理，绝不触发恢复动作。
# ============================================================
set -uo pipefail

MARKER='算法设计在线评测系统'
URLS=('https://hnieacm.com/' 'https://www.hnieacm.com/')
CONNECT_TIMEOUT=5
MAX_TIME=10

TMP_ROOT=$(mktemp -d "${TMPDIR:-/tmp}/frp-public-probe.XXXXXX") || {
    printf 'frp-public-probe: cannot create temp dir\n' >&2
    exit 2
}
cleanup() { rm -rf -- "$TMP_ROOT"; }
trap cleanup EXIT

healthy=1
index=0
for url in "${URLS[@]}"; do
    index=$((index + 1))
    body="$TMP_ROOT/body.$index"
    err="$TMP_ROOT/err.$index"

    code=$(curl -4 --noproxy '*' \
        --silent --show-error \
        --connect-timeout "$CONNECT_TIMEOUT" --max-time "$MAX_TIME" \
        --output "$body" --write-out '%{http_code}' \
        "$url" 2>"$err")
    rc=$?

    if [ "$rc" -ne 0 ] || [ "$code" != '200' ] || ! grep -qF -- "$MARKER" "$body"; then
        healthy=0
    fi
done

if [ "$healthy" -eq 1 ]; then
    printf 'FRP_HEALTH_OK\n'
else
    printf 'FRP_HEALTH_FAILED\n'
fi
exit 0
