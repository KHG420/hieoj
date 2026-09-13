#!/bin/bash
# HnieOJ web 容器入口：php-fpm + nginx 双进程
set -e
echo "[web] php: $(php -v | head -1)"
echo "[web] DB_HOST=${DB_HOST:-<未设置>} DB_PORT=${DB_PORT:-3306} DB_NAME=${DB_NAME:-jol}"
# clear_env=yes 时 php-fpm 不继承环境变量，靠 pool 里的 env[...] 显式传递（见 conf/php/www.conf）
mkdir -p /tmp/hustoj_page_cache && chown -R www-data:www-data /tmp/hustoj_page_cache 2>/dev/null || true
php-fpm8.1 --nodaemonize &
PHP_PID=$!
nginx -g 'daemon off;' &
NGX_PID=$!
trap 'kill -TERM $PHP_PID $NGX_PID 2>/dev/null || true' TERM INT
# 任一进程退出即退出容器，交给 compose 的 restart 策略
wait -n
