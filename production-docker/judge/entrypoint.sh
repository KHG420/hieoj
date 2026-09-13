#!/bin/bash
# HnieOJ judge 容器入口
# 关键：judged 内部会 daemon_init()（fork 后父进程退出），所以不能 exec 它，
#       否则容器主进程立刻结束 -> 容器退出 -> 无限重启。这里启动后循环守护。
set -e
export HOME=/home/judge
umask 077
echo "[judge] uid=$(id -u) host=$(hostname)"
echo "[judge] docker sock : $(ls -l /var/run/docker.sock 2>/dev/null | awk '{print $1,$3,$4}' || echo '缺失!')"
echo "[judge] 宿主 dockerd: $(docker version --format '{{.Server.Version}}' 2>&1 | head -1)"
echo "[judge] 沙箱镜像   : $(docker images hustoj --format '{{.Repository}}:{{.Tag}} ({{.Size}})' 2>&1 | head -1)"
echo "[judge] judge.conf : DB=$(grep -E '^OJ_HOST_NAME' /home/judge/etc/judge.conf | cut -d= -f2) PORT=$(grep -E '^OJ_PORT_NUMBER' /home/judge/etc/judge.conf | cut -d= -f2) USE_DOCKER=$(grep -E '^OJ_USE_DOCKER' /home/judge/etc/judge.conf | cut -d= -f2) RUNNING=$(grep -E '^OJ_RUNNING' /home/judge/etc/judge.conf | cut -d= -f2)"
echo "[judge] judging 数据: $(ls /home/judge/data 2>/dev/null | wc -l) 个题目目录"

rm -f /home/judge/etc/judge.pid
/usr/bin/judged || true
sleep 3

if pgrep -x judged >/dev/null 2>&1; then
  echo "[judge] judged 已启动 pid=$(pgrep -x judged | tr '\n' ' ')，进入守护"
  while pgrep -x judged >/dev/null 2>&1; do sleep 5; done
  echo "[judge] !! judged 进程消失，退出容器以触发重启"
  exit 1
fi
echo "[judge] !! judged 未启动成功"
exit 1
