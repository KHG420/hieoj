> 历史归档：以下命令对应旧生产布局，不适用于统一仓库的新部署。当前入口见根目录 README 和 compose.yaml；切换流程见 docker/MIGRATION.md。

# HnieOJ 容器化部署（/opt/hnieoj-docker）

2026-09-10 完成从「宿主机直装」到「docker compose 容器栈」的迁移。宿主机原部署**完整保留**，作为冷备与回滚路径。

## 一、架构

```
浏览器 → frps(公网 42.194.237.240) → frpc(宿主机, localPort=80) → hnieoj-web(80)
                                                                    ├── hnieoj-db(3306)      mariadb 10.6
                                                                    └── hnieoj-cache(11212)  memcached（未启用）
hnieoj-judge(host 网络 + /var/run/docker.sock) → 宿主 dockerd → hustoj:latest 判题沙箱容器
```

| 服务 | 镜像 | 网络 | 端口 | 关键挂载 |
|---|---|---|---|---|
| hnieoj-web | hnieoj-web:2026.09（nginx+php8.1） | bridge | `80:80` | `/home/judge/src/web`（源码）、`conf/nginx/`、`conf/php/` |
| hnieoj-db | hnieoj-db:2026.09（自建 mariadb 10.6） | bridge | `127.0.0.1:3306:3306` | 卷 `db-data` |
| hnieoj-cache | hnieoj-cache:2026.09（memcached） | bridge | `127.0.0.1:11212:11211` | — ⚠️ **当前刻意未启用**，见第四节 |
| hnieoj-judge | hnieoj-judge:2026.09 | **host** | — | `/home/judge`、`/var/run/docker.sock`、`/usr/bin/judged`、`/usr/bin/judge_client`、`conf/etc`（隔离 judge.pid） |

镜像全部基于 `ubuntu:22.04` 自建（不走 Docker Hub：国内 registry 镜像源只有 ~35KB/s）。

> `conf/nginx/` 下 `conf.d/` 与 `snippets/` 是**目录挂载**（2026-09-10 改造），
> 因此改配置**不需要重建容器**，热加载即可 —— 详见 `README-operations.md`。

## 二、常用运维

```bash
cd /opt/hnieoj-docker
export HOME=/root                      # 否则 compose 插件找不到
docker compose ps                      # 状态
docker compose logs -f judge           # 判题日志
docker compose restart web             # 重启单个服务
docker exec hnieoj-web nginx -t && docker exec hnieoj-web nginx -s reload   # 改 nginx 配置后热加载
docker exec -it hnieoj-db mariadb -uroot -p"$RP" jol   # 进数据库
docker exec hnieoj-judge ps -eo pid,cmd | grep judged  # 看判题进程
```

**改动的生效方式：**

| 改动内容 | 生效方式 |
|---|---|
| 站点源码（PHP/CSS/模板） | `docker compose restart web`（源码是 bind mount）；急用可 `kill -USR2` 重载 fpm |
| nginx 配置（`conf/nginx/`） | **目录挂载，热加载即可**：`docker exec hnieoj-web nginx -t && docker exec hnieoj-web nginx -s reload` |
| php 配置（`conf/php/`） | **目录挂载**：`docker exec hnieoj-web sh -c 'kill -USR2 $(cat /run/php/php8.1-fpm.pid)'` |
| compose.yaml / .env | `docker compose up -d --force-recreate` |
| 样式文件 | 递增 `template/syzoj/css.php` 里的 `$oj_ver`，否则浏览器 7 天强缓存不换 |

> ⚠️ 改 **compose 配置**时注意：**环境变量优先级高于 `.env` 文件** —— 若脚本里 source 过 `.env`，
> 之后改 `.env` 文件不会生效。

## 三、回滚到宿主机（重要）

```bash
# 1) 停容器栈
cd /opt/hnieoj-docker && docker compose down

# 2) 把容器库的数据导回宿主（否则切换期间的新提交会丢）
RP=$(grep ^DB_ROOT_PASSWORD= .env | cut -d= -f2-)
docker run --rm --network hnieoj_default -i hnieoj-db:2026.09 \
  mariadb-dump --socket=/run/mysqld/mysqld.sock -uroot -p"$RP" --single-transaction \
  --routines --triggers --default-character-set=utf8mb4 jol > /tmp/rollback-jol.sql
systemctl start mariadb && mysql -uroot jol < /tmp/rollback-jol.sql

# 3) 恢复宿主服务（含重新设开机自启）
rm -f /home/judge/etc/judge.pid        # 清掉可能残留的脏 pid
systemctl enable --now mariadb memcached nginx php8.1-fpm hustoj

# 4) 验证
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1/index.php
```

## 四、memcached 为什么没启用

`hnieoj-cache` 容器**照常在跑**（`cache/` 镜像 + `127.0.0.1:11212` 映射都在），但 web 侧**
刻意把开关关着**：

- `compose.yaml` 里 `OJ_MEMCACHE: "0"`；
- `conf/php/www.conf` 已备好 `env[OJ_MEMCACHE]` / `env[OJ_MEMSERVER]` / `env[OJ_MEMPORT]` 透传
  （`clear_env=yes` 会清空环境变量，必须显式声明）；
- `include/db_info.inc.php` 已支持用环境变量覆盖，且默认仍为 `false`。

**为什么不打开**：镜像里装的是 **`php8.1-memcached`（提供 `Memcached` 类）**，
而上游 HUSTOJ 的缓存代码用的是**旧的 `Memcache` 扩展**（`new Memcache`）。
直接开 `OJ_MEMCACHE=1` 会 `Fatal error` → **全站 500**。

**要启用需先做兼容层**（把上游 `new Memcache` 的调用适配到 `Memcached` 类，或额外编译
`php-memcache` 扩展），改完再设 `OJ_MEMCACHE=1` 即可，接线不必再动。

## 五、必须知道的坑（迁移中实际踩到）

1. **judged 会 daemonize**（`daemon_init()`，fork 后父进程退出）。容器里 `exec judged` 会让容器立刻退出并无限重启 —— entrypoint 必须启动后循环守护。
2. **judge.pid 隔离**：judged 硬编码写 `/home/judge/etc/judge.pid`。容器写宿主文件会让**宿主 judged 无法启动**（它读到 pid 后 `kill(pid,0)` 命中无关进程 → "already running"）。所以容器挂载 `conf/etc` 覆盖 `/home/judge/etc`。
3. **judge_client 跑在沙箱容器里**，它读的是**宿主机**的 `/home/judge/etc/judge.conf`（judged 传 `-v /home/judge:/home/judge`，路径由宿主 dockerd 解析）。所以 **web / judged / 沙箱 judge_client 必须连同一个数据库**，否则判题会卡在 `result=2`（Compiling）。
4. **compose 环境变量优先于 `.env` 文件**：脚本里 `set -a; . ./.env` 之后再 `sed` 改 `.env` 是无效的。
5. **判题沙箱镜像 `hustoj:latest` 不能删**（1.85GB），judged 每次判题都拉起它。删除会导致全部提交 CE/无法判题。
6. **测试数据命名**：HUSTOJ 扫描题目数据目录下**所有 `*.in`**（不限于 `1.in`），`sample.in/out` 也会参与；`ac/` 目录存的是 AC 代码不是数据。
7. **导入 dump 的字符集**：`mariadb --default-character-set=utf8mb4`。用完可用 `select hex(title)` 与源库比对（命令行显示 `??????` 常常只是客户端字符集，数据本身未必坏）。
8. **改 PHP 后**：`docker compose restart web` + 清页面缓存 `docker exec hnieoj-web sh -c 'rm -rf /tmp/hustoj_page_cache/*'`。
9. **静态资源版本号**：改样式后递增 `template/syzoj/css.php` 里的 `$oj_ver`，否则浏览器 7 天强缓存不换。
10. **单文件 bind mount 绑定的是 inode 而非路径**（2026-09-10 踩了约 1 小时）。
    原先 `conf/nginx/*.conf` 逐个挂载，用 `vim`/`sed -i` 改写会换 inode，容器仍持旧 inode ——
    `nginx -t` 通过、`reload` 报成功但**配置完全不生效**。现已改为**目录挂载**根治；
    历史细节见 `README-operations.md`。

## 六、备份

- 切换前全量：`/home/admin123/oj-optimize-backup-2026-09-10/`（web 快照 + jol dump + routines + 配置 + 图片原图）
- 容器化过程备份：`/opt/hnieoj-docker/backup/`（`db-jol-clean-20260910.sql.gz` 95MB 含存储过程、`host-config-*.tar.gz`、`hustoj-core-*.tar.gz`）
- 站点源码有独立 git：`/home/judge/src/web`
- 本目录也有 git：`/opt/hnieoj-docker`（两个仓库都**未配 remote**，提交只在本地）

## 七、反爬（2026-09-10 建立）

四层机制（官方 IP 段 / UA / 枚举广度自动封禁 / 蜜罐），完整说明见 **`README-anti-crawler.md`**，
运维与回滚见 `README-operations.md`。一键关停自动封禁：

```bash
touch /opt/hnieoj-docker/run/crawler-guard.disabled    # 转为只观察不封禁
```

蜜罐命中单独记在容器内 `/var/log/nginx/honeypot.log`；被拦请求按设计**不写**
access.log，所以日志变"干净"属正常现象。

## 八、宿主机保留项（未容器化，刻意如此）

`frpc`（隧道）、`zerotier-one`（两个私网）、`sshd`、`fail2ban` 继续跑在宿主机。宿主 `nginx/php8.1-fpm/mariadb/memcached/hustoj` 已 `disable` 并停止，作为冷备，**不要卸载**。
