#!/bin/bash
# ============================================================
# crawler-guard.sh —— 基于「枚举广度」的爬虫自动封禁
#
# 为什么用「枚举广度」，而不是请求频率或 UA：
#   实测本站 2026-09-10 日志
#       GPTBot      访问了 1510 个不同 user_id
#       ClaudeBot   访问了  234 个
#       Googlebot   访问了   10 个
#       SemrushBot  访问了    7 个
#       正常学生     个位数
#   爬虫与合法爬虫之间隔着 20 倍以上的差距；而且这个判据与「它是谁」无关，
#   所以能自动覆盖将来出现的新爬虫，不需要预知名单 —— 这正是本机制存在的理由。
#   纯频率判据不可用：校园机房 NAT 后多个学生共用一个出口 IP，按频率封会误伤。
#
# 部署：systemd timer 每 30s 执行一次（hnieoj-crawler-guard.timer）
# 在 Web 容器内运行；/var/lib/oj-nginx/crawler-guard.disabled 启用观察模式。
# 白名单：/opt/oj/crawler-guard.allow（每行一个 IP 前缀）
#
# 幂等性：稳态（没有新命中）下不改动 blocklist.map，因此不触发 nginx reload。
# 注：本脚本刻意不使用 /dev/null（改用临时 sink 文件），
#     以便在受限沙箱（writable-roots 不含 /dev）里同样可运行与自测。
# ============================================================
set -uo pipefail

CONF_D=/var/lib/oj-nginx
BLOCK_FILE="$CONF_D/blocklist.map"
ALLOW_FILE=/opt/oj/crawler-guard.allow
RUN_DIR=/var/lib/oj-nginx
DISABLE_FLAG="$RUN_DIR/crawler-guard.disabled"
STATE_LOG=/var/log/hnieoj-crawler-guard.log

TAIL_LINES=${CG_TAIL_LINES:-3000}   # 每次分析的日志尾部行数
MAX_IDS=${CG_MAX_IDS:-50}           # 不同 user_id/problem_id 数阈值
TTL_SECONDS=${CG_TTL:-86400}        # 封禁时长（默认 24h）

SINK=$(mktemp); TMP_TAIL=$(mktemp); TMP_NEW=$(mktemp); TMP_OUT=$(mktemp)
cleanup() { rm -f "$SINK" "$TMP_TAIL" "$TMP_NEW" "$TMP_OUT" "$TMP_NEW.f" "$TMP_OUT.d"; }
trap cleanup EXIT

log() { echo "$(date '+%F %T') $*" >> "$STATE_LOG"; }

OBSERVE=0
[ -e "$DISABLE_FLAG" ] && OBSERVE=1

# ---------- 0. 前置检查 ----------
mkdir -p "$RUN_DIR"
touch "$STATE_LOG" 2> "$SINK" || true
[ -f "$BLOCK_FILE" ] || { log "ERROR: 找不到 $BLOCK_FILE"; exit 1; }

# ---------- 1. 抓取日志尾部 ----------
tail -n "$TAIL_LINES" /var/log/nginx/access.log > "$TMP_TAIL" 2> "$SINK" || {
    log "SKIP: 无法读取容器日志"; exit 0; }

# ---------- 2. 统计「枚举广度」 ----------
#   只统计枚举型入口；同一 (ip, 资源 id) 只计一次。
#   user_id 与 problem_id 分开计数，互不挤占。
awk -v thr="$MAX_IDS" '
{
    split($0, f, " ")
    ip = f[1]
    if (ip == "" || ip ~ /^-$/) next

    n = split($0, q, "\"")
    if (n < 3) next
    req = q[2]

    if (req !~ /(status\.php|userinfo\.php|problemstatus\.php|ranklist\.php|contestrank)/) next

    cnt[ip]++
    if (match(req, /user_id=[A-Za-z0-9_]+/)) {
        k = substr(req, RSTART, RLENGTH)
        if (!((ip SUBSEP "u" SUBSEP k) in seen)) { seen[ip SUBSEP "u" SUBSEP k] = 1; ids[ip]++ }
    }
    if (match(req, /problem_id=[0-9]+/)) {
        k = substr(req, RSTART, RLENGTH)
        if (!((ip SUBSEP "p" SUBSEP k) in seen)) { seen[ip SUBSEP "p" SUBSEP k] = 1; ids[ip]++ }
    }
}
END {
    for (ip in ids)
        if (ids[ip] >= thr) printf "%s %d %d\n", ip, ids[ip], cnt[ip]
}
' "$TMP_TAIL" > "$TMP_NEW"

# ---------- 3. 白名单过滤 ----------
ALLOWED=""
if [ -s "$TMP_NEW" ]; then
    : > "$TMP_NEW.f"
    while read -r ip nids nreq; do
        [ -z "$ip" ] && continue
        hit=0
        if [ -f "$ALLOW_FILE" ]; then
            while IFS= read -r line; do
                case "$line" in ''|'#'*) continue ;; esac
                case "$ip" in "$line"*) hit=1; break ;; esac
            done < "$ALLOW_FILE"
        fi
        if [ "$hit" = "1" ]; then
            ALLOWED="$ALLOWED $ip"
        else
            printf '%s %s %s\n' "$ip" "$nids" "$nreq" >> "$TMP_NEW.f"
        fi
    done < "$TMP_NEW"
    mv "$TMP_NEW.f" "$TMP_NEW"
fi

NOW=$(date +%s)
NEWLIST=$(cat "$TMP_NEW")
[ -n "$ALLOWED" ] && log "ALLOW: 命中阈值但在白名单，放行:$ALLOWED"

if [ -z "$(echo "$NEWLIST" | tr -d '[:space:]')" ]; then
    log "OK: 无新候选（扫描 $(wc -l < "$TMP_TAIL") 行，无 IP 达到 $MAX_IDS 个不同资源 id）"
else
    [ "$OBSERVE" = "1" ] && log "OBSERVE(未封禁): $(echo "$NEWLIST" | tr '\n' ';')"
fi

# ---------- 4. 组装新内容 ----------
#   保留 AUTO 区块之外的内容（手工条目），加上重写的 AUTO 区块
{
    # 4a. 保留 AUTO 区块之外的所有内容：AUTO 之前 + AUTO 之后（后者包含 HONEYPOT 区块）
    #     只丢掉中间那个由本脚本负责重写的 AUTO 区块。
    #     （曾漏掉"之后"的部分，导致 HONEYPOT 区块每轮被抹掉、又被 honeypot-sweep 加回来，
    #       两边反复互相覆盖 —— 既让蜜罐封禁失效，又造成每 30s 一次多余 reload。）
    if grep -q '^# >>> AUTO-BLOCK BEGIN' "$BLOCK_FILE"; then
        sed -n '1,/^# >>> AUTO-BLOCK BEGIN/p' "$BLOCK_FILE" | sed '$d'
    else
        cat "$BLOCK_FILE"
    fi
    echo "# >>> AUTO-BLOCK BEGIN  (由 crawler-guard.sh 自动维护，勿手工编辑)"
    echo "#     <IP> 1;   # until=<epoch> distinct=<枚举广度> req=<请求数>"
    echo "#     过期为 $((TTL_SECONDS/3600)) 小时后自动失效并移除。"

    # 4b. 保留未过期的既有条目（不续期，避免爬虫被永久封）
    if grep -q '^# >>> AUTO-BLOCK BEGIN' "$BLOCK_FILE"; then
        sed -n '/^# >>> AUTO-BLOCK BEGIN/,/^# <<< AUTO-BLOCK END/p' "$BLOCK_FILE" \
        | grep -E '^[0-9]' | while read -r ip _val _hash until_tok _rest; do
            u="${until_tok#until=}"
            case "$u" in ''|*[!0-9]*) u=0 ;; esac
            [ "$u" -le "$NOW" ] && continue
            echo "$ip 1;   # until=$u"
        done
    fi

    # 4c. 追加本次命中的条目（OBSERVE 模式不写）
    if [ -s "$TMP_NEW" ] && [ "$OBSERVE" = "0" ]; then
        while read -r ip nids nreq; do
            [ -z "$ip" ] && continue
            echo "$ip 1;   # until=$(( NOW + TTL_SECONDS )) distinct=$nids req=$nreq"
        done < "$TMP_NEW"
    fi

    echo "# <<< AUTO-BLOCK END"

    # 4c2. AUTO 区块之后的内容（HONEYPOT 区块等）原样保留。
    #      必须在 END 之后追加 —— 放到前面会把它们嵌进 AUTO 区块内部，
    #      并把 AUTO 的结束标记顶掉，导致 honeypot-sweep 读不到自己的区块。
    if grep -q '^# >>> AUTO-BLOCK BEGIN' "$BLOCK_FILE"; then
        awk '/^# <<< AUTO-BLOCK END/{f=1; next} f' "$BLOCK_FILE"
    fi
} > "$TMP_OUT"

# 4d. AUTO 区块内按 IP 去重（保留第一条）
awk '
  /^# >>> AUTO-BLOCK BEGIN/ { inblk=1 }
  /^# <<< AUTO-BLOCK END/   { inblk=0 }
  {
    if (inblk && /^[0-9]/) {
        ip=$1
        if (ip in dup) next
        dup[ip]=1
    }
    print
  }
' "$TMP_OUT" > "$TMP_OUT.d"

# ---------- 5. 有变化才写盘 + reload（写前备份，便于回滚）----------
if ! cmp -s "$TMP_OUT.d" "$BLOCK_FILE"; then
    cp -a "$BLOCK_FILE" "$RUN_DIR/blocklist.map.bak"
    cat "$TMP_OUT.d" > "$BLOCK_FILE"    # 原地覆盖；目录挂载下 inode 已不重要，保持习惯
    if nginx -t > "$SINK" 2>&1; then
        nginx -s reload > "$SINK" 2>&1
        N=$(grep -cE '^[0-9]' "$BLOCK_FILE" 2> "$SINK" || echo 0)
        SUF=""
        [ "$OBSERVE" = "1" ] && SUF=" (OBSERVE: 只记录未封禁)"
        log "APPLIED: 黑名单现有 $N 条$SUF"
    else
        cat "$RUN_DIR/blocklist.map.bak" > "$BLOCK_FILE"
        log "ERROR: nginx -t 失败，已回滚 blocklist.map，放弃本次写入"
    fi
fi
exit 0
