#!/usr/bin/env bash
# 生成每周学院班级同步的 LaunchAgent plist（不会自动启用/部署）。
# 路径由本脚本按当前项目目录解析，不硬编码用户名或绝对路径。
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
LABEL="com.hnieoj.academic-directory-sync"
TEMPLATE="$SCRIPT_DIR/$LABEL.plist"
TARGET="${HOME}/Library/LaunchAgents/$LABEL.plist"
LOG_DIR="$PROJECT_DIR/output/logs"

mkdir -p "$LOG_DIR" "$(dirname "$TARGET")"
sed -e "s|__PROJECT_DIR__|$PROJECT_DIR|g" -e "s|__LOG_DIR__|$LOG_DIR|g" "$TEMPLATE" > "$TARGET"

echo "已生成：$TARGET"
echo "日志目录：$LOG_DIR"
echo
echo "启用（手动执行）：launchctl bootstrap gui/$(id -u) \"$TARGET\""
echo "查看状态：        launchctl print gui/$(id -u)/$LABEL"
echo "停用：            launchctl bootout gui/$(id -u)/$LABEL"
echo
echo "注意：运行前需保持 Chrome 打开并登录教务系统，且开启"
echo "      View > Developer > Allow JavaScript from Apple Events；登录失效时任务会失败且不会写入。"
