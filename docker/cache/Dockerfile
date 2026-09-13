FROM ubuntu:22.04
ARG TARGETARCH
ENV DEBIAN_FRONTEND=noninteractive
RUN --mount=type=cache,id=hnieoj-jammy-apt-${TARGETARCH},target=/var/cache/apt,sharing=locked \
    --mount=type=cache,id=hnieoj-jammy-lists-${TARGETARCH},target=/var/lib/apt/lists,sharing=locked \
    rm -f /etc/apt/apt.conf.d/docker-clean \
 && sed -i 's|//archive.ubuntu.com|//mirrors.ustc.edu.cn|g; s|//security.ubuntu.com|//mirrors.ustc.edu.cn|g' /etc/apt/sources.list \
 && apt-get -o Acquire::Retries=5 update && apt-get -o Acquire::Retries=5 install -y --no-install-recommends memcached ca-certificates
EXPOSE 11211
ENTRYPOINT ["memcached","-u","memcache","-m","256","-l","0.0.0.0","-c","1024"]
