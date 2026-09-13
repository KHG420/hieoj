FROM ubuntu:22.04
ARG TARGETARCH
ENV DEBIAN_FRONTEND=noninteractive TZ=Asia/Shanghai
RUN test "$TARGETARCH" = amd64 || { echo 'The judge requires native Linux x86_64; ARM64 ptrace support is not validated.' >&2; exit 1; }
RUN --mount=type=cache,id=hnieoj-jammy-apt-${TARGETARCH},target=/var/cache/apt,sharing=locked \
    --mount=type=cache,id=hnieoj-jammy-lists-${TARGETARCH},target=/var/lib/apt/lists,sharing=locked \
    rm -f /etc/apt/apt.conf.d/docker-clean \
 && sed -i 's|//archive.ubuntu.com|//mirrors.ustc.edu.cn|g; s|//security.ubuntu.com|//mirrors.ustc.edu.cn|g' /etc/apt/sources.list \
 && apt-get -o Acquire::Retries=5 update \
 && apt-get -o Acquire::Retries=5 install -y --no-install-recommends \
    build-essential libmysqlclient-dev ca-certificates tzdata procps docker.io \
    python3 openjdk-17-jdk-headless fp-compiler clang busybox dos2unix \
    similarity-tester locales-all
RUN groupadd -g 1536 judge && useradd -m -u 1536 -g 1536 judge \
 && ln -sf /usr/bin/python3 /usr/bin/python \
 && ln -sf /usr/bin/sim_c++ /usr/bin/sim_cc
COPY core/ /opt/oj/core/
RUN make -C /opt/oj/core/judged && make -C /opt/oj/core/judge_client \
 && install /opt/oj/core/judged/judged /opt/oj/core/judge_client/judge_client /usr/bin/ \
 && install /opt/oj/core/sim/sim.sh /usr/bin/sim.sh \
 && rm -rf /opt/oj/core
COPY etc/judge.conf.example /opt/oj/judge.conf.example
COPY install/java0.policy /opt/oj/java0.policy
COPY docker/judge/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
WORKDIR /home/judge
ENV LANG=C.UTF-8
# CMD, not ENTRYPOINT: a per-submission sandbox runs judge_client directly.
CMD ["/entrypoint.sh"]
