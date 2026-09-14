#!/bin/sh
set -eu
export MYSQL_PWD="$(cat /run/oj-secrets/root-password)"
name=editorial_migration_test_$$
mariadb -uroot -e "CREATE DATABASE $name CHARACTER SET utf8mb4"
trap 'mariadb -uroot -e "DROP DATABASE $name"' EXIT
mariadb -uroot "$name" < /opt/oj/schema.sql
mariadb -uroot "$name" -e "INSERT INTO solution(user_id,problem_id,result) VALUES('historical',1000,4),('future',1000,0)"
mariadb -uroot "$name" < /opt/oj/solutions-coins.sql
[ "$(mariadb -uroot -N "$name" -e 'SELECT COUNT(*) FROM coin_wallet')" = 0 ]
mariadb -uroot "$name" -e "INSERT INTO solution(user_id,problem_id,result) VALUES('historical',1000,4); UPDATE solution SET result=4 WHERE user_id='future'"
[ "$(mariadb -uroot -N "$name" -e "SELECT balance FROM coin_wallet WHERE user_id='future'")" = 2 ]
mariadb -uroot "$name" < /opt/oj/solutions-coins.sql
mariadb -uroot "$name" -e "UPDATE solution SET result=0 WHERE user_id='future'; UPDATE solution SET result=4 WHERE user_id='future'"
[ "$(mariadb -uroot -N "$name" -e 'SELECT SUM(balance) FROM coin_wallet')" = 2 ]
[ "$(mariadb -uroot -N "$name" -e 'SELECT COUNT(*) FROM coin_ledger')" = 1 ]
echo 'PASS: fresh migration, historical exclusion, future reward, idempotent reapply and rejudge.'
