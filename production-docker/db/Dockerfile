# HnieOJ db 容器：用 ubuntu:22.04 自建 mariadb 10.6（与宿主机完全同版本）
# 不走 Docker Hub 拉取（国内 registry 镜像太慢），全部由 apt 中科大源提供
FROM ubuntu:22.04
ENV DEBIAN_FRONTEND=noninteractive TZ=Asia/Shanghai
RUN sed -i 's|//archive.ubuntu.com|//mirrors.ustc.edu.cn|g; s|//security.ubuntu.com|//mirrors.ustc.edu.cn|g' /etc/apt/sources.list \
 && apt-get update && apt-get install -y --no-install-recommends \
      mariadb-server mariadb-client ca-certificates tzdata procps \
 && rm -rf /var/lib/apt/lists/* /var/lib/mysql/* \
 && mkdir -p /var/lib/mysql /run/mysqld && chown -R mysql:mysql /var/lib/mysql /run/mysqld
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
EXPOSE 3306
ENTRYPOINT ["/entrypoint.sh"]
