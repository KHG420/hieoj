# HnieOJ

一套源码，一个部署入口：**根目录 `compose.yaml`**。
Web 来自 `web/`，判题程序从 `core/` 编译；不再依赖服务器上的另一套源码、预编译二进制或手工准备的 `hustoj:latest`。

2026-09-14 已完成生产切换并删除旧部署。当前服务器路径、备份和验收见[生产记录](docker/PRODUCTION.md)。
本地也已整理为独立仓库，ARM Mac 的启动方式和数据边界见[本地说明](docker/LOCAL.md)。

## 一条命令启动

在**原生 Linux x86_64 主机**上安装启用 BuildKit 的 Docker Engine 23+ 和 Compose v2 后：

```bash
git clone git@github.com:KHG420/hieoj.git
cd hieoj
docker compose up -d --build
```

获取源码需要该仓库的 Git 访问权限；Docker 启动本身不依赖 Git 凭据。

首次构建需要联网下载 Ubuntu 基础镜像和软件包。x86_64 默认使用中科大 Ubuntu 镜像源，四个镜像共享分架构 APT 下载缓存并启用重试，避免重复下载及瞬时网络错误。启动后访问 **http://127.0.0.1:8080**。
等待 `docker compose ps` 中 db、web、judge 显示 healthy。
管理员用户名为 `admin`，首次启动随机生成密码，取出方式：

```bash
docker compose exec db cat /run/oj-secrets/admin-password
```

登录后请在站内修改密码。上述文件是**首次生成的密码**，不会随站内密码变更而更新。
数据库密码同样随机生成并持久化，不写入 Git，也不打印到日志。
新安装只有管理员和知识地图基础定义，不含生产用户、题库、提交记录及测试数据。
默认项目名是 `hnieoj-unified`，与旧生产项目 `hnieoj` 隔离，不会接管旧项目容器和数据卷。

默认仅监听本机回环地址。需要给反向代理或局域网访问时，在根目录 `.env` 中配置：

```dotenv
WEB_BIND=0.0.0.0
WEB_PORT=8080
DB_BUFFER_POOL=512M
```

再执行同一条启动命令即可。公网部署需要自行配置域名、HTTPS 和防火墙；
不开放 MariaDB、Memcached 端口。已有线上数据库不能当作“全新安装”直接启动，见[迁移说明](docker/MIGRATION.md)。

## 更新与运行数据

```bash
git pull --ff-only
docker compose up -d --build
docker compose ps
docker compose logs --tail=100 web judge
```

代码打进镜像，修改代码后必须重新 build；不再通过服务器上两份目录同步或覆盖文件。
`docker compose restart` 不会把新的源码打入镜像。

数据存放在独立 Docker volumes：

| 数据卷 | 内容 |
|---|---|
| db-data | MariaDB 数据 |
| credentials | 自动生成的数据库、初始管理员密码 |
| judge-data | 题目输入输出、SPJ 文件 |
| uploads | 用户上传 |
| judge-runtime | 判题配置、工作目录和日志 |
| nginx-state | 动态反爬封禁状态 |

`docker compose down` 不删除这些数据；**不要执行 `down -v` 或清理这些 volumes**，否则会丢数据。
保持同一 Compose 项目名，改项目名会启动独立空环境。
备份和生产切换流程见[迁移与回滚](docker/MIGRATION.md)。

## 判题与隔离

保留现有 HUSTOJ 的每次提交独立 Docker 沙箱机制。调度容器使用 Docker socket；
提交沙箱只挂载判题工作卷和题目数据卷，**不挂载 Docker socket、Web 源码或宿主机目录**，
通过独立 Compose 网络连接数据库，不使用 host network，也不使用 privileged 容器。

默认构建安装 C、C++、Pascal、Java 17、Python 3、Clang、Clang++；网页仅展示这些语言。
判题器使用 ptrace 和 Linux namespaces，完整判题栈仅支持**原生 Linux x86_64**。
现有上游 ARM 寄存器读取实现会把正常程序误判为 RE，因此 ARM 构建会提前报错，
不通过放开系统调用白名单绕过问题。Apple Silicon / QEMU 模拟 x86 也不作为受支持的判题环境；
Mac 可以单独运行 Web 开发环境，但不能据此宣称完整判题已验证。

Docker socket 相当于宿主机管理权限，判题仍需 SYS_PTRACE/SYS_ADMIN 能力；
OJ 是运行不可信代码的系统，生产应部署在专用 Linux 主机，不应与敏感服务共享 Docker 引擎。
这次整理没有重写 HUSTOJ 沙箱的安全模型。

## 唯一维护路径

- `web/`：站点源码。
- `core/`：判题与相似度检测源码。
- `compose.yaml` + `docker/`：当前唯一 Docker 部署方案、配置、初始化及验收脚本。
- `docker/db/schema.sql`：2026-09-13 从本地生产备份数据库提取的纯表结构，不含生产数据及自增计数。
- `etc/`：判题配置模板。
- `install/`、`deploy/`：旧裸机维护素材，不是当前部署入口。
- `docs/legacy/`、`OJ系统说明文档.md`：历史记录，不应照其中旧命令新建服务。

旧 `production-docker/`、两套 `docker/hustoj*` 和 `install/Dockerfile` 部署入口已合并/退役，
历史版本可从 Git 恢复。不要把生产环境含私密配置的 Web Git 历史合并到主仓库。

## 隔离验收

已执行的环境、结果和支持边界见[验收记录](docker/VERIFICATION.md)。

开发验收需要 Node.js 20+，它不是生产运行依赖。只在空的测试项目执行：

```bash
COMPOSE_PROJECT_NAME=hnieoj-unified-test WEB_PORT=18888 docker compose up -d --build --wait
COMPOSE_PROJECT_NAME=hnieoj-unified-test WEB_PORT=18888 node docker/tests/smoke.mjs
```

脚本检查正常登录、后台建题、缺失/错误令牌拒绝、各已安装语言的真实 HTTP 提交、AC/WA/CE、
知识地图回归，以及强制重建容器后的数据库、初始口令、测试数据、上传文件和登录持久化。
脚本拒绝默认生产项目名及包含实际用户/题目的数据库，不会自动删除数据卷。
