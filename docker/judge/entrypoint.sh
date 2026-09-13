#!/bin/bash
set -euo pipefail
umask 077
mkdir -p /home/judge/etc /home/judge/log /home/judge/run0 /home/judge/run1
cp /opt/oj/java0.policy /home/judge/etc/java0.policy
cp /opt/oj/judge.conf.example /home/judge/etc/judge.conf
password=$(< /run/oj-secrets/db-password)
sed -i \
  -e 's/^OJ_HOST_NAME=.*/OJ_HOST_NAME=db/' \
  -e "s/^OJ_PASSWORD=.*/OJ_PASSWORD=$password/" \
  -e 's/^OJ_RUNNING=.*/OJ_RUNNING=2/' \
  -e 's/^OJ_INTERNAL_CLIENT=.*/OJ_INTERNAL_CLIENT=1/' \
  -e 's/^OJ_LANG_SET=.*/OJ_LANG_SET=0,1,2,3,6,13,14/' \
  -e 's/^OJ_UDP_ENABLE=.*/OJ_UDP_ENABLE=0/' \
  /home/judge/etc/judge.conf
chmod 700 /home/judge/etc
chown judge:judge /home/judge/run0 /home/judge/run1
rm -f /home/judge/etc/judge.pid
# foreground mode keeps PID 1 tied to judged; sandbox containers get no Docker socket.
exec /usr/bin/judged /home/judge foreground
