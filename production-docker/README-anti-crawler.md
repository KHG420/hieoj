# HNIEOJ 反爬机制总览

> 面向「防止未来的其他爬虫」。现有机制见 `README-operations.md`。

## 一、设计出发点

已知名单（OpenAI / Anthropic 官方 IP 段）只能挡住**已经公布过自己**的爬虫。
对将来出现的新爬虫，唯一不依赖身份、只依赖行为的判据是**枚举广度**：

| 来源 | 访问过的不同 `user_id` 数（2026-09-10 实测本站日志） |
|---|---|
| GPTBot | **1510** |
| ClaudeBot | **234** |
| Googlebot | 10 |
| SemrushBot | 7 |
| 正常学生 | 个位数 |

爬虫与合法爬虫之间隔着 **20 倍以上**的差距 —— 因此阈值定在 50 是安全的。

**为什么不用请求频率**：校园机房 NAT 后多个学生共用一个出口 IP，按频率封必然误伤。
枚举广度则与出口 IP 是否共享无关。

## 二、四层结构

| 层 | 位置 | 职责 | TTL | 实现 |
|---|---|---|---|---|
| L1 | nginx | 官方 IP 段 + UA 特征 → 444 | 静态 | `conf.d/00-oj-http.conf` |
| L2 | nginx | **动态黑名单统一出口**（供 L3/L5 写入） | 由写入方决定 | `conf.d/blocklist.map` + `geo $block_ip` |
| L3 | systemd timer | **按枚举广度自动发现新爬虫** | 24h | `scripts/crawler-guard.sh` |
| L4 | PHP | 匿名访客禁止按 `user_id` 过滤 + 限制翻页深度 | 静态 | `web/status.php` |
| L5 | nginx + PHP 模板 | **蜜罐**（确定性判据） | 7d | `scripts/honeypot-sweep.sh` + `footer.php` |

命中任意判据都是 `return 444`：不发响应体、不进 location、**不写 access_log**。

### L2 为什么需要单独一层

`blocklist.map` 被 `00-oj-http.conf` 里 `geo $block_ip { include ... }` 显式引用
（文件名后缀非 `.conf`，所以不会被 `include conf.d/*.conf` 重复加载）。
L3/L5 都只往这个文件写，从而实现「一个封禁出口，多种发现手段」。

### L5 蜜罐为什么是最硬的判据

诱饵路径 `/_oj_(trap|export|internal|debug)/`：

* 在 `robots.txt` 里被 `Disallow` —— **守规矩的爬虫会主动避开**，所以命中者必然不守规矩；
* 在 `footer.php` 里以 `display:none` 隐藏 —— **浏览器不渲染，而解析 HTML 提取链接的爬虫会跟进去**。

真实访客两条都不会碰，因此命中即定性，无需阈值，也不会误伤。

## 三、运维

### 回滚（只观察不封禁）

```bash
touch /opt/hnieoj-docker/run/crawler-guard.disabled
```

### 查看当前封禁

```bash
cat /opt/hnieoj-docker/conf/nginx/conf.d/blocklist.map
tail -f /var/log/hnieoj-crawler-guard.log      # 运行日志（含 ALLOW/OBSERVE/APPLIED/ERROR）
```

### 白名单（命中阈值也永不封禁）

编辑 `/opt/hnieoj-docker/conf/crawler-guard.allow`，每行一个 **IP 前缀**，例如 `66.249.`。

### 调阈值

`systemd` 单元里可用环境变量覆盖，或直接改脚本默认值：

```bash
CG_MAX_IDS=80          # 枚举广度阈值（默认 50）
CG_TAIL_LINES=5000     # 每次分析的日志尾部行数（默认 3000）
CG_TTL=43200           # L3 封禁时长秒数（默认 86400 = 24h）
HP_TTL=1209600         # L5 蜜罐封禁时长秒数（默认 604800 = 7d）
```

### 幂等性

L3 与 L5 都只在 `blocklist.map` **内容实际变化**时才写盘并 `nginx -s reload`，
稳态下无额外开销。两者各写各的区块（`AUTO-BLOCK` / `HONEYPOT`），互不覆盖。

## 四、已知边界

1. **被拦截的请求不写 access_log**（`if=$oj_keep_log`），所以从日志看不到爬虫活动。
   要确认它们是否仍在敲门，需用 `ss`/抓包，或临时去掉该条件。
2. **纯 UA 判据可伪造**，但 L1 的 IP 段判据与 L3 的行为判据都不依赖 UA。
3. **L4 只改了 `status.php`**。`userinfo.php`、`problemstatus.php`、`contestrank*.php`
   等同类枚举入口尚未加同样的限制 —— 目前靠 L3 的行为检测兜底。
   若要彻底，应把这套限制抽成公共 include。
4. **X-Forwarded-For 可被客户端伪造**（`set_real_ip_from` 信任了 Docker 内网段）。
   建议后续在 frps 端**覆写**而非追加该头。
