FROM ubuntu:22.04
ARG TARGETARCH
ENV DEBIAN_FRONTEND=noninteractive TZ=Asia/Shanghai
RUN --mount=type=cache,id=hnieoj-jammy-apt-${TARGETARCH},target=/var/cache/apt,sharing=locked \
    --mount=type=cache,id=hnieoj-jammy-lists-${TARGETARCH},target=/var/lib/apt/lists,sharing=locked \
    rm -f /etc/apt/apt.conf.d/docker-clean \
 && sed -i 's|//archive.ubuntu.com|//mirrors.ustc.edu.cn|g; s|//security.ubuntu.com|//mirrors.ustc.edu.cn|g' /etc/apt/sources.list \
 && apt-get -o Acquire::Retries=5 update && apt-get -o Acquire::Retries=5 install -y --no-install-recommends \
      nginx \
      php8.1-fpm php8.1-cli php8.1-mysql php8.1-gd php8.1-zip php8.1-mbstring \
      php8.1-xml php8.1-yaml php8.1-curl php8.1-apcu php8.1-memcached php8.1-sqlite3 \
      ca-certificates tzdata curl procps
RUN rm -f /etc/nginx/sites-enabled/default \
 && mkdir -p /run/php /tmp/hustoj_page_cache \
 && chown -R www-data:www-data /tmp/hustoj_page_cache /run/php
COPY docker/conf/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/conf/nginx/conf.d/ /etc/nginx/conf.d/
COPY docker/conf/nginx/snippets/ /etc/nginx/snippets/
COPY docker/conf/php/99-oj.ini /etc/php/8.1/fpm/conf.d/99-oj.ini
COPY docker/conf/php/www.conf /etc/php/8.1/fpm/pool.d/www.conf
COPY web/ /home/judge/src/web/
COPY docker/web/configure.php docker/web/healthcheck.php /opt/oj/
COPY docker/web/entrypoint.sh /entrypoint.sh
COPY docker/operations/crawler-guard.sh docker/operations/honeypot-sweep.sh /opt/oj/operations/
COPY docker/conf/crawler-guard.allow /opt/oj/crawler-guard.allow
RUN mkdir -p /var/lib/oj-nginx && touch /var/lib/oj-nginx/blocklist.map \
 && mkdir -p /home/judge/data /home/judge/src/web/upload \
 && chown www-data:www-data /home/judge/data /home/judge/src/web/upload \
 && chmod +x /entrypoint.sh \
 && nginx -t
EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
