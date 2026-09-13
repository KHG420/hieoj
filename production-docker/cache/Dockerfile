# HnieOJ cache 容器（注：当前 db_info.inc.php 里 $OJ_MEMCACHE=false，OJ 尚未启用它；与宿主现状一致保留）
FROM ubuntu:22.04
ENV DEBIAN_FRONTEND=noninteractive
RUN sed -i 's|//archive.ubuntu.com|//mirrors.ustc.edu.cn|g; s|//security.ubuntu.com|//mirrors.ustc.edu.cn|g' /etc/apt/sources.list \
 && apt-get update && apt-get install -y --no-install-recommends memcached ca-certificates \
 && rm -rf /var/lib/apt/lists/*
EXPOSE 11211
ENTRYPOINT ["memcached","-u","memcache","-m","256","-l","0.0.0.0","-c","1024"]
