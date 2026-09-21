# FRP 外部健康检查与有界自动恢复

统一 FRP 版本：**0.69.1**（公网 `frps` 与应用服务器 `frpc` 必须一致）。
相关文件（本仓库 `docker/operations/`）：

| 文件 | 部署位置 | 模式 |
|---|---|---|
| `frp-public-probe.sh` | 公网主机 `/usr/local/sbin/hnieoj-frp-public-probe` | 0755 |
| `frp-healthcheck.sh` | 应用服务器 `/usr/local/sbin/hnieoj-frp-healthcheck` | 0755 |
| `frp-healthcheck.service` | 应用服务器 `/etc/systemd/system/frp-healthcheck.service` | 0644 |
| `frp-healthcheck.timer` | 应用服务器 `/etc/systemd/system/frp-healthcheck.timer` | 0644 |
| `frp-notify.py` | 公网主机 `/usr/local/sbin/hnieoj-frp-notify.py` | 0644 |
| `frp-notify.service` | 公网主机 `/etc/systemd/system/frp-notify.service` | 0644 |
| `frp-notify.timer` | 公网主机 `/etc/systemd/system/frp-notify.timer` | 0644 |
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

## 公网故障/恢复邮件通知（`frp-notify`）

这是一条与自动恢复**完全独立**的告警链路，运行在**公网主机**（不是应用服务器），
由 `frp-notify.timer` 每 60s 拉起 `/usr/local/sbin/hnieoj-frp-notify.py`
（`ExecStart=/usr/bin/python3 /usr/local/sbin/hnieoj-frp-notify.py`）。
脚本只调用本机探针 `/usr/local/sbin/hnieoj-frp-public-probe`（超时 25s），
不 SSH 应用服务器、不执行任何重启动作；因此应用服务器/应用整机不可用时，
只要公网主机和探针还在，通知仍会发出。

判定与通知语义：

- **连续 3 次失败**（阈值 3）→ 一条不可用告警，含首次失败时间（CST）、
  两个监控 URL、连续失败次数。两个域名属于同一站点、同一次事故，合并为一条消息。
- **连续 2 次健康** → 一条恢复通知，含持续时间；恢复时间按**首次确认恢复**的时刻计算，
  投递重试不会把恢复时间/时长越推越晚。
- **故障持续**：距上次“已接受”通知满 1800s 再发一条提醒。
- **未知结果**（探针超时/非零退出/输出不是 `FRP_HEALTH_OK|FAILED`）只按未知处理：
  清零连续计数、不当作恢复、不发通知；若此时有“待发恢复”，该恢复作废，
  事故本身保留，必须重新连续 2 次健康才会再发恢复。
- **提交失败绝不记为已接受**：300s 后重试，两次重试之间只写日志。
  恢复通知提交失败后同样 300s 重试；若首次告警从未被接受就已恢复，
  则不再单独发“已恢复”，避免只收到恢复的误导。
- 邮件受理成功后清除重试冷却，后续的恢复通知不会被上一次成功投递的冷却拖延。
- 发送前先把重试冷却/尝试时间原子落盘，进程即便随后被杀也不会每分钟重发。
- PushPlus 返回 `code==900`（用户当日限额）时，按官方说明
  <https://www.pushplus.plus/doc/help/limit.html> 暂停投递到**次日 CST 0 点**再试；
  暂停期间探针检查照常进行，避免继续请求延长封禁。其他网络/接口失败仍按 300s 重试。

配置与状态：

| 路径 | 内容 | 模式 |
|---|---|---|
| `/etc/hnieoj-frp-notify.json` | `{"pushplus_token": "..."}`，仅此一项 | 0600 |
| `/var/lib/hnieoj-frp-notify/state.json` | 连续计数、事故起止、待发通知、恢复确认时刻、重试冷却、messageID | 文件 0600（目录 0700） |

推送使用 PushPlus 官方接口 `https://www.pushplus.plus/send`
（`template=txt`、`channel=mail`）：`channel=mail` 走 PushPlus **默认发信通道**，
不传 `option`，收件邮箱在用户个人资料中绑定，无需自建 SMTP。
HTTP 200 且 JSON `code==200` 仅表示
**已受理入队**，返回的 `data` 只是 messageID（用于追踪），**不代表用户已收到**。
网络请求有界：`urllib` 忽略代理环境变量（`ProxyHandler({})`）、TLS 默认校验、
超时 10s。token 绝不进入 argv/日志/输出/Git。
未配置 token 时脚本安全失败（退出非零、不推送、不写状态）：
**在 Codex 提供 token 之前不要 `enable` 该 timer。**

范围限制：本通知只能观测“从公网主机访问自建域名”的可用性，并依赖公网主机与
PushPlus。若公网主机整机/整网不可用（云侧故障），该链路同样发不出通知，
需要另行的人工或云厂商监控兜底。

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
5. 安装 `frp-notify.py` 为 `/usr/local/sbin/hnieoj-frp-notify.py`（root 0644，
   由 `/usr/bin/python3` 显式调用，无需可执行位）。
6. 写入 `/etc/hnieoj-frp-notify.json`（root 0600）：`{"pushplus_token": "<token>"}`。
   **未拿到 token 前不要执行下一步**，脚本会因配置缺失而安全失败。
7. 安装 `frp-notify.service` / `frp-notify.timer` 后
   `systemctl daemon-reload && systemctl enable --now frp-notify.timer`。
   service `Type=oneshot`、`StateDirectory=hnieoj-frp-notify`（0700）、
   `TimeoutStartSec=50s`（探针 25s + HTTPS 10s）；timer 每 60s、`AccuracySec=1s`。
   不 source 任何 `.env`，token 只来自上面的配置文件。
8. 手动发一条测试消息并确认邮箱收到：
   `/usr/bin/python3 /usr/local/sbin/hnieoj-frp-notify.py --test-notification`。
   该消息明确标注“测试通知”，不修改事故状态；若有凭据缺失或接口拒绝则退出非零。

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
5. 公网主机：`systemctl disable --now frp-notify.timer`，删除
   `/etc/systemd/system/frp-notify.{service,timer}` 后 `systemctl daemon-reload`，
   再删除 `/usr/local/sbin/hnieoj-frp-notify.py` 与 `/etc/hnieoj-frp-notify.json`。
   状态目录 `/var/lib/hnieoj-frp-notify` 可保留用于下次恢复判定，删除即回到初始状态。

## 日志与告警

应用服务器只有 journald，没有邮件/Slack；公网主机侧新增了 PushPlus 邮件推送：

```bash
journalctl -u frp-healthcheck.service -n 50 --no-pager   # 应用服务器：检查结论、失败计数、重启动作
journalctl -u frps.service -n 50 --no-pager              # 公网主机：服务端状态
journalctl -u frp-notify.service -n 50 --no-pager        # 公网主机：探测结论、通知受理/重试
```

关注字段：`external probe FAILED ... consecutive failures N/3`、
`ACTION restarting frpc` / `ACTION systemctl restart frpc failed`、
`restart suppressed by cooldown`、`WARN ... resetting consecutive failures`。
状态文件 `/var/lib/hnieoj-frp-health/state` 记录 `consecutive_failures` 与
`last_restart_epoch`。

通知侧关注：`notification ... accepted (messageID=...)`（**已受理入队，不代表送达**）、
`notification ... was not accepted ...; will retry after 300s`、
`notification ... hit PushPlus daily limit (code 900); pausing delivery until ...`、
`probe result unknown ...`、`next delivery attempt in Ns`。
状态文件 `/var/lib/hnieoj-frp-notify/state.json` 记录连续计数、事故起止、
待发通知、恢复确认时刻、重试冷却与 messageID。

## 回归测试

测试只使用仓库内脚本与临时目录，不安装依赖、不触碰部署路径。在仓库根目录执行：

```bash
python3 docker/tests/frp-notify-test.py    # 纯 stdlib unittest；只 mock 探针与 HTTPS 边界
docker run --rm -v "$PWD":/work:ro --entrypoint bash hnieoj-unified-web:latest \
    /work/docker/tests/frp-public-probe.test.sh
docker run --rm -v "$PWD":/work:ro --entrypoint bash hnieoj-unified-web:latest \
    /work/docker/tests/frp-healthcheck.test.sh
```

## 网络证据记录（`frp-network-diagnostics`，应用服务器，只读）

2026-09-21 10:22–10:39 CST 实测事实：应用服务器 172.31.0.96 能到网关
172.31.0.1 与本地 Web，但到腾讯 42.194.237.240 的 TCP 22/7000 与公网 DNS
都失败；SYN 已离开网卡、没有回包；10:39:45 同一个 frpc PID 自行恢复重连，
networkd 没有任何事件。上游触发原因未知。为把「当时本机看到了什么」逐分钟
留证，新增这条**只读**证据链路：每分钟追加一行 JSONL，供事后与云侧/校园网
上游日志对齐，而不是在本机猜测结论。

它与 `frp-healthcheck`（自动恢复）、`frp-notify`（公网邮件）完全独立：
不重启、不登录、不改配置、不读任何配置文件、不读环境变量，只执行固定只读
命令（`curl` / `ip` / `ping` / `getent` / `journalctl` / `systemctl show`），
诊断结果不触发任何动作。

| 文件 | 部署位置 | 模式 |
|---|---|---|
| `frp-network-diagnostics.py` | 应用服务器 `/usr/local/sbin/hnieoj-frp-network-diagnostics.py` | 0644 |
| `frp-network-diagnostics.service` | 应用服务器 `/etc/systemd/system/frp-network-diagnostics.service` | 0644 |
| `frp-network-diagnostics.timer` | 应用服务器 `/etc/systemd/system/frp-network-diagnostics.timer` | 0644 |

### 每次快照记录什么

10 项固定检查，每项 3–5s 硬超时、固定 8 线程有界并发，正常一轮 <15s：

| 检查 | 命令 / 边界 | `ok` 的条件 |
|---|---|---|
| `local_http` | `curl -4 --noproxy '*'` 请求 `http://127.0.0.1/`，正文写临时文件、只读前 256KiB | HTTP 恰为 200 且含页面标记「算法设计在线评测系统」 |
| `tcp_frp_7000` | 字面 IP socket 连接 42.194.237.240:7000 | 握手成功 |
| `tcp_ssh_22` | 字面 IP socket 连接 42.194.237.240:22 | 握手成功 |
| `tcp_dns_53` | 字面 IP socket 连接 223.5.5.5:53 | 握手成功 |
| `resolver` | `getent ahostsv4 www.baidu.com`（5s 上限） | 至少解析出一个 IPv4 地址 |
| `default_route` | `ip -4 route show default` | 存在 `dev ens160` 的默认路由（其他网卡的默认路由只记录、不算 ok） |
| `gateway_ping` | `ping -4 -n -q -c 1 -W 2 -w 4 <网关>` | 有回包 |
| `neighbor` | `ip -4 neigh show dev ens160` | 网关条目不是 FAILED/INCOMPLETE（无条目不算异常） |
| `interface` | `ip -4 -o addr show dev ens160` + `ip -s -o link show dev ens160` | operstate UP 且有 IPv4 地址；同时记录 RX/TX 字节、包、错误、丢包计数 |
| `frpc_service` | `systemctl show frpc -p LoadState,ActiveState,SubState,MainPID,ActiveEnterTimestamp,...` | `LoadState=loaded` 且 `ActiveState=active`，记录 MainPID 与启动时间 |

路由/地址/链路/邻居观测都围绕应用网卡 `ens160`。TCP 探测只对字面 IPv4
构造 `AF_INET` socket，**不做 DNS 解析**（诊断本身不依赖解析器）；
curl 显式 `--noproxy '*'`，不受代理环境变量影响。

每行 JSONL 字段：

- `ts`（带时区 ISO8601）、`epoch`、`elapsed_ms`、`hostname`、`iface`；
- `checks.<name>`：`ok` / `error` / `observed` / `duration_ms` 加该检查的原始
  观测（HTTP 只记状态码、是否含标记、正文字节数，**绝不记正文**）；
- `failed_checks`：所有 `ok=false` 的检查名；`issues`：`<检查名>: <error>`
  形式的建议标签，仅描述本机观测，不做因果推断；
- `primary_issues` / `primary_failure`：只看 `local_http`、`tcp_frp_7000`、
  `tcp_ssh_22`、`tcp_dns_53`、`resolver` 这 5 项主判据；
- `probes_complete`：所有检查是否都真正跑出了观测（命令缺失、子进程超时等
  情况为 false）。`failed_checks` 非空或 `probes_complete=false` 都不能被
  读成「一切正常」。

`error` 标签刻意区分：`refused`（拒绝）、`timeout`（超时/无回包）、
`dns_error`（解析失败）、`missing_command`（命令缺失）、`http_5xx`、
`marker_missing`、`no_default_route`、`down`、`not_active` 等。

### 旁证与结论的边界

- **网关 ping 单独失败不构成任何结论。** 它只是旁证；`primary_failure` 只由
  上面 5 项主判据决定。网关/邻居/接口/frpc 服务异常会如实进入
  `failed_checks` 与 `issues`，但不据此判定「公网不可用」。
- 本记录只反映**应用服务器本机的观测**。上游（腾讯云 VPC/安全组、运营商、
  校园网出口、DNS 服务商）是否异常，必须用上游日志对齐后才能定论；
  没有上游日志时只能说「本机在哪些时刻看到了什么」。
- 不推断「校园网大面积故障」「门户认证过期」等结论；`issues` 只是原始观测标签。
- 不含配置、环境变量、凭据。journal 行中的 `token=` / `password:` 等键值会
  脱敏为 `<redacted>`，且只保留最近 30 行 / 2 分钟内 / 最多 8KiB。

### journal 附带规则

仅两种情况附带 frpc journal（`journalctl -u frpc -n 30 --since '2 min ago'`）：

1. 本轮任一主判据失败 → `journal.reason=primary_failure`；
2. 上一轮主判据失败、本轮恢复 → `journal.reason=recovery`。

上一轮状态保存在日志目录 `state.json`（仅 `last_epoch`、`primary_failure`、
`issues`）。journalctl 本身失败只记 `journal.error`，不影响快照落盘。

### 有界性与权限

- 目录 `/var/log/hnieoj-frp-network` 0700；`events.jsonl`、`state.json`、
  `lock` 0600（脚本显式 chmod，service `UMask=0077` 兜底）。
- `events.jsonl` 按 4MiB 轮转：写新行前若会超过 4MiB，则
  `events.jsonl` → `.1` → … → `.7`，最旧的 `.7` 被覆盖，总量 ≤32MiB。
- service `Type=oneshot`、`TimeoutStartSec=45s`；timer `OnBootSec=30s`、
  `OnUnitActiveSec=60s`、`AccuracySec=1s`。
- `flock` 非阻塞独占锁：手动执行与 timer 重叠时跳过本轮，不写半份证据。
- 无法写证据（目录不可建、`events.jsonl` 不可写）→ stderr 可见、退出非零；
  探测失败但证据写入成功 → 退出 0；state 写失败只告警，证据仍保留。

### 安装（仅应用服务器；不重建 Web 镜像）

```bash
install -m 0644 docker/operations/frp-network-diagnostics.py \
    /usr/local/sbin/hnieoj-frp-network-diagnostics.py
install -m 0644 docker/operations/frp-network-diagnostics.service \
    docker/operations/frp-network-diagnostics.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now frp-network-diagnostics.timer
```

不涉及 Web 镜像或前端产物，不需要重建/重启 Web 容器，也不改动
`frp-healthcheck`、`frp-notify` 的 unit、状态与判定逻辑。

### 状态、查看与读取

```bash
systemctl status frp-network-diagnostics.timer --no-pager
systemctl list-timers frp-network-diagnostics.timer --no-pager
journalctl -u frp-network-diagnostics.service -n 20 --no-pager  # 每轮一行摘要；写失败为 WARN
tail -n 5 /var/log/hnieoj-frp-network/events.jsonl              # 最近 5 分钟快照
ls -l /var/log/hnieoj-frp-network/                             # events.jsonl + .1..7
```

```bash
# 用 Python 读取 JSONL，挑出主判据失败或观测不完整的行（jq 非必需）
python3 - <<'PY'
import json
for line in open('/var/log/hnieoj-frp-network/events.jsonl'):
    rec = json.loads(line)
    if rec['primary_failure'] or not rec['probes_complete']:
        print(rec['ts'], rec['issues'])
PY
```

轮转说明：超过 4MiB 时在写新行前轮转，`.1` 最新、`.7` 最旧并被覆盖；
按每行约 2–4KiB 估算，32MiB 总量可回溯数天。

### 回滚

```bash
systemctl disable --now frp-network-diagnostics.timer
rm -f /etc/systemd/system/frp-network-diagnostics.service \
      /etc/systemd/system/frp-network-diagnostics.timer
systemctl daemon-reload
rm -f /usr/local/sbin/hnieoj-frp-network-diagnostics.py
rm -rf /var/log/hnieoj-frp-network   # 需要保留证据时不要执行
```

### 回归测试

```bash
python3 docker/tests/frp-network-diagnostics-test.py
```

只 mock 子进程与 TCP socket 两个外部边界；快照组装、输出解析、state、权限与
4MiB 轮转都跑真实逻辑，全部使用临时目录，不接触 `/var/log` 与真实网络。
