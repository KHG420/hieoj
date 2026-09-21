FROM ubuntu:22.04
ARG TARGETARCH
ENV DEBIAN_FRONTEND=noninteractive TZ=Asia/Shanghai
RUN --mount=type=cache,id=hnieoj-jammy-apt-${TARGETARCH},target=/var/cache/apt,sharing=locked \
    --mount=type=cache,id=hnieoj-jammy-lists-${TARGETARCH},target=/var/lib/apt/lists,sharing=locked \
    rm -f /etc/apt/apt.conf.d/docker-clean \
 && sed -i 's|//archive.ubuntu.com|//mirrors.ustc.edu.cn|g; s|//security.ubuntu.com|//mirrors.ustc.edu.cn|g' /etc/apt/sources.list \
 && apt-get -o Acquire::Retries=5 update && apt-get -o Acquire::Retries=5 install -y --no-install-recommends \
    mariadb-server mariadb-client ca-certificates tzdata procps openssl \
 && rm -rf /var/lib/mysql/* \
 && mkdir -p /var/lib/mysql /run/mysqld && chown -R mysql:mysql /var/lib/mysql /run/mysqld
COPY docker/db/schema.sql docker/db/knowledge-graph.sql docker/db/solutions-coins.sql docker/db/editorial-markdown.sql docker/db/adventure-coins.sql docker/db/community-hunts.sql docker/db/acm-lab.sql /opt/oj/
COPY docker/db/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
EXPOSE 3306
ENTRYPOINT ["/entrypoint.sh"]
