> 历史归档：以下命令对应旧生产布局，不适用于统一仓库的新部署。当前入口见根目录 README 和 compose.yaml；切换流程见 docker/MIGRATION.md。

# HnieOJ 容器化部署文档

| 项 | 值 |
|---|---|
| 文档版本 | v1.0（2026-09-10） |
| 适用系统 | Ubuntu 22.04 LTS（amd64） |
| 部署方式 | Docker Compose 单机容器栈 |
| 部署目录 | `/opt/hnieoj-docker` |
| 站点源码 | `/home/judge/src/web`（bind mount，不在镜像内） |
| 判题数据 | `/home/judge/data`（bind mount，约 2.4G / 3800 个题目目录） |

---

## 1. 架构总览

```
                         ┌─────────────────── 宿主机 (Ubuntu 22.04) ───────────────────┐
浏览器 ──https──▶ frps    │  frpc.service ──▶ 127.0.0.1:80                               │
 (公网服务器)   (42.x.x.x) │      │                                                       │
                         │      ▼                                                       │
                         │  ┌── hnieoj-web ────────────┐  ┌── hnieoj-judge ─────────┐  │
                         │  │ nginx + php8.1-fpm       │  │ judged（守护者进程）     │  │
                         │  │ 挂载: /home/judge/src/web│  │ 挂载: /home/judge       │  │
                         │  └───────┬──────────────────┘  │      docker.sock        │  │
                         │          │                     │      /usr/bin/judged    │  │
                         │  ┌───────▼──────┐  ┌─────────┐ │      conf/etc(隔离pid) │  │
                         │  │ hnieoj-db    │  │hnieoj-  │ └──────────┬──────────────┘  │
                         │  │ mariadb 10.6 │  │cache    │            │ docker run      │
                         │  │ :3306        │  │:11211   │            ▼                 │
                         │  │ 卷 db-data   │  └─────────┘  ┌── hustoj:latest ──────┐ │
                         │  └──────────────┘               │ 判题沙箱（编译/运行）  │ │
                         │                                 └───────────────────────┘ │
                         └─────────────────────────────────────────────────────────────┘
```

**数据流要点**

1. 外部流量经 frp 隧道进来，`frpc.toml` 的 `localPort=80` 指向 **web 容器**（配置零改动）。
2. **`judged` 自己会 `docker run` 拉起判题沙箱**；这些 `docker run` 命令由**宿主机的 dockerd** 执行（通过挂进容器的 `/var/run/docker.sock`）。
3. 沙箱容器用 `-v /home/judge:/home/judge` 挂载，该路径由**宿主 dockerd 解析** —— 因此 judge 容器里的 `/home/judge` 必须是宿主同路径的 bind mount（硬性要求）。
4. 沙箱内运行的 `judge_client` 读取的是**宿主机**的 `/home/judge/etc/judge.conf`。所以 **web / judged / 沙箱 judge_client 三方必须连同一个数据库**。

**容器清单**

| 服务 | 镜像 | 网络 | 端口映射 | 说明 |
|---|---|---|---|---|
| `web` | `hnieoj-web:2026.09` | bridge | `80:80` | nginx + php8.1-fpm |
| `db` | `hnieoj-db:2026.09` | bridge | `127.0.0.1:3306:3306` | 自建 mariadb 10.6 |
| `cache` | `hnieoj-cache:2026.09` | bridge | `127.0.0.1:11212:11211` | memcached —— **刻意未启用**，见 5.6 节：镜像装的是 `php8.1-memcached`（`Memcached` 类），上游 hustoj 用的是旧 `Memcache` 扩展，直开会全站 500 |
| `judge` | `hnieoj-judge:2026.09` | **host** | — | judged + docker CLI |
| （沙箱） | `hustoj:latest` | host | — | 判题沙箱，由 judged 按需拉起，**不可删除** |

**卷**

| 卷 | 挂载点 | 内容 |
|---|---|---|
| `hnieoj_db-data` | db 容器 `/var/lib/mysql` | 数据库数据 |
| `hnieoj_page-cache` | web 容器 `/tmp/hustoj_page_cache` | OJ 页面缓存 |

---

## 2. 前置条件

| 项 | 要求 | 检查命令 |
|---|---|---|
| 操作系统 | Ubuntu 22.04（amd64） | `lsb_release -rs` |
| Docker | ≥ 24（本机 29.6.0） | `docker version` |
| Docker Compose | v2 插件 | `docker compose version` |
| 内存 | ≥ 8G（db buffer pool 占 4G） | `free -g` |
| 磁盘 | ≥ 20G 可用 | `df -h /` |
| 源码 | `/home/judge/src/web` + `/home/judge/data` | `ls /home/judge/` |
| 判题沙箱 | 宿主已构建 `hustoj:latest` | `docker images hustoj` |

**安装 compose 插件（若缺失）**

```bash
apt-get install -y docker-compose-plugin
docker compose version     # 期望 v5.x
```

> ⚠️ `docker compose` 子命令在**没有 HOME 环境变量**时会找不到插件（报 `unknown shorthand flag: 'f'`）。
> 在脚本/定时任务里执行时务必先 `export HOME=/root`。

---

## 3. 目录结构

```
/opt/hnieoj-docker/
├── compose.yaml              # 编排定义
├── .env                      # 环境变量（含密码，权限 600，勿入库）
├── README.md                 # 运维速查 + 回滚
├── DEPLOY.md                 # 本文档
├── web/
│   ├── Dockerfile            # nginx + php8.1-fpm
│   └── entrypoint.sh         # 双进程守护（php-fpm + nginx）
├── judge/
│   ├── Dockerfile            # judged 运行时 + docker CLI（含 judge 用户 UID 1536）
│   └── entrypoint.sh         # 启动 judged 后循环守护容器
├── db/
│   ├── Dockerfile            # 自建 mariadb 10.6
│   └── entrypoint.sh         # 首次初始化 + 建库建用户 + 启动
├── cache/
│   └── Dockerfile            # memcached
├── conf/
│   ├── nginx/                 # 目录挂载进容器（2026-09-10 改造，改配置无需重建）
│   │   ├── nginx.conf         # 主配置：worker_connections 4096 + multi_accept
│   │   ├── conf.d/
│   │   │   ├── 00-oj-http.conf  # http 级：real_ip 还原 + 反爬 geo/map + 限流 zone
│   │   │   ├── default.conf     # server 块（444 判据 + PHP fastcgi + 限流）
│   │   │   └── blocklist.map    # 动态黑名单（crawler-guard / honeypot 写入）
│   │   └── snippets/
│   │       ├── oj-hardening.conf # 静态缓存 / 敏感路径拒绝 / 蜜罐 location / 安全响应头
│   │       ├── fastcgi-php.conf
│   │       └── snakeoil.conf
│   ├── php/
│   │   ├── 99-oj.ini         # opcache 256M / APCu 128M / 上传限制
│   │   └── www.conf          # FPM 池 64/8/8/24 + env[DB_*] / env[OJ_MEMCACHE] 注入
│   ├── etc/                  # 容器专用 /home/judge/etc（含 judge.conf + java0.policy）
│   ├── webroot-overrides/    # webroot 手工改动备份（status.php / robots.txt / footer.php）
│   └── crawler-guard.allow   # 反爬白名单（IP 前缀）
├── scripts/                  # 反爬脚本（crawler-guard / honeypot-sweep / sync-aibot-ips）
├── run/                      # 运行期状态 + 回滚开关（crawler-guard.disabled）
└── backup/                   # 部署期备份（db dump、宿主配置、判题 core）
```

---

## 4. 部署步骤

### 4.1 准备环境变量

```bash
cd /opt/hnieoj-docker
cat > .env <<'EOF'
# 并行阶段 web 连宿主 db；切换后改为 db（容器）
WEB_DB_HOST=db
WEB_DB_PORT=3306
# web 对外端口（并行验证用 8080，接管生产时改 80）
WEB_PORT=80
# db 映射到宿主机的端口（并行阶段用 3307 避开宿主 mariadb，切换时改 3306）
DB_PORT=3306
DB_PASSWORD=<与宿主 judge.conf / db_info.inc.php 一致>
DB_ROOT_PASSWORD=<自定义强密码>
EOF
chmod 600 .env
```

### 4.2 构建镜像

```bash
cd /opt/hnieoj-docker
docker compose build          # 构建 web / judge / db / cache 四个镜像
docker images | grep hnieoj
```

> **为什么四个镜像都自建（不用 Docker Hub）？**
> 国内 registry 镜像源实测只有 ~35KB/s，`mariadb:10.6` 需数小时；全部基于本地已有的
> `ubuntu:22.04` + 中科大 apt 源构建，耗时仅几分钟，且与宿主保持同一软件版本
> （mariadb 10.6.x、php 8.1）。

### 4.3 数据库初始化与数据导入

```bash
# 起 db + cache
docker compose up -d db cache
sleep 10 && docker compose ps

# 从宿主导出（用 root，避免 hustoj 用户无权导出存储过程）
mariadb-dump -uroot --single-transaction --routines --triggers --events \
  --default-character-set=utf8mb4 jol > /tmp/jol.sql

# 导入容器（必须指定客户端字符集，否则中文会二次编码）
RP=$(grep ^DB_ROOT_PASSWORD= .env | cut -d= -f2-)
docker exec -i hnieoj-db mariadb --socket=/run/mysqld/mysqld.sock \
  -uroot -p"$RP" --default-character-set=utf8mb4 jol < /tmp/jol.sql

# 校验：逐表行数必须一致
for t in solution source_code problem users contest; do
  h=$(mysql -uroot -N -B -e "select count(*) from jol.$t")
  c=$(docker exec hnieoj-db mariadb --socket=/run/mysqld/mysqld.sock -uroot -p"$RP" \
        -N -B -e "select count(*) from jol.$t")
  echo "$t host=$h container=$c $([ "$h" = "$c" ] && echo OK || echo MISMATCH)"
done
```

### 4.4 web 栈上线（并行验证，不影响生产）

```bash
# .env 里 WEB_PORT=8080、WEB_DB_HOST 指向现有数据库（见 5.4 节说明）
docker compose up -d web
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/index.php
```

**验收标准**（与宿主逐页像素对比）：

```bash
# 用 puppeteer 对同一页面在宿主(80)与容器(8080)各截一张全页图，再用 ImageMagick 比对
compare -metric AE host.png container.png diff.png     # 输出 0 表示完全一致
```

### 4.5 judge 容器化

```bash
# ⚠️ 先停宿主 judged，否则两个 judged 会抢同一队列
systemctl stop hustoj
docker compose up -d judge
docker logs --tail 12 hnieoj-judge
# 期望看到：judged 已启动 pid=NN，进入守护
```

**判题端到端验证**（插一条测试提交，跑完即删）：

```bash
RP=$(grep ^DB_ROOT_PASSWORD= .env | cut -d= -f2-)
M="docker exec hnieoj-db mariadb --socket=/run/mysqld/mysqld.sock -uroot -p$RP --default-character-set=utf8mb4"
$M jol <<'SQL'
INSERT INTO solution (problem_id,user_id,time,memory,in_date,result,language,ip,code_length,valid)
VALUES (1003,'admin',0,0,NOW(),0,0,'127.0.0.1',120,1);
SET @sid=LAST_INSERT_ID();
INSERT INTO source_code (solution_id,source)
VALUES (@sid,'#include <stdio.h>\nint main(){int a,b;scanf("%d%d",&a,&b);printf("%d\\n",a+b);return 0;}\n');
SQL
$M -N -B -e "select concat('result=',result,' pass_rate=',ifnull(pass_rate,'-')) from jol.solution order by solution_id desc limit 1"
# 期望 result=4（Accepted）；随后清理：
$M jol -e "DELETE FROM solution WHERE solution_id=(select max(solution_id) from solution);"
```

### 4.6 切换生产流量

```bash
# 1) 冻结写：停宿主 web 与判题
systemctl stop nginx php8.1-fpm hustoj
# 2) DB 终同步（宿主 -> 容器，逐表校验行数）
#    见 4.3 的 dump/import/校验三步
# 3) 停宿主 db/cache，释放端口
systemctl stop mariadb memcached
# 4) 改 .env：WEB_DB_HOST=db、DB_PORT=3306、WEB_PORT=80
#    ⚠️ 改完不要 source .env（见 8.4 的坑）
sed -i 's|^WEB_DB_HOST=.*|WEB_DB_HOST=db|;s|^DB_PORT=.*|DB_PORT=3306|;s|^WEB_PORT=.*|WEB_PORT=80|' .env
# 5) 重建容器栈
docker compose up -d --force-recreate
# 6) 验证（见第 6 节验收清单）
```

### 4.7 收尾

```bash
# 宿主服务禁止开机自启（否则重启后会与容器抢 80/3306），但不卸载，保留为冷备
systemctl disable nginx php8.1-fpm mariadb memcached hustoj php7.3-fpm

# 确认这些必须保持 enabled
systemctl is-enabled docker containerd frpc zerotier-one fail2ban
```

---

## 5. 配置详解

### 5.1 compose.yaml 关键项

```yaml
services:
  db:
    environment: { DB_ROOT_PASSWORD, DB_NAME: jol, DB_USER: hustoj, DB_PASS, DB_BUFFER_POOL: 4G }
    volumes: [ db-data:/var/lib/mysql ]
    ports: [ "127.0.0.1:${DB_PORT}:3306" ]      # 只监听本机，不对外暴露
    healthcheck:
      test: ["CMD-SHELL", "mariadb-admin --socket=/run/mysqld/mysqld.sock -uroot -p\"$${DB_ROOT_PASSWORD}\" ping"]

  web:
    build: { context: ., dockerfile: web/Dockerfile }
    environment: { DB_HOST: ${WEB_DB_HOST}, DB_PORT, DB_NAME: jol, DB_USER: hustoj, DB_PASS }
    volumes:
      - /home/judge/src/web:/home/judge/src/web    # 源码 bind mount，改完 restart 即生效
      - page-cache:/tmp/hustoj_page_cache          # 页面缓存用卷，避免容器重建丢失
    ports: [ "${WEB_PORT}:80" ]

  judge:
    network_mode: host          # 让 127.0.0.1:3306 直达 db；也便于与沙箱同网
    privileged: true            # judged 需要拉起特权沙箱容器
    volumes:
      - /home/judge:/home/judge                        # 必须与宿主同路径（沙箱 -v 依赖）
      - /var/run/docker.sock:/var/run/docker.sock      # Docker-out-of-Docker
      - /usr/bin/judged:/usr/bin/judged:ro             # 宿主编译的静态二进制
      - /usr/bin/judge_client:/usr/bin/judge_client:ro
      - /var/log/hustoj:/var/log/hustoj
      - ./conf/etc:/home/judge/etc                     # 隔离 judge.pid，避免污染宿主
```

### 5.2 nginx（`conf/nginx/`）

| 文件 | 作用 |
|---|---|
| `nginx.conf` | 主配置：`worker_connections 4096` + `multi_accept on`（Ubuntu 默认 768 且关闭） |
| `conf.d/00-oj-http.conf` | **必须放在 http 级**：`real_ip` 还原、反爬 `geo`/`map`、`limit_req_zone`、日志抑制 |
| `conf.d/default.conf` | server 块：root、PHP fastcgi、`if ($oj_bad_ip) / ($oj_bad_ua) / ($block_ip) { return 444; }`、限流 |
| `conf.d/blocklist.map` | **动态黑名单**（crawler-guard / honeypot 写入，被 `geo $block_ip` 引用） |
| `snippets/oj-hardening.conf` | 静态资源 7 天缓存、`.git/.bak/.sql` 拒绝、**蜜罐 location**、安全响应头 |

> `conf.d/` 与 `snippets/` 以**目录挂载**进容器（2026-09-10 改造），改完 `nginx -s reload`
> 即生效，**无需重建镜像**。`web/Dockerfile` 里也 COPY 一份，保证镜像可独立构建。

要点：

- **真实 IP 还原**（隧道场景必需，否则限流会把所有用户当成同一个 IP）：
  ```nginx
  set_real_ip_from 127.0.0.1;      # 宿主 frpc
  set_real_ip_from 172.16.0.0/12;  # docker 网桥（容器化后新增）
  set_real_ip_from 10.0.0.0/8;
  set_real_ip_from 192.168.0.0/16;
  real_ip_header X-Forwarded-For;
  real_ip_recursive on;
  ```
- **反爬**：GPTBot / ClaudeBot / Bytespider / SemrushBot / SERankingBacklinksBot 等
  **一律 `return 444`**（不返回响应体、不进 location、不写 access_log），
  保留 Googlebot / bingbot / Baiduspider。四层判据见 `README-anti-crawler.md`。
- **限流**：`limit_req_zone $binary_remote_addr zone=oj_dyn:10m rate=8r/s;` + `burst=20 nodelay`，
  `limit_req_status 429`。
- PHP location：`fastcgi_pass unix:/run/php/php8.1-fpm.sock`（容器内 socket）。

### 5.3 PHP（`conf/php/`）

```ini
; 99-oj.ini
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.revalidate_freq = 60      ; ⚠️ 改 PHP 后需 restart web 才立即生效
apc.shm_size = 128M
upload_max_filesize = 100M
```

```ini
; www.conf —— 池参数（默认值 max_children=5 远远不够）
pm.max_children = 64
pm.start_servers = 8
pm.min_spare_servers = 8
pm.max_spare_servers = 24

; ⚠️ php-fpm 默认 clear_env=yes，不继承环境变量，必须显式传递，否则 db_info.inc.php 拿不到 DB_*
env[DB_HOST] = $DB_HOST
env[DB_NAME] = $DB_NAME
env[DB_USER] = $DB_USER
env[DB_PASS] = $DB_PASS
```

### 5.4 数据库连接如何注入

`template/syzoj/...` 无关；连接串在 **`/home/judge/src/web/include/db_info.inc.php`**：

```php
static $DB_HOST="127.0.0.1";
static $DB_NAME="jol";
static $DB_USER="hustoj";
static $DB_PASS="...";
// 容器化改造：环境变量可覆盖；未设置时行为与原来完全一致（宿主机不受影响）
foreach (array("DB_HOST","DB_NAME","DB_USER","DB_PASS") as $__envk) {
    $__envv = getenv($__envk);
    if ($__envv !== false && $__envv !== "") { $$__envk = $__envv; }
}
```

> **不要**用 entrypoint 去 `sed` 这个文件 —— 它是 bind mount 的宿主源码，改它会连带把宿主 OJ 的连接串改坏。

### 5.5 判题配置

容器使用 `conf/etc/judge.conf`（挂载覆盖 `/home/judge/etc`），关键项：

| 项 | 值 | 说明 |
|---|---|---|
| `OJ_HOST_NAME` | `127.0.0.1` | judge 容器是 host 网络 |
| `OJ_PORT_NUMBER` | `3306` | db 容器映射在宿主 3306 |
| `OJ_USE_DOCKER` | `1` | 启用容器判题沙箱 |
| `OJ_DOCKER_PATH` | `/usr/bin/docker` | 容器内 docker CLI，经 docker.sock 操作宿主 dockerd |
| `OJ_RUNNING` | `3` | 并发判题进程数 |
| `OJ_PYTHON_FREE` | `1` | Python 免编译模式 |

### 5.6 memcached：接线已备好，开关刻意关着

`cache` 容器照常在跑，但 web 侧**不启用** memcached 缓存：

- `compose.yaml`：`OJ_MEMCACHE: "0"`
- `conf/php/www.conf`：已备好 `env[OJ_MEMCACHE]` / `env[OJ_MEMSERVER]` / `env[OJ_MEMPORT]`
  （php-fpm 默认 `clear_env=yes` 会清空环境变量，必须显式声明）
- `include/db_info.inc.php`：这三个值支持环境变量覆盖，且默认仍为 `false`

**原因**：镜像装的是 **`php8.1-memcached`（提供 `Memcached` 类）**，而上游 HUSTOJ 的缓存代码
用的是**旧的 `Memcache` 扩展**（`new Memcache`）。直接置 `OJ_MEMCACHE=1` 会 `Fatal error` →
**全站 500**。

**要启用**需先补兼容层（把上游 `new Memcache` 的调用适配到 `Memcached` 类，或另装
`php-memcache` 扩展）；之后设 `OJ_MEMCACHE=1` 即可，接线无需再动。

---

## 6. 验收清单

部署完成后逐项执行，**全部通过才算完成**：

```bash
# 1) 容器全绿
docker compose ps
#    期望：web(healthy) db(healthy) judge(Up) cache(Up)

# 2) 端口归属（必须是 docker-proxy，而不是宿主的 nginx/mariadbd）
ss -lntp | grep -E ':80 |:3306'

# 3) 页面回归（全 200）
for p in index.php problemset.php status.php ranklist.php problem.php?id=1000 contest.php admin/; do
  printf '%-22s %s\n' "$p" "$(curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1/$p)"
done

# 4) 前端产物在位
curl -sS http://127.0.0.1/index.php | grep -o 'oj-bundle.css?v=[0-9.]*'    # 期望 oj-bundle.css?v=2026.3
curl -sS http://127.0.0.1/index.php | grep -c defer                        # 期望 5

# 5) 反爬与限流
#    命中判据一律 444 —— 连接被直接关闭，curl 显示的 http_code 是 000
curl -sS -o /dev/null -w 'GPTBot -> %{http_code}\n'    -A 'Mozilla/5.0 (compatible; GPTBot/1.4)'     http://127.0.0.1/  # 000
curl -sS -o /dev/null -w 'ClaudeBot -> %{http_code}\n' -A 'Mozilla/5.0 (compatible; ClaudeBot/1.0)' http://127.0.0.1/  # 000
curl -sS -o /dev/null -w 'Googlebot -> %{http_code}\n' -A 'Mozilla/5.0 (compatible; Googlebot/2.1)' http://127.0.0.1/  # 200
curl -sS -o /dev/null -w 'honeypot -> %{http_code}\n'  http://127.0.0.1/_oj_trap/export                                  # 000

# 6) 公网端到端（经 frp 隧道）
curl -sSL -o /dev/null -w '%{http_code} %{time_total}s\n' https://<你的域名>/index.php

# 7) 判题（见 4.5 的插入测试提交步骤，期望 result=4）
```

---

## 7. 日常运维

```bash
cd /opt/hnieoj-docker
export HOME=/root                     # 否则 compose 插件找不到

docker compose ps                     # 状态
docker compose logs -f web judge      # 跟随日志
docker compose restart web            # 重启单个服务
docker compose stop judge             # 暂停判题（例如维护）
docker stats --no-stream              # 资源占用

# 改 nginx 配置后热加载（目录挂载，无需重建容器）
docker exec hnieoj-web nginx -t && docker exec hnieoj-web nginx -s reload
# 改 php 配置后重载 FPM
docker exec hnieoj-web sh -c 'kill -USR2 $(cat /run/php/php8.1-fpm.pid)'

docker exec -it hnieoj-db mariadb -uroot -p"$(grep ^DB_ROOT_PASSWORD= .env | cut -d= -f2-)" jol
docker exec hnieoj-judge ps -eo pid,cmd | grep judged
```

**改动的生效方式**

| 改动内容 | 生效方式 |
|---|---|
| 站点源码（PHP/CSS/模板） | `docker compose restart web`（源码是 bind mount）；急用可 `kill -USR2` 重载 fpm |
| nginx 配置（`conf/nginx/`） | **目录挂载，热加载即可**：`docker exec hnieoj-web nginx -t && docker exec hnieoj-web nginx -s reload` |
| php 配置（`conf/php/`） | **目录挂载**：`docker exec hnieoj-web sh -c 'kill -USR2 $(cat /run/php/php8.1-fpm.pid)'` |
| compose.yaml / .env | `docker compose up -d --force-recreate` |
| 样式文件 | 递增 `template/syzoj/css.php` 里的 `$oj_ver`，否则浏览器 7 天强缓存不换 |

**备份**

```bash
# 数据库
RP=$(grep ^DB_ROOT_PASSWORD= .env | cut -d= -f2-)
docker exec hnieoj-db mariadb-dump --socket=/run/mysqld/mysqld.sock -uroot -p"$RP" \
  --single-transaction --routines --triggers --events --default-character-set=utf8mb4 jol \
  | gzip > /opt/hnieoj-docker/backup/jol-$(date +%F).sql.gz
# 配置与文档
tar czf /opt/hnieoj-docker/backup/hnieoj-docker-$(date +%F).tar.gz -C /opt hnieoj-docker --exclude=backup
```

---

## 8. 故障排查（部署中实际踩到的坑）

| # | 现象 | 根因 | 解决 |
|---|---|---|---|
| 1 | judge 容器**无限重启** | `judged` 内部 `daemon_init()` 会 fork 后让父进程退出，`exec judged` 使容器主进程立刻结束 | entrypoint 启动 judged 后**循环守护容器**（`while pgrep -x judged; do sleep 5; done`） |
| 2 | **宿主 judged 起不来** | 容器里的 judged 把 pid 写进宿主的 `/home/judge/etc/judge.pid`；宿主 `kill(pid,0)` 命中无关进程 → 判定"already running" | 挂载 `./conf/etc:/home/judge/etc` 隔离 pid 文件；回滚前 `rm -f /home/judge/etc/judge.pid` |
| 3 | 判题**永远卡在 `result=2`**（Compiling） | `judged` 执行 `docker run -v /home/judge:/home/judge hustoj`，路径由**宿主** dockerd 解析，沙箱里的 `judge_client` 读到的是**宿主**的 `judge.conf`（端口与容器 db 不一致）→ 找不到该提交 | 让 web / judged / 沙箱三方连**同一个库**（统一用 3306） |
| 4 | 切换后端口没变（还是 8080/3307） | **compose 读环境变量的优先级高于 `.env` 文件**；脚本里 `set -a; . ./.env` 之后再 `sed` 改 `.env` 无效 | 不要 source `.env`；只按需 `grep` 取变量 |
| 5 | `docker compose` 报 `unknown shorthand flag: 'f'` | 缺少 `HOME`，插件查找失败 | `export HOME=/root` |
| 6 | 容器里查中文显示 `??????` | 只是 `mariadb` **命令行客户端**的默认字符集 | 用 `hex(title)` 与源库比对即可确认数据无恙；需要可读时加 `--default-character-set=utf8mb4` |
| 7 | 镜像拉取极慢（~35KB/s） | 国内 Docker Hub 镜像源限速 | 全部基于本地 `ubuntu:22.04` 自建 + 中科大 apt 源 |
| 8 | 改了 PHP 不生效 | `opcache.revalidate_freq=60` | `docker compose restart web` |
| 9 | 页面还是旧的 | OJ 页面缓存在 `/tmp/hustoj_page_cache` | `docker exec hnieoj-web sh -c 'rm -rf /tmp/hustoj_page_cache/*'` |
| 10 | 判题全部 CE 或无输出 | `hustoj:latest` 沙箱镜像被删 | 保留该镜像（1.85GB），必要时用 `install/docker.sh` 重建 |

**快速定位**

```bash
docker compose ps                                     # 哪个容器不健康
docker logs --tail 30 hnieoj-judge                    # 判题日志
docker exec hnieoj-judge ps -eo pid,ppid,cmd          # 容器内进程
docker exec hnieoj-db mariadb-admin --socket=/run/mysqld/mysqld.sock -uroot -p"$RP" ping
docker exec hnieoj-web sh -c 'tail -20 /var/log/nginx/error.log'
```

---

## 9. 回滚到宿主机

```bash
# 1) 停容器栈
cd /opt/hnieoj-docker && docker compose down

# 2) 把容器库数据导回宿主（否则切换后的新提交会丢）
RP=$(grep ^DB_ROOT_PASSWORD= .env | cut -d= -f2-)
docker run --rm --network hnieoj_default -i hnieoj-db:2026.09 \
  mariadb-dump --socket=/run/mysqld/mysqld.sock -uroot -p"$RP" --single-transaction \
  --routines --triggers --default-character-set=utf8mb4 jol > /tmp/rollback-jol.sql
systemctl start mariadb && mysql -uroot jol < /tmp/rollback-jol.sql

# 3) 恢复宿主服务（并恢复开机自启）
rm -f /home/judge/etc/judge.pid
systemctl enable --now mariadb memcached nginx php8.1-fpm hustoj

# 4) 验证
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1/index.php
```

回滚后如需再次切回容器，重复 4.6 的步骤（注意先重新 `systemctl disable` 宿主服务）。

---

## 10. 附录

**关键路径**

| 用途 | 路径 |
|---|---|
| 部署目录 | `/opt/hnieoj-docker` |
| 站点源码 | `/home/judge/src/web` |
| 判题数据 | `/home/judge/data` |
| 判题配置 | `/home/judge/etc/judge.conf`（宿主）、`/opt/hnieoj-docker/conf/etc/judge.conf`（容器） |
| 判题二进制 | `/usr/bin/judged`、`/usr/bin/judge_client`（**静态链接**，可直接挂载） |
| 判题沙箱镜像 | `hustoj:latest`（**不可删**） |
| 页面缓存 | web 容器 `/tmp/hustoj_page_cache`（卷 `hnieoj_page-cache`） |
| 隧道配置 | `/usr/local/frp/frpc.toml`（`localPort=80`） |

**部署期备份**

| 路径 | 内容 |
|---|---|
| `/home/admin123/oj-optimize-backup-2026-09-10/` | web 快照、jol dump、routines、nginx/php/mysql 配置、图片原图 |
| `/opt/hnieoj-docker/backup/` | `db-jol-clean-20260910.sql.gz`（含存储过程）、`host-config-*.tar.gz`、`hustoj-core-*.tar.gz` |
| `/home/judge/src/web`（git） | `b5b3968` 优化前基线；`0da4d1e`/`25f67b2`/`0306561` 前端优化；`3fcd15b` db_info 环境变量支持；`ec17c17` memcached 接线 + 清理遗留；`5aa71a9` 反爬（蜜罐 / 反枚举） |
| `/opt/hnieoj-docker`（git） | `48884a9` 容器化资产；`40102d4` 反爬四层机制；`f8df06e` Dockerfile COPY 修正；`64cd374` www.conf memcached 透传 |

**刻意留在宿主机（不容器化）**

`frpc`（隧道）、`zerotier-one`（私网）、`sshd`、`fail2ban` —— 这些属于宿主机基础设施，容器化无收益且增加故障面。

**变更记录**

| 日期 | 内容 |
|---|---|
| 2026-09-10 | 首次容器化部署：web/db/cache/judge 四容器上线，生产流量切换完成 |
| 2026-09-10 | `conf/nginx/` 改**目录挂载**，根治单文件 bind mount 的 inode 陷阱（改配置不再需要重建容器） |
| 2026-09-10 | 反爬四层上线：官方 IP 段 + UA 判据、动态黑名单（枚举广度自动封禁）、匿名反枚举、蜜罐；frps 入口封禁 47 条 |
