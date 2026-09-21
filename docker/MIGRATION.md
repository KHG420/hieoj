# 生产迁移、备份与回滚

本方案先在隔离环境验证，随后于 2026-09-14 经授权完成生产切换和旧版删除，见 `PRODUCTION.md`。
以下保留为后续迁移、恢复时的操作原则。
不能把根 Compose 直接覆盖到旧生产编排目录：卷名、密码管理、源码来源与判题挂载均已变化。

## 现有卷升级：今日冒险金币（adventure_reward）

`coin_ledger.kind` 的旧 enum 不含 `adventure_reward`。只重建镜像或重启容器**不会**升级已有数据卷：
`docker/db/entrypoint.sh` 仅在全新安装时导入初始 SQL，旧卷上的 `coin_ledger` 保持原样。
不要为了这次升级重跑 `solutions-coins.sql`：`CREATE TABLE IF NOT EXISTS` 不会改动已存在的表，
而其中的历史首次通过回填是另一项独立行为。改用只扩展 enum 的聚焦迁移
`docker/db/adventure-coins.sql`（钱包余额、账本行、金额与 `one_event` 唯一键都不受影响，可重复执行）。

enum 升级不需要重建或重启数据库：直接在运行中的旧 db 容器上执行聚焦迁移，
确认 enum 扩展完成后，再部署新 web 镜像（新 web 会在入账时写入 `adventure_reward`）。

```bash
# 1. 把仓库中的聚焦迁移直接送进运行中的 db 容器（不重启数据库）。
#    若数据库已停止，先 docker compose up -d --wait db，--wait 依赖 db 的 healthcheck。
docker compose exec -T db sh -c 'MYSQL_PWD="$(cat /run/oj-secrets/root-password)" mariadb -uroot jol' < docker/db/adventure-coins.sql
# 2. 核对 enum 已包含 adventure_reward，再继续下一步。
docker compose exec -T db sh -c 'MYSQL_PWD="$(cat /run/oj-secrets/root-password)" mariadb -uroot jol -e "SHOW COLUMNS FROM coin_ledger LIKE \"kind\""'
# 3. 聚焦迁移确认后才部署新 web 镜像；--no-deps 不触碰 db，--wait 等健康检查通过。
docker compose up -d --no-deps --build --wait web
```

只有在 db 镜像本身也需要更新时（例如改用镜像内置的 `/opt/oj/adventure-coins.sql`）才重建 db；
升级已有卷时 entrypoint 不会自动执行迁移，仍需手动跑聚焦迁移：

```bash
docker compose up -d --build --wait db
docker compose exec -T db sh -c 'MYSQL_PWD="$(cat /run/oj-secrets/root-password)" mariadb -uroot jol < /opt/oj/adventure-coins.sql'
```

迁移末尾的两条只读查询用于人工复核，不会改写历史数据：
`empty_kind_rows` 是旧 enum 下被 `INSERT IGNORE` 静默写成空字符串、但钱包已加钱的账本行；
`duplicate_day_rewards` 是同一天被旧会话随机奖励 ID 重复发放冒险金币的用户。
发现结果后先备份并人工确认补偿方式，不要直接删改账本行。`ALTER TABLE` 期间会短暂锁写，建议在低峰执行。

## 备份范围

在旧生产服务器执行只读备份，并将备份复制到另一台机器，核对 SHA-256。
备份目录必须权限 700，SQL/凭据文件权限 600：

1. 数据库 `jol` 的完整逻辑导出（表结构、数据、触发器、事件）。
   现库有 MyISAM 表，**不能仅使用 --single-transaction 声称一致备份**。
   最终备份应在维护窗口停止写入，使用 `mariadb-dump --lock-all-tables`。
2. `/home/judge/data` 与 Web 的 `upload/`。
3. 两个原 Git 仓库的 bundle、未提交修改、未跟踪文件；Web 私密历史不得上传主仓库。
4. 旧 Compose、`.env`、`judge.conf`、Web 私密配置、Nginx/PHP 配置、反爬列表、
   当前镜像 ID 及可回滚的镜像导出。
5. 公网 Nginx、FRP 配置及对应服务状态。入口层不随本次仓库整理自动改动。

已有备份不能代替正式切换前的新备份。

## 先平行恢复，再切流量

1. 使用有访问权限的 Git 身份克隆主仓库到新目录，例如 `/opt/hnieoj`。不要删除两个旧目录。
2. 用**独立项目名和端口**初始化，避免碰到旧项目 `hnieoj`：

   ```bash
   COMPOSE_PROJECT_NAME=hnieoj-next WEB_PORT=18088 docker compose up -d --build
   ```

   后续命令始终使用同样的项目名。推荐在这个目录的 `.env` 中固定：
   `COMPOSE_PROJECT_NAME=hnieoj-next`、`WEB_PORT=18088`。
3. 待新库初始化完成，停止新栈 web、judge；旧生产继续运行。
4. 将**经确认只包含 jol 应用库**的备份导入新 db。不要导入 mysql 系统库，
   不要覆盖新实例的 root/hustoj 用户和密码。新环境使用其随机生成的连接密码。
   完整应用库恢复会覆盖新建管理员，以生产用户/权限为准；初始 admin 密码不再代表可登录凭据。
5. 通过只挂载新项目卷的临时容器恢复测试数据和上传文件。
   判题 UID/GID 是 1536；Web UID/GID 是 33。
   题目目录由 www-data 管理，判题器需要读取并按现有逻辑编译 SPJ。
6. 不要把生产 Web 源码覆盖进新容器；代码以仓库构建镜像为准。
   生产特有站点设置先逐项对照 `docker/web/configure.php` 和模板，敏感配置不得提交 Git。
   新镜像保留 Nginx 静态反爬、限流和安全规则。动态封禁脚本在 `docker/operations/`，
   通过 Web 容器运行，状态保存在 nginx-state 卷；生产 systemd timer 已适配。
   公网入口层的独立网段封禁服务保持原样；后续迁移仍需核对主机定时任务。
7. 启动新 web、judge，验证生产账号登录、管理员权限、题库/比赛/提交数量、
   知识地图、附件、普通判题及实际使用的 SPJ/语言。
   平行验证不得让两个 judge 同时连接同一生产数据库。
8. 重建/重启新容器后，再核对数据库、附件、测试数据与判题结果仍在。

导入前应先在隔离备份上演练；本文不提供会无条件覆盖生产数据的一键脚本。
初始化失败会保留现场并拒绝自动重建，检查日志与备份后再处理，禁止直接删除数据卷碰运气。

新装基线只导入应用表和知识地图定义，不自动启用旧库的存储过程/事件。
本地备份中的 `check_long_queries` 事件本来就是 DISABLED；它调用的过程会杀掉所有超时连接，
不应作为新环境的默认服务。旧 `DEFAULT_ADMINISTRATOR` 过程也不用于管理员初始化，
新管理员由首次启动脚本显式建立并设置审核通过标记。生产迁移仍应完整备份这些对象并逐项核对。

## 正式切换（需要维护窗口）

1. 停止提交入口与所有写入，等待在途判题完成，停旧 judge。
2. 做最终一致性数据库备份，并补齐测试数据/上传增量。
3. 在新栈恢复最终数据，核对统计，验证新 judge。
4. 只调整公网/FRP 上游到新 Web 端口，验证 HTTPS、登录和一次真实提交。
5. 记录新提交 ID、镜像 ID、Git commit、卷名、备份校验和与切换时间。
6. 保留旧目录和镜像作为**只读冷备**；确认稳定并获得清理授权后才归档/移除。

目标是只有 `/opt/hnieoj` 这一份**活动代码**；
冷备不参与挂载、构建或启动，不是第二套活动部署。

## 回滚

切流量前失败：停止新项目即可，旧生产完全不受影响，保留新卷排查。
切流量后失败：先冻结新栈写入并备份；新栈若已经产生提交/修改，
必须制定数据回灌方案，**不能直接启动旧数据库丢弃新数据**。
确认数据一致后恢复原公网/FRP 上游和旧栈。整个过程不要使用 `docker compose down -v`。

## 新部署日常备份

同时备份 db-data、credentials、judge-data、uploads、nginx-state；judge-runtime 可备份以保留诊断日志。
优先导出应用库逻辑 SQL；物理复制 MariaDB 卷必须先正常停止数据库或使用数据库认可的热备工具。
数据备份与 Git 代码备份分开管理，定期在独立项目上执行恢复演练。
