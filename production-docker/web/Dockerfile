# HnieOJ web 容器：nginx + php8.1-fpm（与现网一致），只提供运行环境，源码走 bind mount
FROM ubuntu:22.04
ENV DEBIAN_FRONTEND=noninteractive TZ=Asia/Shanghai

RUN sed -i 's|//archive.ubuntu.com|//mirrors.ustc.edu.cn|g; s|//security.ubuntu.com|//mirrors.ustc.edu.cn|g' /etc/apt/sources.list \
 && apt-get update && apt-get install -y --no-install-recommends \
      nginx \
      php8.1-fpm php8.1-cli php8.1-mysql php8.1-gd php8.1-zip php8.1-mbstring \
      php8.1-xml php8.1-yaml php8.1-curl php8.1-apcu php8.1-memcached php8.1-sqlite3 \
      ca-certificates tzdata curl procps \
 && rm -rf /var/lib/apt/lists/*

# 默认站点会盖住我们的 server 块，删掉；页面缓存目录预建
RUN rm -f /etc/nginx/sites-enabled/default \
 && mkdir -p /run/php /tmp/hustoj_page_cache \
 && chown -R www-data:www-data /tmp/hustoj_page_cache /run/php

# 配置打进镜像（镜像自包含）；运行时由 compose bind mount 覆盖，
# 改配置只需 docker compose up -d，无需重建镜像（见 compose.yaml 的 web.volumes）
COPY conf/nginx/nginx.conf   /etc/nginx/nginx.conf
# 2026-09-10：conf/nginx 重组为 conf.d/ + snippets/ 两个目录（见 compose.yaml）。
# 运行时以目录 bind mount 为准，这里只保证镜像自包含、可独立构建。
# 注：snippets/ 里已包含镜像自带的 fastcgi-php.conf 与 snakeoil.conf 副本。
COPY conf/nginx/conf.d/      /etc/nginx/conf.d/
COPY conf/nginx/snippets/    /etc/nginx/snippets/
COPY conf/php/99-oj.ini /etc/php/8.1/fpm/conf.d/99-oj.ini
COPY conf/php/www.conf  /etc/php/8.1/fpm/pool.d/www.conf
COPY web/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# 2026-09-10 性能审计：nginx.conf 改为自带（worker_connections 4096 + multi_accept on，
# Ubuntu 默认 768 且 multi_accept 关闭）。构建期做一次配置校验，配置有误则构建直接失败。
RUN nginx -t

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
