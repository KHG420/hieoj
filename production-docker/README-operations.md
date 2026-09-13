# HNIEOJ 部署运维须知

## 一、改 nginx / php 配置如何生效

（2026-09-10 起已改为**目录挂载**，不再有 inode 陷阱。）

`conf/` 下现在的挂载方式：

    /opt/hnieoj-docker/conf/nginx/nginx.conf   -> /etc/nginx/nginx.conf      单文件
    /opt/hnieoj-docker/conf/nginx/conf.d/      -> /etc/nginx/conf.d/         目录
    /opt/hnieoj-docker/conf/nginx/snippets/    -> /etc/nginx/snippets/       目录
    /opt/hnieoj-docker/conf/php/99-oj.ini      -> /etc/php/8.1/fpm/conf.d/99-oj.ini
    /opt/hnieoj-docker/conf/php/www.conf       -> /etc/php/8.1/fpm/pool.d/www.conf

**目录挂载跟随路径**，所以任何编辑器（`vim` / `sed -i` / 各种写入工具）改完都立即生效，
**不需要**重建容器：

    # nginx 配置：校验后热加载
    docker exec hnieoj-web nginx -t && docker exec hnieoj-web nginx -s reload

    # php 配置：USR2 重载 FPM 主进程
    docker exec hnieoj-web sh -c 'kill -USR2 $(cat /run/php/php8.1-fpm.pid)'

    # 站点源码（PHP/模板/CSS）：opcache.revalidate_freq=60，急用则 USR2 重载 fpm
    docker exec hnieoj-web sh -c 'kill -USR2 $(cat /run/php/php8.1-fpm.pid)'

### 历史：单文件 bind mount 的 inode 陷阱（已根治，留作教训）

原先每个文件都是**单独**挂载的，而单文件 bind mount 绑定的是**该文件的 inode**，不是路径。
于是 `vim` / `sed -i` / 编辑器保存 / 各种写入工具改写，本质都是「写临时文件 → rename 覆盖」，
**inode 会变**；而容器仍持有**旧 inode**。此时 `nginx -t` 通过、`nginx -s reload` 也报成功，
**但配置完全不生效** —— 表现为「改了配置却毫无变化」，极易误判成语法或逻辑问题。
（2026-09-10 排查约 1 小时才定位）

当时的绕法是「原地覆盖」`cat new.conf > target` 以保持 inode。改成目录挂载后，
**脚本里不再需要这个约束**（`sync-aibot-ips.sh` 等已不受影响）。

---

## 二、反爬机制（2026-09-10 建立）

完整说明见 **`README-anti-crawler.md`**。四层速查：

| 层 | 实现 | 判据 | 位置 |
|---|---|---|---|
| L1 | nginx `geo` + `map` | 官方 IP 段、UA 特征 | `conf.d/00-oj-http.conf` |
| L2 | `conf.d/blocklist.map` | 动态黑名单（**统一封禁出口**） | 同上 |
| L3 | `scripts/crawler-guard.sh`（timer 30s） | **枚举广度** > 50 个不同资源 id | `/etc/systemd/system/hnieoj-crawler-guard.*` |
| L4 | `status.php` 补丁 | 匿名禁止按 `user_id` 过滤 + 翻页深度上限 | webroot |
| L5 | 蜜罐 `/_oj_trap/*` 等 | 命中即定性（robots 禁止 + `display:none` 双诱饵） | `scripts/honeypot-sweep.sh` |

- 命中任一即 `return 444`：不返回响应体、不进 location、**不写 access_log**。
- 同步官方网段：`scripts/sync-aibot-ips.sh`（已排入 `cron.weekly`）。
- **副作用**：被拦爬虫不写日志，所以 `access.log` 里看不到它们。若要确认它们是否仍在敲门，
  看点对点的 `ss` / 抓包，或临时去掉 `if=$oj_keep_log`。
- **一键回滚 L3/L5**：`touch /opt/hnieoj-docker/run/crawler-guard.disabled`
  → 转为只记录不封禁（OBSERVE 模式），无需改配置。
- 实测（2026-09-10）：GPTBot 632.5 MB / 117,275 请求（占全站 88.5%），ClaudeBot 8.3k 请求。
  单 IP 枚举广度：GPTBot `74.7.241.25` 触达 1510 个不同 `user_id`，ClaudeBot 234 —— 阈值 50 有 20 倍余量。

### frps 入口封禁（2026-09-10 已执行）

隧道流量发生在 `42.194.237.240 (frps) ↔ 本机 frpc` 之间，只有在这之前丢弃才真正省流量。
已在 frps 上封禁 **OpenAI 21 条 + Anthropic 26 条**官方网段，共 **47 条**：

    iptables -I INPUT -s <CIDR> -j DROP
    /etc/aibot/block.sh                        # 重放脚本
    systemctl status aibot-block.service        # oneshot，已 enable + active

注：frps 上 `ipset` 不可用（MISSING），故用 iptables 直封；`netfilter-persistent` 亦缺失，
改用 oneshot service 在启动时重放规则。

---

## 三、日志

容器内 `/var/log/nginx/*.log` 不在挂载卷上，宿主 logrotate 管不到；容器内无常驻 cron。
已加 `/etc/cron.daily/hnieoj-nginx-log-rotate` 每日截断（`docker exec hnieoj-web sh -c ': > $f'`）。

另：蜜罐命中单独写 `/var/log/nginx/honeypot.log`，不参与上面的截断。
