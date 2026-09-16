# FRP 外部健康检查与有界自动恢复

统一 FRP 版本：**0.69.1**（公网 `frps` 与应用服务器 `frpc` 必须一致）。
相关文件（本仓库 `docker/operations/`）：

| 文件 | 部署位置 | 模式 |
|---|---|---|
| `frp-public-probe.sh` | 公网主机 `/usr/local/sbin/hnieoj-frp-public-probe` | 0755 |
| `frp-healthcheck.sh` | 应用服务器 `/usr/local/sbin/hnieoj-frp-healthcheck` | 0755 |
| `frp-healthcheck.service` | 应用服务器 `/etc/systemd/system/frp-healthcheck.service` | 0644 |
| `frp-healthcheck.timer` | 应用服务器 `/etc/systemd/system/frp-healthcheck.timer` | 0644 |
| `frps.service` | 公网主机 `/etc/systemd/system/frps.service` | 0644 |

## 为什么这样设计

- **进程活着 ≠ 公网可用。** 实测出现过 frpc 进程存活、外部连接却超时的情况，
  因此判据取自公网视角，而不是进程状态。
- **应用服务器自请求公网域名的观测不代表外部视角。** 实测应用服务器源发请求
  公网域名返回 404，而公网主机返回 200；该 404 只是应用服务器侧的观测，
  无法据此判断真实外部用户是否可达，所以不在应用服务器上直接探公网 URL，
  而是 SSH 到公网主机（42.194.237.240）由本地探针发起请求。
- **恢复必须有界。** 只有「本机应用健康」且「外部探针连续 3 次确认失败」
  （每 60s 一次）才 `systemctl restart frpc`，且距上次重启尝试不足 600s 时
  受冷却期抑制，避免反复重启扩大故障。健康检查会清零连续失败计数，
  但不影响冷却期。本机不健康或 SSH 结论未知都清零计数并告警，不重启。
  重启前先把冷却时间戳原子落盘，落盘失败则 fail-closed 不重启。
  SSH 一律用 `-nT`：`-n` 把 stdin 指向 `/dev/null`、`-T` 不分配 pty；否则
  在交互式 root 手动执行时，ssh 会因读取终端收到 SIGTTIN 而进入 stopped
  状态，而 GNU timeout 默认只发 SIGTERM，对已停止的进程会一直等下去，
  整体就不再是有界的。因此 ssh 与 systemctl 都套
  `timeout --kill-after=2s`，宽限期后补发 SIGKILL，stopped / 无响应进程
  也能被强制收尾。本轮上限为「本机 10s + SSH 30s + 2s + 重启 10s + 2s
  = 54s」，短于 service 的 `TimeoutStartSec=55s`，因此即使 systemctl
  挂死也不会拖过 service 超时，下一轮仍受已落盘的冷却期抑制。

## 探针判定（公网主机，`frp-public-probe.sh`）

对 `https://hnieacm.com/` 与 `https://www.hnieacm.com/` 各发一次请求：
TLS 证书默认校验（不使用 `-k`）、仅 IPv4、忽略代理、连接 5s / 整体 10s；
**HTTP 状态码必须恰为 200**，且响应体含稳定标记「算法设计在线评测系统」。
先判状态码再判标记，避免把带标记的 404 页面当成健康。
两个域名都过输出 `FRP_HEALTH_OK`，任一不过输出 `FRP_HEALTH_FAILED`，
两种情况都退出 0；其他任何输出/非零退出都被应用服务器视为「未知」。
探针只读、无需 sudo。

## 部署

公网主机（42.194.237.240，用户 `ubuntu`）：

1. 安装官方 0.69.1 的 `frps`、`frpc`（仅服务端需要 `frps`）到 `/usr/local/frp/`，
   放置 `/usr/local/frp/frps.toml`。
2. 安装 `frps.service`，`systemctl daemon-reload && systemctl enable --now frps`
   （`Restart=always` / `RestartSec=5`）。
3. 安装 `frp-public-probe.sh` 为 `/usr/local/sbin/hnieoj-frp-public-probe`（root 0755）。
4. 生成**专用**探针密钥对，把公钥写入公网主机 `/home/ubuntu/.ssh/authorized_keys`，
   使用受限强制命令（只允许运行探针）：

   ```text
   restrict,command="/usr/local/sbin/hnieoj-frp-public-probe" ssh-ed25519 AAAA... hnieoj-frp-probe
   ```

   不要为运维添加公网 root/管理员公钥；探针命令不需要 sudo。

应用服务器（root）：

1. 升级 `frpc` 到官方 0.69.1，与公网 `frps` 保持一致。
2. 安装 `frp-healthcheck.sh` 为 `/usr/local/sbin/hnieoj-frp-healthcheck`（root 0755）。
3. 安装专用私钥 `/etc/hnieoj-frp/probe_ed25519`（`0600`，仅 root 可读），
   并用带外核验过的主机公钥生成 `/etc/hnieoj-frp/known_hosts`
   （`ssh-keyscan 42.194.237.240` 仅为起点，必须与已知指纹核对后再固定；
   脚本使用 `StrictHostKeyChecking=yes` + `UserKnownHostsFile` 固定该文件）。
4. 安装 service/timer，`systemctl daemon-reload && systemctl enable --now frp-healthcheck.timer`。
   timer 每 60s 触发，service `TimeoutStartSec=55s`，`After=network-online.target`。
5. 首次连通性验证：`sudo -u root /usr/local/sbin/hnieoj-frp-healthcheck; echo "rc=$?"`，
   并确认 `journalctl -u frp-healthcheck` 中出现 `external probe healthy`。
   脚本 ssh 使用 `-nT` 且外层 `timeout --kill-after=2s`，从交互式终端手动执行
   也不会因 ssh 读取终端（SIGTTIN）而挂起。

## 回滚

1. 应用服务器：`systemctl disable --now frp-healthcheck.timer`，删除
   `/etc/systemd/system/frp-healthcheck.{service,timer}` 后 `systemctl daemon-reload`。
2. 删除 `/usr/local/sbin/hnieoj-frp-healthcheck` 与 `/etc/hnieoj-frp/probe_ed25519`；
   在公网主机 `authorized_keys` 删除该 `restrict,command=...` 条目。
3. 如 FRP 版本回滚，务必 `frps`/`frpc` 同步回同一版本，避免协议不兼容。
4. 需要立即停掉自动恢复而不卸载时：只 `systemctl stop frp-healthcheck.timer`，
   状态文件 `/var/lib/hnieoj-frp-health/state` 保留，手动删除即回到初始计数。

## 日志与告警

只有 journald，没有配置任何推送通道（无邮件、无 Slack）：

```bash
journalctl -u frp-healthcheck.service -n 50 --no-pager   # 检查结论、失败计数、重启动作
journalctl -u frps.service -n 50 --no-pager              # 公网服务端状态
```

关注字段：`external probe FAILED ... consecutive failures N/3`、
`ACTION restarting frpc` / `ACTION systemctl restart frpc failed`、
`restart suppressed by cooldown`、`WARN ... resetting consecutive failures`。
状态文件 `/var/lib/hnieoj-frp-health/state` 记录 `consecutive_failures` 与
`last_restart_epoch`。

## 回归测试

测试只使用仓库内脚本与临时目录，不安装依赖、不触碰部署路径。在仓库根目录执行：

```bash
docker run --rm -v "$PWD":/work:ro --entrypoint bash hnieoj-unified-web:latest \
    /work/docker/tests/frp-public-probe.test.sh
docker run --rm -v "$PWD":/work:ro --entrypoint bash hnieoj-unified-web:latest \
    /work/docker/tests/frp-healthcheck.test.sh
```
