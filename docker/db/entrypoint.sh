#!/bin/bash
set -euo pipefail
DATADIR=/var/lib/mysql
SOCK=/run/mysqld/mysqld.sock
mkdir -p /run/mysqld /run/oj-secrets
chown mysql:mysql /run/mysqld
umask 077
# Reuse the same credentials after every restart. Never print DB passwords.
for name in db-password root-password admin-password; do
  if [ ! -s "/run/oj-secrets/$name" ]; then
    if [ -d "$DATADIR/mysql" ]; then
      echo "[db] Existing database but credentials are missing; refusing to reset." >&2
      exit 1
    fi
    openssl rand -hex 24 > "/run/oj-secrets/$name"
  fi
done
DB_PASS=$(< /run/oj-secrets/db-password)
ROOT_PASS=$(< /run/oj-secrets/root-password)
ADMIN_PASS=$(< /run/oj-secrets/admin-password)
# Web only needs its application password; root/admin stay readable by root only.
chown root:www-data /run/oj-secrets/db-password
chmod 640 /run/oj-secrets/db-password
if [ ! -d "$DATADIR/mysql" ]; then
  mariadb-install-db --user=mysql --datadir="$DATADIR" --auth-root-authentication-method=normal >/dev/null
  mariadbd --user=mysql --datadir="$DATADIR" --skip-networking --socket="$SOCK" &
  init_pid=$!
  trap 'kill -TERM "$init_pid" 2>/dev/null || true' EXIT
  ready=0
  for i in $(seq 1 60); do
    if mariadb-admin --socket="$SOCK" ping --silent; then ready=1; break; fi
    sleep 1
  done
  [ "$ready" = 1 ]
  mariadb --socket="$SOCK" -uroot <<SQL
CREATE DATABASE jol CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'hustoj'@'%' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON jol.* TO 'hustoj'@'%';
SQL
  mariadb --socket="$SOCK" -uroot jol < /opt/oj/schema.sql
  # knowledge-graph.sql maps nodes to `category` labels that nothing else seeds,
  # so a fresh volume needs them first or knowledge_node_category stays empty.
  # Both files carry Chinese names: force the client to utf8mb4 so the import
  # cannot be decoded with the latin1 default.
  mariadb --socket="$SOCK" --default-character-set=utf8mb4 -uroot jol < /opt/oj/category-seed.sql
  mariadb --socket="$SOCK" --default-character-set=utf8mb4 -uroot jol < /opt/oj/knowledge-graph.sql
  mariadb --socket="$SOCK" --default-character-set=utf8mb4 -uroot jol < /opt/oj/solutions-coins.sql
  mariadb --socket="$SOCK" --default-character-set=utf8mb4 -uroot jol < /opt/oj/community-hunts.sql
  mariadb --socket="$SOCK" --default-character-set=utf8mb4 -uroot jol < /opt/oj/acm-lab.sql
  mariadb --socket="$SOCK" --default-character-set=utf8mb4 -uroot jol < /opt/oj/adventure-route.sql
  # Use the existing application's salted password format, not a new auth scheme.
  SALT=$(openssl rand -hex 2)
  mariadb --socket="$SOCK" -uroot jol <<SQL
INSERT INTO users (user_id,nick,password,reg_time,accesstime,register_num) VALUES ('admin','Administrator',TO_BASE64(CONCAT(UNHEX(SHA1(CONCAT(MD5('$ADMIN_PASS'),'$SALT'))),'$SALT')),NOW(),NOW(),1);
INSERT INTO privilege (user_id,rightstr,defunct) VALUES ('admin','administrator','N');
ALTER TABLE problem AUTO_INCREMENT=1000;
ALTER TABLE contest AUTO_INCREMENT=1000;
ALTER USER 'root'@'localhost' IDENTIFIED BY '$ROOT_PASS';
SQL
  MYSQL_PWD="$ROOT_PASS" mariadb-admin --socket="$SOCK" -uroot shutdown
  wait "$init_pid"
  trap - EXIT
  touch "$DATADIR/.oj-initialized"
  echo "[db] Fresh installation initialized. Read admin password with the command in README."
elif [ ! -f "$DATADIR/.oj-initialized" ]; then
  echo "[db] Incomplete initialization or imported database: refusing automatic changes. See docker/MIGRATION.md." >&2
  exit 1
fi
exec mariadbd --user=mysql --datadir="$DATADIR" --socket="$SOCK" \
  --bind-address=0.0.0.0 --port=3306 --innodb-buffer-pool-size="${DB_BUFFER_POOL:-512M}" \
  --slow-query-log=1 --long-query-time=1 --max-connections=200 \
  --character-set-server=utf8mb4 --collation-server=utf8mb4_general_ci
