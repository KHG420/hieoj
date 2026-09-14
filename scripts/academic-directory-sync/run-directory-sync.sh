#!/usr/bin/env bash
# 定期同步学院班级目录：
#   1) 用 macOS 现有已登录 Chrome 采集快照（失败不覆盖旧快照）
#   2) 采集成功后才在项目的 web 容器内运行 importer（默认 dry-run，--apply 才写）
#
# 宿主不需要 PHP；importer 通过 stdin 接收 JSON，不把宿主路径传进容器。
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="${ACADEMIC_SYNC_COMPOSE:-$PROJECT_DIR/compose.yaml}"
SERVICE="${ACADEMIC_SYNC_SERVICE:-web}"
SNAPSHOT="${ACADEMIC_SYNC_SNAPSHOT:-$PROJECT_DIR/output/academic-directory.json}"
LOG_DIR="${ACADEMIC_SYNC_LOG_DIR:-$PROJECT_DIR/output/logs}"
PYTHON_BIN="${ACADEMIC_SYNC_PYTHON:-python3}"
CONTAINER_IMPORTER="/home/judge/src/web/cli/academic_directory_import.php"

APPLY=0
for arg in "$@"; do
  case "$arg" in
    --apply) APPLY=1 ;;
    --dry-run) APPLY=0 ;;
    -h|--help)
      echo "用法: $0 [--dry-run|--apply]"
      echo "默认 dry-run；--apply 才会写入数据库。"
      exit 0
      ;;
    *)
      echo "未知参数: $arg" >&2
      exit 2
      ;;
  esac
done

mkdir -p "$LOG_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
LOG_FILE="$LOG_DIR/academic-directory-sync-$STAMP.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "[$(date '+%F %T')] 开始同步（日志：${LOG_FILE}，模式：$([ "$APPLY" -eq 1 ] && echo apply || echo dry-run)）"

# 1) 采集：失败会保留旧快照并退出，不会进入导入
if ! "$PYTHON_BIN" "$SCRIPT_DIR/collect_directory.py" --out "$SNAPSHOT"; then
  echo "[$(date '+%F %T')] 采集失败：保留旧快照，未执行导入" >&2
  exit 1
fi
if [ ! -s "$SNAPSHOT" ]; then
  echo "[$(date '+%F %T')] 快照为空：未执行导入" >&2
  exit 1
fi

# 2) 确认容器内已有 importer（镜像需已 COPY web/）
if ! docker compose -f "$COMPOSE_FILE" exec -T "$SERVICE" test -f "$CONTAINER_IMPORTER"; then
  echo "[$(date '+%F %T')] 容器内缺少 ${CONTAINER_IMPORTER}，请先重新构建 web 镜像（docker compose build web）" >&2
  exit 1
fi

# 3) 导入：默认 dry-run，--apply 才写；JSON 从 stdin 传入
IMPORT_ARGS=""
if [ "$APPLY" -eq 1 ]; then
  IMPORT_ARGS="--apply"
fi
if ! docker compose -f "$COMPOSE_FILE" exec -T "$SERVICE" php "$CONTAINER_IMPORTER" $IMPORT_ARGS < "$SNAPSHOT"; then
  echo "[$(date '+%F %T')] 导入失败" >&2
  exit 1
fi

if [ "$APPLY" -eq 1 ]; then
  echo "[$(date '+%F %T')] 同步完成（已写入数据库）"
else
  echo "[$(date '+%F %T')] dry-run 完成（未写入；使用 --apply 才会写入）"
fi
