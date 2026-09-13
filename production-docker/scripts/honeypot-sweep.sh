#!/bin/bash
# ============================================================
# honeypot-sweep.sh —— 蜜罐命中清扫
#
# 蜜罐定义见 conf/nginx/snippets/oj-hardening.conf：
#   路径 /_oj_(trap|export|internal|debug)/ 在 robots.txt 里被 Disallow，
#   同时以 display:none 隐藏在页面里。真实访客两条都不会碰，
#   所以「命中」= 确定性爬虫判据（比枚举广度更硬），给 7 天 TTL。
#
# 与 crawler-guard.sh 的分工：
#   crawler-guard.sh  → 按枚举广度抓「还不知道是谁」的新爬虫（TTL 24h）
#   honeypot-sweep.sh → 处理已被诱饵确证的爬虫（TTL 7d）
# 两者写 blocklist.map 的不同区块，互不覆盖。
#
# 注：脚本刻意不使用 /dev/null（用临时 sink 文件），以便在受限沙箱中也可运行。
# ============================================================
set -uo pipefail

BLOCK_FILE=/opt/hnieoj-docker/conf/nginx/conf.d/blocklist.map
ALLOW_FILE=/opt/hnieoj-docker/conf/crawler-guard.allow
RUN_DIR=/opt/hnieoj-docker/run
STATE_LOG=/var/log/hnieoj-crawler-guard.log
CT=hnieoj-web
TTL_SECONDS=${HP_TTL:-604800}   # 7 天

SINK=$(mktemp); TMP_IPS=$(mktemp); TMP_OUT=$(mktemp)
cleanup() { rm -f "$SINK" "$TMP_IPS" "$TMP_OUT" "$TMP_OUT.d"; }
trap cleanup EXIT

log() { echo "$(date '+%F %T') [honeypot] $*" >> "$STATE_LOG"; }

mkdir -p "$RUN_DIR"
docker exec "$CT" true > "$SINK" 2>&1 || { log "SKIP: 容器不可用"; exit 0; }
[ -f "$BLOCK_FILE" ] || { log "ERROR: 找不到 $BLOCK_FILE"; exit 1; }

# ---------- 1. 读取蜜罐日志 ----------
docker exec "$CT" sh -c 'cat /var/log/nginx/honeypot.log 2>/dev/null || true' > "$TMP_IPS" 2> "$SINK"

# ---------- 2. 提取来源 IP（去重 + 白名单过滤）----------
awk '{ ip=$1; if (ip != "" && ip !~ /^-$/) print ip }' "$TMP_IPS" | sort -u > "$TMP_IPS.u"

FILTERED=$(mktemp)
: > "$FILTERED"
while IFS= read -r ip; do
    [ -z "$ip" ] && continue
    hit=0
    if [ -f "$ALLOW_FILE" ]; then
        while IFS= read -r line; do
            case "$line" in ''|'#'*) continue ;; esac
            case "$ip" in "$line"*) hit=1; break ;; esac
        done < "$ALLOW_FILE"
    fi
    [ "$hit" = "0" ] && echo "$ip" >> "$FILTERED"
done < "$TMP_IPS.u"
rm -f "$TMP_IPS.u"

NOW=$(date +%s)
N_NEW=$(wc -l < "$FILTERED")
[ "$N_NEW" -gt 0 ] && log "蜜罐命中 $N_NEW 个 IP: $(tr '\n' ' ' < "$FILTERED")"

# ---------- 3. 组装 ----------
{
    # 3a. 去掉旧的 HONEYPOT 区块
    sed -n '1,/^# >>> HONEYPOT-BEGIN/p' "$BLOCK_FILE" | sed '$d'
    [ -s "$BLOCK_FILE" ] || true

    echo "# >>> HONEYPOT-BEGIN  (honeypot-sweep.sh 自动维护，勿手工编辑)"
    echo "#     蜜罐命中 = 确定性爬虫判据，TTL $((TTL_SECONDS/86400)) 天。"

    # 3b. 保留未过期的既有条目
    if grep -q '^# >>> HONEYPOT-BEGIN' "$BLOCK_FILE"; then
        sed -n '/^# >>> HONEYPOT-BEGIN/,/^# <<< HONEYPOT-END/p' "$BLOCK_FILE" \
        | grep -E '^[0-9]' | while read -r ip _val _hash until_tok _rest; do
            u="${until_tok#until=}"
            case "$u" in ''|*[!0-9]*) u=0 ;; esac
            [ "$u" -le "$NOW" ] && continue
            echo "$ip 1;   # until=$u"
        done
    fi

    # 3c. 追加本次命中的
    while IFS= read -r ip; do
        [ -z "$ip" ] && continue
        echo "$ip 1;   # until=$(( NOW + TTL_SECONDS )) honeypot"
    done < "$FILTERED"

    echo "# <<< HONEYPOT-END"
    # 3d. 原文件里 HONEYPOT 区块之后的内容（一般没有，保险）
    awk '/^# <<< HONEYPOT-END/{f=1; next} f' "$BLOCK_FILE"
} > "$TMP_OUT"

# 3e. 区块内按 IP 去重
awk '
  /^# >>> HONEYPOT-BEGIN/ { inblk=1 }
  /^# <<< HONEYPOT-END/   { inblk=0 }
  {
    if (inblk && /^[0-9]/) { ip=$1; if (ip in dup) next; dup[ip]=1 }
    print
  }
' "$TMP_OUT" > "$TMP_OUT.d"

rm -f "$FILTERED"

# ---------- 4. 变化才写盘 ----------
if ! cmp -s "$TMP_OUT.d" "$BLOCK_FILE"; then
    cp -a "$BLOCK_FILE" "$RUN_DIR/blocklist.map.hp.bak"
    cat "$TMP_OUT.d" > "$BLOCK_FILE"
    if docker exec "$CT" nginx -t > "$SINK" 2>&1; then
        docker exec "$CT" nginx -s reload > "$SINK" 2>&1
        log "APPLIED: 蜜罐名单已更新"
    else
        cat "$RUN_DIR/blocklist.map.hp.bak" > "$BLOCK_FILE"
        log "ERROR: nginx -t 失败，已回滚"
    fi
fi
exit 0
