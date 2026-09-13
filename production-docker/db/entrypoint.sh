#!/bin/bash
# HnieOJ db 容器入口（自建 mariadb 10.6）
# 首次启动：初始化数据目录 -> 建库/建用户 -> 交给正常启动流程
set -e
DATADIR=/var/lib/mysql
SOCK=/run/mysqld/mysqld.sock
mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld

if [ ! -d "$DATADIR/mysql" ]; then
  echo "[db] 首次初始化：mariadb-install-db"
  mariadb-install-db --user=mysql --datadir="$DATADIR" --auth-root-authentication-method=normal >/dev/null

  echo "[db] 临时启动以创建库/用户/授权"
  mariadbd --user=mysql --datadir="$DATADIR" --skip-networking --socket="$SOCK" --skip-grant-tables=0 &
  for i in $(seq 1 60); do mariadb-admin --socket="$SOCK" ping >/dev/null 2>&1 && break; sleep 1; done

  echo "[db] 创建 jol 库与 ${DB_USER:-hustoj} 用户"
  mariadb --socket="$SOCK" -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME:-jol}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS '${DB_USER:-hustoj}'@'%' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER:-hustoj}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME:-jol}\`.* TO '${DB_USER:-hustoj}'@'%';
GRANT ALL PRIVILEGES ON \`${DB_NAME:-jol}\`.* TO '${DB_USER:-hustoj}'@'localhost';
ALTER USER 'root'@'localhost' IDENTIFIED BY '${DB_ROOT_PASSWORD}';
FLUSH PRIVILEGES;
SQL
  mariadb-admin --socket="$SOCK" -uroot -p"${DB_ROOT_PASSWORD}" shutdown || pkill -f mariadbd || true
  sleep 2
  echo "[db] 初始化完成"
fi

echo "[db] 启动 mariadbd（buffer pool ${DB_BUFFER_POOL:-4G}）"
exec mariadbd --user=mysql --datadir="$DATADIR" --socket="$SOCK" \
  --bind-address=0.0.0.0 --port=3306 \
  --innodb-buffer-pool-size="${DB_BUFFER_POOL:-4G}" \
  --slow-query-log=1 --long-query-time=1 --log-queries-not-using-indexes=0 \
  --max-connections=200 \
  --character-set-server=utf8mb4 --collation-server=utf8mb4_general_ci
