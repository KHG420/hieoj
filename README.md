# HnieOJ · 湖南工程学院在线评测系统

基于 HUSTOJ 持续维护的在线评测系统，面向程序设计教学、日常练习、竞赛与 ACM 实验室管理。项目包含 PHP 网站、C/C++ 判题程序、数据库初始化脚本及 Docker 运维配置。

**唯一部署入口是仓库根目录的 `compose.yaml`。** Web 从 `web/` 构建，判题器从 `core/` 编译，不依赖宿主机另一份源码或手工准备的旧判题镜像。

本文介绍系统功能、代码组织、运行架构，以及从空环境安装到日常维护的操作方法。特定服务器的配置与历史记录见文末文档索引。

## 目录

- [功能与代码入口](#功能与代码入口)
- [架构与运行边界](#架构与运行边界)
- [仓库结构](#仓库结构)
- [全新安装](#全新安装)
- [本地开发](#本地开发)
- [配置与数据库](#配置与数据库)
- [测试与验收](#测试与验收)
- [发布与生产运维](#发布与生产运维)
- [备份恢复与回滚](#备份恢复与回滚)
- [常见问题](#常见问题)
- [文档索引](#文档索引)

## 功能与代码入口

项目采用 PHP 页面入口加模板的组织方式，没有统一的前后端分离 API 层。默认模板为 `syzoj`；定位页面问题通常先看 `web/` 对应入口，再看 `web/template/syzoj/` 中的模板及资源。

| 业务 | 主要入口 / 实现 | 说明 |
|---|---|---|
| 题库、分类和题目 | `web/problemset.php`、`web/problem.php` | 可见性、比赛权限和筛选分页需一起检查 |
| 提交与评测状态 | `web/submitpage.php`、`web/submit.php`、`web/status.php` | Web 写入任务，判题器异步更新结果 |
| 比赛、作业与榜单 | `web/contest.php`、`web/contestrank*.php` | 包含 ACM、OI、Replay 和滚榜；时间、罚时和题号规则需一致 |
| 用户、注册与资料 | `web/registerpage.php`、`web/modifypage.php`、`web/userinfo.php` | 学院/班级、审核状态、个人统计与后台审批相关联 |
| 排名与实验室 | `web/ranklist.php`、`web/lab.php` | 申请、反馈、学院筛选与排名，见 [模块说明](docs/acm-lab-ranking.md) |
| 题解与金币 | `web/solutions.php`、`web/coins.php`、`web/include/editorial.inc.php` | 审核、首次 AC 奖励与付费解锁涉及事务、触发器和防重复记账 |
| 知识地图 | `web/knowledge_graph.php`、`web/include/knowledge_graph.inc.php` | 后台维护入口 `web/admin/knowledge_graph.php` |
| 今日冒险与反例挑战 | `web/adventure.php`、`web/include/adventure.inc.php`、`web/include/community_hunts.inc.php` | 包含远征、影子挑战、共同挑战与回忆录，见模块文档 |
| 讨论与公告 | `web/discuss.php`、`web/thread.php`、`web/viewnews.php` | 讨论相关逻辑亦在 `web/discuss3/`，公告过滤在公共函数中 |
| 管理后台 | `web/admin/` | 题目、测试数据、比赛、用户、权限与公告管理，写入需权限和令牌校验 |
| 公共配置与数据库 | `web/include/db_info.inc.php.example`、`web/include/pdo.php` | 运行时配置由容器生成，不直接修改容器内文件作为发布方式 |
| 判题调度 / 执行 | `core/judged/`、`core/judge_client/` | 调度、编译、运行、资源限制及结果回写 |

题目可见性与比赛权限共同决定用户能否访问和提交；评测结果用于个人统计、榜单和金币奖励；题解的审核与解锁由相应权限和账本记录控制。

## 架构与运行边界

```text
浏览器 ── HTTPS ── 反向代理 / HTTPS 入口（另行部署）
                           │
                    宿主机 Web 端口
                           │
              web：Nginx + PHP 8.1-FPM
                    │              │
             db：MariaDB      cache：Memcached
                    │
             judge：judged 调度器
                    │ Docker socket
                    ▼
            每次提交独立的判题沙箱
            编译 → 运行 → 比对 → 回写数据库
```

四个服务均由根 Compose 管理，基于 Ubuntu 22.04 构建。Web 资源主要直接随 PHP/JS/CSS 源码发布；根项目没有统一的 npm 构建流程，`web/blockly/package.json` 属于内置第三方子项目，不是整个站点的启动入口。

### 服务职责与提交链路

| 服务 | 职责 | 数据与通信 |
|---|---|---|
| `web` | Nginx 提供静态资源，PHP-FPM 处理页面、登录、业务操作与管理后台 | 访问 MariaDB，读写题目数据与上传卷；默认只向宿主机回环地址发布 HTTP 端口 |
| `db` | MariaDB 保存用户、题目元数据、比赛、提交源码和评测结果 | 通过 Compose 内部网络供 Web 与判题程序访问 |
| `judge` | `judged` 调度待评测任务，通过 Docker 启动 `judge_client` 沙箱 | 读取题目测试数据，编译并运行提交，写回评测结果 |
| `cache` | 提供 Memcached 服务 | 业务是否使用由 Web 配置决定，默认关闭 |

一次提交的处理过程：

1. 用户在 Web 选择题目和语言并提交源码，PHP 校验登录状态、题目或比赛权限及提交参数。
2. Web 将提交记录和源码写入数据库，页面显示等待评测状态。
3. `judged` 获取待处理任务，为任务启动独立沙箱，运行 `judge_client`。
4. 判题程序编译源码，使用题目数据执行测试，在时间、内存和系统调用限制下运行，并进行答案比对或 SPJ（特殊判题）。
5. 判题程序将状态、耗时、内存及错误信息写回数据库，Web 查询结果并展示提交状态和相关统计。

题目描述、限制和比赛关联保存在数据库；输入输出文件与 SPJ 等保存在 `judge-data` 卷。迁移或恢复题库时需要同时处理这两部分。

### 平台与运行限制

- **完整评测仅支持原生 Linux x86_64。** 默认安装 C、C++、Pascal、Java 17、Python 3、Clang、Clang++，前端只展示这些语言。
- 判题依赖 ptrace 和 Linux namespaces。当前 ARM 寄存器读取实现存在兼容问题，ARM 构建会拒绝继续；Apple Silicon / QEMU 模拟 x86 不属于已支持的完整判题环境。
- 调度容器挂载 Docker socket；提交沙箱不挂载该 socket、Web 源码或宿主机目录，通过独立 Compose 网络访问数据库，不使用 host network 或 privileged 模式，但仍需要 SYS_PTRACE / SYS_ADMIN 能力。
- Docker socket 具有宿主机管理级权限。运行不可信代码的判题环境应使用专用 Linux 主机，当前项目沿用 HUSTOJ 沙箱模型。
- `cache` 服务存在，但 Compose 当前设置 `OJ_MEMCACHE=0`；不能仅凭容器运行就判断业务缓存已开启。

## 仓库结构

```text
.
├── compose.yaml             # 唯一 Docker 编排入口
├── web/                     # PHP 页面、后台、模板和静态资源
│   ├── admin/               # 管理后台
│   ├── include/             # 配置模板、PDO、公共业务函数
│   ├── template/syzoj/      # 默认前台模板、JS 和 CSS
│   ├── tests/               # PHP、HTTP、浏览器及前端回归
│   └── upload/              # 运行时挂载上传卷，不提交 Git
├── core/                    # judged、judge_client、相似度检测源码
├── etc/                     # 判题配置模板、Java 策略等
├── docker/
│   ├── web/ db/ judge/ cache/ # 镜像与启动脚本
│   ├── conf/                # Nginx、PHP 与反爬配置
│   ├── db/*.sql             # 基线结构与功能初始化/迁移 SQL
│   ├── operations/          # 备份、反爬、蜜罐运维脚本
│   └── tests/               # 隔离完整栈验收及迁移测试
├── docs/                    # 业务文档、教程与历史记录
├── install/、deploy/        # 旧裸机部署素材，不作为当前入口
└── README.md                # 项目说明与部署指南
```

`docs/legacy/` 和 `OJ系统说明文档.md` 是历史参考。旧 `production-docker/`、`docker/hustoj*` 等入口已退役；不要按历史命令重新启动另一套生产服务。

## 全新安装

### 环境要求

- 原生 Linux x86_64 主机，Docker Engine 23+、Compose v2，启用 BuildKit。
- Git 仓库访问权限；首次构建需联网下载基础镜像和软件包。
- 预留镜像、数据库、题库、判题工作目录和备份所需磁盘空间；容量按实际题库及提交量规划。
- Node.js 20+ 仅用于开发验收，不是生产运行依赖。

### 1. 获取源码与配置端口

以下命令仅用于新环境，已有生产库恢复见 [备份恢复与回滚](#备份恢复与回滚)。

```bash
git clone git@github.com:KHG420/hieoj.git
cd hieoj
```

在仓库根目录创建 `.env`，设置监听地址、端口和数据库缓冲池大小。该文件不提交 Git：

```dotenv
WEB_BIND=127.0.0.1
WEB_PORT=8080
DB_BUFFER_POOL=512M
```

需要局域网直接访问时可将 `WEB_BIND` 改为 `0.0.0.0`，并按实际网络范围配置防火墙。默认设置适用于本机访问或由同机反向代理转发。

### 2. 构建并启动服务

以下命令从仓库根目录执行：

```bash
docker compose config --quiet
docker compose up -d --build --wait
docker compose ps
```

默认访问 <http://127.0.0.1:8080>。`db`、`web`、`judge` 应显示 healthy，`cache` 应处于运行状态。首次构建需要下载基础镜像、软件包并编译判题器，可通过 `docker compose logs --tail=100 db web judge` 排查启动问题。

新安装自动创建业务表、知识地图等基础定义及管理员，不含生产用户、题库、提交记录或测试数据。数据库连接密码自动生成并持久化。

### 3. 登录管理员并准备题库

读取随机生成的管理员初始密码：

```bash
docker compose exec db cat /run/oj-secrets/admin-password
```

用户名为 `admin`，登录后修改密码。此文件只保存首次初始化密码，不会随站内密码修改而更新；已有数据库中的管理员继续使用原有凭据。

进入管理后台创建或导入题目，配置时间与内存限制并上传测试数据。需要特殊判题的题目还应配置 SPJ。用一道结果明确的测试题提交程序，确认任务从等待状态变为实际评测结果；仅看到容器 healthy 不能证明判题链路正常。

### 4. 配置域名与 HTTPS

Compose 提供 HTTP 服务，域名解析、TLS 证书和公网反向代理需另行配置。同机代理将请求转发到 `http://127.0.0.1:8080`（端口与 `.env` 一致）；代理位于其他主机或容器时，应使用其可访问的宿主机地址，并相应调整 `WEB_BIND` 和网络访问规则。

公网接入后检查首页、登录、静态资源、附件上传和提交功能。MariaDB 和 Memcached 保持在内部网络，不发布到公网。FRP 仅在需要内网穿透的部署中另行配置。

## 本地开发

Apple Silicon / ARM Mac 只启动 Web、数据库与缓存。以下端口是示例，应选择未被占用的端口并写入本地 `.env`：

```dotenv
WEB_BIND=127.0.0.1
WEB_PORT=18888
```

```bash
docker compose up -d --build --wait db cache web
```

访问 <http://127.0.0.1:18888>。此环境可以验证 PHP 页面、数据库交互和表单，但没有判题器，提交不会因此自动得到真实判题结果。本地导入的生产备份也不会与线上实时同步。

代码被 COPY 到镜像，默认没有源码热挂载。修改 PHP、模板或静态资源后：

```bash
docker compose up -d --no-deps --build --wait web
```

只执行 `restart` 不会更新镜像中的代码。修改判题源码或编译环境后，需要在支持的平台重建 judge，并做真实判题回归。

开发时从具体页面入口沿公共函数、SQL、模板定位问题。使用 CodeGraph 的环境可先执行 `codegraph status`，尚未初始化时运行 `codegraph init`，再用 `codegraph node web/submit.php` 等命令查看调用关系；CodeGraph 不是项目运行依赖。

## 配置与数据库

### 配置来源

| 配置 | 维护位置与生效方式 |
|---|---|
| 端口、项目名、数据库缓冲池 | 根 `.env` 与 `compose.yaml`；修改后重新执行 Compose up |
| 站点名称、模板、功能默认值 | `web/include/db_info.inc.php.example`；修改后重建 Web |
| Web 部署覆盖项 | `docker/web/configure.php` 和 `docker/web/entrypoint.sh`；启动时生成 `web/include/db_info.inc.php` |
| 数据库密码 | `credentials` 卷中的 `db-password` / `root-password`；不手工改成与数据库不一致的值 |
| 判题语言、并发与运行配置 | `etc/judge.conf.example` 和 `docker/judge/entrypoint.sh`；后者覆盖部分模板值，当前 `OJ_RUNNING=2` |
| Nginx / PHP | `docker/conf/`；修改后重建 Web |
| 外部通知等集成 | 对照实际调用代码和部署环境配置，不能假定全部由 `.env` 管理 |

`.env` 并不是任意业务配置的通用入口，只有 Compose 或代码明确读取的变量才会生效。运行时 Web 配置和判题配置会在启动时重新生成，直接在容器内修改不能形成可复现部署。

`.gitignore` 已排除 `.env`、`web/include/db_info.inc.php`、`etc/judge.conf` 和上传目录等。数据库导出、凭据、用户附件和本地测试产物不应提交到仓库。

### 数据模型与升级

主库名为 `jol`。核心关系包括用户 `users` / 权限 `privilege`、题目 `problem`、比赛 `contest` / `contest_problem`、提交 `solution` 及源码、公告 `news`。新增业务表与触发器以 `docker/db/` SQL 和对应模块说明为准。

全新数据库入口依次导入 `schema.sql`、`knowledge-graph.sql`、`solutions-coins.sql`、`community-hunts.sql`、`acm-lab.sql`，再创建管理员。**已有数据库不会因重建容器自动执行这些初始化 SQL。** `editorial-markdown.sql` 等升级文件也不能仅因存在于镜像中就认为已应用。

升级前先检查目标库结构、对应功能文档及迁移脚本，在隔离环境演练并备份。金币功能依赖 `solution` 上的触发器，恢复时需要保留并核对触发器和 DEFINER；不要只恢复表数据。现库混用存储引擎，不能把整个数据库视为纯 InnoDB。

## 测试与验收

所有命令均从仓库根目录执行。选择与修改相关的最小测试；不要在生产上运行会创建夹具、重建容器或写入业务数据的测试。

### 逻辑回归与语法检查

在已启动 Web 的开发环境中：

```bash
docker compose exec -T web php /home/judge/src/web/tests/knowledge_graph_test.php
docker compose exec -T web php /home/judge/src/web/tests/news_filter_test.php
docker compose exec -T web php /home/judge/src/web/tests/registration_approval_test.php
docker compose exec -T -u www-data web php /home/judge/src/web/tests/adventure_test.php
# 将路径替换为本次修改的 PHP 文件
docker compose exec -T web php -l /home/judge/src/web/submit.php
git diff --check
```

单个注册审核逻辑测试使用隔离数据库和模拟邮件服务，不等同于真实邮件投递验收。

### 本地数据库夹具测试

仅在可丢弃或经授权的本地测试数据库执行，脚本使用临时记录并清理自己创建的夹具。运行前仍需确保所连环境正确及对应模块表已初始化。

```bash
docker compose exec -T -u www-data web php /home/judge/src/web/tests/lab_rank_test.php --fixtures
docker compose exec -T -u www-data web php /home/judge/src/web/tests/editorial_test.php --fixtures
docker compose exec -T -u www-data web php /home/judge/src/web/tests/profile_task_test.php --fixtures
docker compose exec -T -u www-data web php /home/judge/src/web/tests/page_boundaries_test.php --fixtures
docker compose exec -T web php /home/judge/src/web/tests/registration_approval_http_test.php --fixtures
docker compose exec -T -u www-data web php /home/judge/src/web/tests/adventure_http_test.php --fixtures
```

更多共同挑战、迁移和浏览器专项测试见 `web/tests/`、`docker/tests/` 及模块文档，部分脚本需要特定数据或原生判题环境，先阅读脚本开头要求。

### 历史数据只读回归

```bash
OJ_TEST_ORIGIN=http://127.0.0.1:18888 node web/tests/frontend-regression.mjs
OJ_TEST_ORIGIN=http://127.0.0.1:18888 node web/tests/live-audit-regression.mjs
```

这两份脚本面向恢复的 HnieOJ 数据或获准测试的线上站点，包含题目 1000、比赛 1163、公告 1016 等数据假设，**不能直接作为空库安装验收**。数据发生正常变化后应先核对断言前提，不能简单把失败认定为代码缺陷。

### 完整判题栈验收

在原生 Linux x86_64 上，使用独立空项目和未占用端口：

```bash
COMPOSE_PROJECT_NAME=hnieoj-unified-test WEB_PORT=18888 docker compose up -d --build --wait
COMPOSE_PROJECT_NAME=hnieoj-unified-test WEB_PORT=18888 node docker/tests/smoke.mjs
```

两条命令必须使用相同项目名和端口。脚本拒绝默认生产项目和包含真实用户/题目的数据库，会建测试题、提交各语言、验证 AC/WA/CE，并强制重建容器检查数据库、凭据、题目数据、上传和登录持久化。它不是只读健康检查，也不会自动删除数据卷。

### 发布后浏览器检查

`web/tests/form-controls.browser.js` 可通过 Playwright CLI 的 `run-code` 在已登录、含历史测试数据的浏览器会话中执行，覆盖语言记忆、回复/引用、班级输入切换及手机页面溢出；只操作浏览器字段，不发布内容或发送邮件。

至少检查匿名访问和登录、题库筛选/翻页、比赛与榜单、提交状态、公告、管理菜单，以及本次变更涉及的表单和错误分支。检查控制台异常、失败请求和图片加载；修改响应式布局时补测手机宽度。判题改动需验证真实提交和使用中的 SPJ，HTTP 200 或 judge 进程健康不能代替结果正确。

## 发布与生产运维

遵循 **本地修改与回归 → commit/push → 服务器 pull → 重建 → 线上验收**。不在生产容器中临时改文件作为正式修复。

检查差异、完成审核后，将已验证变更按团队流程合入并推送 `main`。服务器只拉取已推送的代码。

仅 Web 变更的发布示例：先进入服务器上的仓库根目录，再在具备仓库读取与 Docker 操作权限的 shell 中执行：

```bash
git status --short
bash docker/operations/backup.sh
umask 022
git pull --ff-only origin main
docker compose up -d --no-deps --build --wait web
git rev-parse HEAD
docker compose ps
docker compose logs --tail=100 web
```

拉取前工作区应干净；有未提交内容或分支分叉时先调查，不能用强制重置覆盖。备份可能短暂锁表，应安排合适窗口。Web 重建可能造成短暂不可用，此方案不承诺零停机。

若涉及 judge、数据库镜像或全栈配置，需要另行安排对应服务更新；判题更新先处理在途任务，涉及迁移时按模块文档安排停写。通用全栈更新命令为 `docker compose up -d --build --wait`，不应对仅 Web 修改无必要地重建全部服务。

常用只读检查：

```bash
docker compose ps
docker compose logs --tail=100 db web judge
docker compose exec -T web nginx -t
docker compose exec -T web php /opt/oj/healthcheck.php
docker compose exec -T judge sh -c 'ls -lah /home/judge/log'
docker system df
df -h
```

判题详细日志在 `judge-runtime` 卷的 `/home/judge/log`。健康检查分别验证数据库初始化与连接、Web 健康脚本和 judged 进程；仍需业务验收。

定时备份、反爬维护、公网代理、FRP 和防火墙属于宿主机运维配置，克隆仓库并启动 Compose 不会自动安装。按部署需求配置这些任务，并检查执行日志和备份结果。

## 备份恢复与回滚

### 持久化数据

| Compose 卷 | 内容 | 容器位置 |
|---|---|---|
| `db-data` | MariaDB 数据 | db：`/var/lib/mysql` |
| `credentials` | 数据库和初始管理员密码 | `/run/oj-secrets` |
| `judge-data` | 输入输出文件、SPJ 等题目数据 | `/home/judge/data` |
| `uploads` | 用户上传与附件 | web：`/home/judge/src/web/upload` |
| `judge-runtime` | 判题配置、工作目录和日志 | judge：`/home/judge` |
| `nginx-state` | 动态反爬封禁状态 | web：`/var/lib/oj-nginx` |

实际卷名受 Compose 项目名影响，以 `docker compose config` 为准。更改项目名会指向另一套数据；只复制源码无法迁移这些卷。页面缓存 tmpfs 为临时数据。

`docker compose down` 保留卷；**生产禁止执行 `docker compose down -v` 或删除上述业务卷来排障**。

### 日常备份

在仓库根目录执行：

```bash
sudo bash docker/operations/backup.sh
```

脚本生成时间戳目录，包含 `jol.sql.gz`、`data-upload.tar.gz`、`credentials.tar.gz`、`nginx-state.tar.gz`、代码提交号与 `SHA256SUMS`。数据库导出使用 `--lock-all-tables`，并包含例程、事件和触发器。

将下列路径替换为脚本刚输出的真实备份目录后，检查校验和与压缩完整性：

```bash
backup_dir=/var/backups/hnieoj-unified/替换为实际备份时间
sudo sha256sum -c "$backup_dir/SHA256SUMS"
sudo sh -c 'gzip -t "$1"/*.gz' sh "$backup_dir"
```

日常脚本不备份外部代理/FRP 配置、镜像或判题运行日志，也不自动异机同步或删除旧备份。附件备份期间可能有并发写入，正式迁移仍需维护窗口停写一致性备份；不能只用 `--single-transaction` 声称含 MyISAM 的全库一致。

部署时应安排异机备份、保留周期、磁盘容量监测和定期恢复演练。备份含真实数据和凭据，按私密数据管理。

### 恢复与回滚原则

恢复步骤详见 [迁移与回滚](docker/MIGRATION.md)。先使用独立项目名和端口恢复，只导入 `jol` 应用库，不覆盖新实例的 MySQL 系统库及连接密码。数据库、题目文件和上传文件应来自同一停写备份，并核对数量、校验和、权限和触发器，再验证登录、普通题与 SPJ 判题。

纯代码回滚也应先记录当前提交和镜像、冻结必要写入并备份，确认旧代码与现库兼容后再重建或恢复保留镜像。若新版已产生业务数据，不能直接用旧库覆盖；涉及数据库回退必须先制定增量处理方案。

## 常见问题

| 现象 | 排查顺序 |
|---|---|
| 修改代码后页面没有变化 | 确认本地提交、服务器 HEAD 和构建目录，再重建 Web；单独 restart 无效 |
| 新启动后看不到原用户/题库 | 检查 Compose 项目名、卷和是否完成授权数据恢复；空库初始化不会自带生产数据 |
| DB 启动拒绝初始化 | 查看 db 日志，核对凭据卷和 `.oj-initialized`；不要删除卷或手工伪造标记跳过检查 |
| Mac 提交一直等待 / judge 构建报 ARM 不支持 | 本地 Web 栈没有可用判题器，改在原生 Linux x86_64 验证 |
| 线上提交排队 | 检查 judge 状态、日志、Docker socket、沙箱镜像、磁盘与数据卷；不要直接批量重判 |
| 后台保存审批后提示邮件失败 | 先确认审批是否已保存，再检查邮件服务地址、连通性与配置；勿重复批准来重发通知 |
| PHP 文件存在但返回 404 / 无法读取 | 检查镜像内路径与文件权限，尤其拉取时 umask；再检查 Nginx 路由和日志 |
| 公网访问被拒绝、内网健康检查正常 | 分别核对公网 Nginx / FRP、动态反爬和防火墙；服务器出口 IP 也可能命中已有封禁 |
| 新模块提示缺表 | 对照目标库与模块迁移文档；已有卷不会自动重跑首次初始化 |
| 冒险进度退出登录后消失 | 远征路线、影子计时等保存在会话，退出/过期会清除，真实提交仍保留 |
| 只读回归在空库失败 | 检查脚本数据前提；空库应使用独立完整栈 smoke 验收 |

## 文档索引

| 文档 | 用途 |
|---|---|
| [Docker 入口说明](docker/README.md) | 当前编排入口 |
| [本地环境记录](docker/LOCAL.md) | 原维护者 Mac 环境、数据与历史归档 |
| [生产记录](docker/PRODUCTION.md) | 切换、线上配置、备份、反爬与审批修复历史 |
| [迁移与回滚](docker/MIGRATION.md) | 数据恢复、切换和一致性要求 |
| [隔离验收记录](docker/VERIFICATION.md) | 完整栈测试结果和平台边界 |
| [题解与金币](docs/solutions-coins.md) | 业务规则、事务、触发器和升级 |
| [实验室与排名](docs/acm-lab-ranking.md) | 申请管理和排名说明 |
| [今日冒险](docs/ADVENTURE.md) | 玩法、会话状态和测试 |
| [共同挑战界面](docs/COMMUNITY_HUNTS_UI.md) | 共同挑战界面说明；设计计划需对照当前实现 |
| [Markdown 教程](docs/tutorials/markdown-beginner.md) / [LaTeX 教程](docs/tutorials/latex-beginner.md) | 内容编写参考 |

仓库包含 HUSTOJ 衍生代码及 Ace、Blockly、KindEditor、Popper、SIM 等第三方组件。修改和再分发时保留各文件及组件自带的版权和许可证说明；本 README 不为整仓新增统一许可授权。
