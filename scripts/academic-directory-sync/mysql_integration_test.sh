#!/usr/bin/env bash
# 学院班级目录的 MySQL/MariaDB 集成测试入口。
#
# 只使用任务专属、一次性的容器与网络（oj-academic-test-*），
# 不挂载任何现有 volume / 凭证，也不使用 hnieoj-unified-db 的默认初始化 entrypoint。
# 结束后销毁自己创建的容器、网络与临时目录。
#
# 用法：
#   scripts/academic-directory-sync/mysql_integration_test.sh
#   ACADEMIC_ITEST_SNAPSHOT=/path/live-directory.json \
#     scripts/academic-directory-sync/mysql_integration_test.sh   # 追加完整快照幂等场景
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
WEB_IMAGE="${ACADEMIC_ITEST_WEB_IMAGE:-hnieoj-unified-web}"
DB_IMAGE="${ACADEMIC_ITEST_DB_IMAGE:-hnieoj-unified-db}"
FULL_SNAPSHOT="${ACADEMIC_ITEST_SNAPSHOT:-}"

RUN_ID="$$"
DB="oj-academic-test-db-$RUN_ID"
NET="oj-academic-test-net-$RUN_ID"
TMP_DIR="$PROJECT_DIR/output/itest-$RUN_ID"

cleanup() {
  docker rm -f "$DB" >/dev/null 2>&1 || true
  docker network rm "$NET" >/dev/null 2>&1 || true
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

mkdir -p "$TMP_DIR"

echo "[itest] 创建任务专属网络 $NET"
docker network create "$NET" >/dev/null

echo "[itest] 启动一次性 MariaDB ${DB}（独立数据目录，无 volume/凭证）"
docker run -d --name "$DB" --network "$NET" \
  --entrypoint /bin/bash "$DB_IMAGE" -c '
    set -e
    DATADIR=/tmp/itestdb
    mkdir -p "$DATADIR" /run/mysqld
    chown -R mysql:mysql "$DATADIR" /run/mysqld
    mariadb-install-db --user=mysql --datadir="$DATADIR" --auth-root-authentication-method=normal >/dev/null 2>&1
    exec mariadbd --user=mysql --datadir="$DATADIR" --bind-address=0.0.0.0 --port=3306 --skip-grant-tables
  ' >/dev/null

echo "[itest] 等待数据库就绪"
ready=0
for _ in $(seq 1 60); do
  if docker exec "$DB" mariadb -uroot -h127.0.0.1 -e 'SELECT 1' >/dev/null 2>&1; then
    ready=1
    break
  fi
  sleep 1
done
if [ "$ready" != 1 ]; then
  echo "[itest] 数据库未就绪" >&2
  docker logs "$DB" >&2 || true
  exit 1
fi
docker exec "$DB" mariadb -uroot -h127.0.0.1 \
  -e 'CREATE DATABASE academic_test DEFAULT CHARACTER SET utf8mb4' >/dev/null

# 只读测试配置：指向一次性容器，不写入仓库内的 db_info.inc.php。
cat > "$TMP_DIR/db_info.inc.php" <<PHP
<?php
\$dbh = new PDO('mysql:host=$DB;dbname=academic_test;charset=utf8mb4', 'root', '');
\$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require __DIR__ . '/pdo.php';
PHP

run_php_itest() {
  docker run --rm --network "$NET" "$@" \
    -v "$PROJECT_DIR:/work:ro" \
    -v "$TMP_DIR:/itest:ro" \
    -w /work \
    --entrypoint sh "$WEB_IMAGE" -c '
      set -e
      rm -rf /tmp/web
      cp -a /work /tmp/web
      cp /itest/db_info.inc.php /tmp/web/web/include/db_info.inc.php
      cd /tmp/web
      exec php web/tests/academic_directory_mysql_test.php
    '
}

if [ -n "$FULL_SNAPSHOT" ]; then
  if [ ! -r "$FULL_SNAPSHOT" ]; then
    echo "[itest] 完整快照不可读：$FULL_SNAPSHOT" >&2
    exit 2
  fi
  echo "[itest] 运行 PHP 集成测试（含完整快照幂等）"
  run_php_itest \
    -v "$FULL_SNAPSHOT:/full-snapshot.json:ro" \
    -e ACADEMIC_ITEST_SNAPSHOT=/full-snapshot.json
else
  echo "[itest] 运行 PHP 集成测试"
  run_php_itest
fi
