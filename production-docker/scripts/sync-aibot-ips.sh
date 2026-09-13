#!/bin/bash
# ============================================================
# 同步 OpenAI / Anthropic 官方爬虫 IP 段到 nginx geo 块
#
#   数据源：
#     https://openai.com/gptbot.json            (GPTBot)
#     https://claude.com/crawling/bots.json     (ClaudeBot/Claude-User/Claude-SearchBot)
#
#   !! 重要 —— Docker 单文件 bind mount 绑定的是 inode !!
#   /opt/hnieoj-docker/conf/** 下的每个文件都是单文件 bind mount 进容器的。
#   若用编辑器 / sed -i / mv 之类「新建再改名」的方式写，inode 会变，
#   容器仍持有旧 inode，表现为「配置改了、reload 也成功，但完全不生效」
#   （2026-09-10 为此排查了很久）。
#   本脚本一律用 `cat 临时文件 > 目标文件` 原地覆盖，保持 inode 不变。
# ============================================================
set -euo pipefail

CONF=/opt/hnieoj-docker/conf/nginx/00-oj-http.conf
CT=hnieoj-web
CURL="curl -fsS -m 20"

TMP=$(mktemp); TMP2=$(mktemp)
trap 'rm -f "$TMP" "$TMP2"' EXIT

extract() { grep -oE '"ipv[46]Prefix": *"[^"]+"' | sed 's/.*"\([^"]*\)"$/\1/' | sort -u; }

OPENAI=$($CURL https://openai.com/gptbot.json       | extract)
ANTHROPIC=$($CURL https://claude.com/crawling/bots.json | extract)

[ -n "$OPENAI" ]    || { echo "ERROR: OpenAI 列表为空，中止（不覆盖现有规则）" >&2; exit 1; }
[ -n "$ANTHROPIC" ] || { echo "ERROR: Anthropic 列表为空，中止（不覆盖现有规则）" >&2; exit 1; }

STAMP=$(date -u +%F)
{
  echo "geo \$oj_bad_ip {"
  echo "    default 0;"
  echo
  echo "    # ----- OpenAI GPTBot (openai.com/gptbot.json, 同步于 ${STAMP}) -----"
  for ip in $OPENAI; do printf "    %-22s 1;\n" "$ip"; done
  echo
  echo "    # ----- Anthropic (claude.com/crawling/bots.json, 同步于 ${STAMP}) -----"
  for ip in $ANTHROPIC; do printf "    %-22s 1;\n" "$ip"; done
  echo "}"
} > "$TMP"

# 用新 geo 块替换旧的整个 geo $oj_bad_ip { ... } 块
awk -v newfile="$TMP" '
  /^geo \$oj_bad_ip \{/ {
      while ((getline line < newfile) > 0) print line
      close(newfile); skip = 1; next
  }
  skip && /^\}/ { skip = 0; next }
  skip { next }
  { print }
' "$CONF" > "$TMP2"

# 原地覆盖：保持 inode（见文件头说明）
cat "$TMP2" > "$CONF"

docker exec "$CT" nginx -t
docker exec "$CT" nginx -s reload
echo "OK: 已同步 OpenAI=$(echo "$OPENAI" | wc -l) Anthropic=$(echo "$ANTHROPIC" | wc -l) 条前缀并 reload"
