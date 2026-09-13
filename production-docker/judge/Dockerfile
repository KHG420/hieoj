# HnieOJ judge 容器：只提供 judged/judge_client 的运行时 + docker CLI
# judged/judge_client 二进制由宿主的 /home/judge/src/core 提供（bind mount），不在镜像内重编译
# 判题沙箱是宿主 dockerd 上的 hustoj:latest，由 judge_client 通过挂进来的 docker.sock 拉起
FROM ubuntu:22.04
ENV DEBIAN_FRONTEND=noninteractive TZ=Asia/Shanghai

RUN sed -i 's|//archive.ubuntu.com|//mirrors.ustc.edu.cn|g; s|//security.ubuntu.com|//mirrors.ustc.edu.cn|g' /etc/apt/sources.list \
 && apt-get update && apt-get install -y --no-install-recommends \
      libmysqlclient21 libstdc++6 ca-certificates tzdata procps \
      docker.io python3 \
 && rm -rf /var/lib/apt/lists/*

# 必须与宿主机 judge 用户对齐（host: uid=1536 gid=1536），否则 /home/judge 下权限错乱
RUN groupadd -g 1536 judge && useradd -m -u 1536 -g 1536 judge

# 判题二进制由宿主 /usr/bin(/judged,/judge_client) 静态链接提供，通过 compose 挂载进来

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
