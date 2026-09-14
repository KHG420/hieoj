# 生产切换记录：2026-09-14

## 唯一活动部署

- 内网服务器：`10.95.222.28`，代码：`/opt/hnieoj`，分支 `main`。
- Compose 项目：`hnieoj-unified`。生产 `.env` 保存在服务器，未提交 Git。
- Web 监听 `127.0.0.1:80`。公网 Nginx / FRP 链路保持原配置，流量已到新版。
- 首次切换时业务代码提交：`94d9157180b3e3e5567e2ea2743c4e59d58d6933`；后续更新见下方记录。

日常启动/更新：

```bash
cd /opt/hnieoj
git pull --ff-only
docker compose up -d --build
docker compose ps
```

生产导入了原用户及权限，使用原账号密码；不要把新装初始化文件中的 admin 密码当作生产账号密码。
完整判题支持原生 Linux x86_64，默认语言为 C/C++/Pascal/Java/Python/Clang/Clang++。

## 切换与验收

旧 Web 于北京时间 00:04 停止写入，新 Web 于约 00:14 接回公网端口。
等待旧判题队列为空后停止旧 judge，使用 `--lock-all-tables` 导出 jol（含例程、事件、触发器），
没有用仅适用于事务表的快照选项声称 MyISAM 一致性。

- 恢复前后所有应用表的 `CHECKSUM TABLE ... EXTENDED` 一致。
- 原始基线：12,120 用户、1,154 题、153 比赛、541,379 提交。
- 题目数据和上传文件从同一次停写备份恢复，并通过 `tar --compare` 逐项比较。
- 生产账号正常登录，首页、题库、比赛、状态、知识地图可访问。
- 普通题 1000：提交 `542514` AC；SPJ 题 4369：提交 `542515` AC。
- 正式域名浏览器登录、填写代码、提交：`542516` AC。
- 三条验收提交保留在授权测试账号中；因此提交总数在基线上增加 3。
- 切换到生产端口重建 Web 后再次登录、提交成功，知识地图 PHP 回归通过。
- 新版每日备份脚本实跑成功，所有压缩包及 SHA-256 校验通过。

## 已删除的旧版

- 旧 `hnieoj-web / judge / db / cache` 容器及旧项目数据卷、镜像、旧 `hustoj:latest` 沙箱镜像。
- `/home/judge/src`（包括旧 Web、core 及目录中的历史重复副本）、`/opt/hnieoj-docker`。
- 原宿主机 `/home/judge/data`、`/home/judge/etc`；数据已迁入新项目卷，不再依赖宿主机路径。
- 旧 `/usr/bin/judged`、`/usr/bin/judge_client`、SysV 启动脚本；旧开机启动项已停用。
- 隔离验收项目的容器、卷、镜像和临时源码目录。

旧内容只保留为压缩备份，不参与运行、挂载或构建。不能再直接启动旧栈回滚：
新版已经有新提交，恢复前必须冻结写入并处理增量，禁止用切换前数据库覆盖新数据。

## 备份位置

内网完整切换备份（目录 700，敏感文件仅 root 可读）：

`/home/admin123/hnieoj-backups/cutover-20260914/`

异机副本（含数据库、题目/附件、旧代码、旧镜像、私密 Git bundle、配置及新数据库凭据）：

`42.194.237.240:/home/ubuntu/hnieoj-retired-20260914/`

关键归档均已逐一核对两端 SHA-256：

| 文件 | SHA-256 |
|---|---|
| jol-final.sql.gz | a60a3bdbd0dc92f2128815d6e5a796e41f49a69ac743eae2a08d12f2fab5633e |
| data-upload-final.tar.gz | 59727f95250cf97b5caf70910b2f9fec49c857c50c9c54011bc8a6611807a32b |
| old-code-config.tar.gz | 387d55f9b9a5099f188ac2661c9c124e1426f04309f1287f1b0276e540cb1591 |
| old-images.tar.gz | 62bd9fc7a867580d5a61ea4e9cf1290ab718f3c12bb6e6b7a531d2046169f3d3 |
| old-sandbox-image.tar.gz | 43b4295d507e698bcda307c740c2a914ce9db347f8c38d5cac070a6f76abc6d5 |

私密配置和旧 Web Git 历史不得上传代码仓库。

## 定时运维

- `hnieoj-crawler-guard.timer` 每 30 秒运行已适配的容器内反爬/蜜罐脚本，已验证成功。
- root crontab 每日 00:01 执行 `bash /opt/hnieoj/docker/operations/backup.sh`。
- 日常备份输出 `/var/backups/hnieoj-unified/<时间>/`，日志 `/var/log/hnieoj-backup.log`。
- 首次实跑备份：`/var/backups/hnieoj-unified/20260914-001642/`。
- 日常脚本备份数据库、题目/附件、凭据、动态封禁状态及代码提交号；不清理业务记录。
  运行中附件有并发写入可能，正式迁移仍需停写一致性备份。
- 本次切换备份已异机保存；**每日任务目前只生成本机备份，不自动异机同步或删除历史备份**。
  需按实际磁盘容量管理保留周期。旧的第三方主机同步/清理脚本不再执行。

## 补充清理：仅保留一份展开的站点源码

2026-09-14 再次盘点后，将未参与生产的 `/home/admin123/src`、`/home/admin123/HnieOJ`
及 `/home/judge` 中散落的历史文件完整归档、逐文件比较、异机校验后删除。
旧副本中未提交的文档修改和未跟踪文件均保存在归档中，没有直接合入生产代码。
仅保留 `/opt/hnieoj` 为站点源码目录；第三方工具库、系统用户配置不在清理范围。

- 内网归档：`/home/admin123/hnieoj-backups/single-source-20260914/retired-copies.tar.gz`
- 异机归档：`42.194.237.240:/home/ubuntu/hnieoj-retired-20260914/additional-retired-copies.tar.gz`
- 两端 SHA-256：`afc27408d31ce5f58c70320d537e9699162d9dfe8a3d91dc4016151333e81b54`
- 此次清理未重启生产容器，公开访问及内网健康检查正常。

检查时服务器出口 IP `58.45.191.117` 命中切换前已有的动态封禁规则，
从服务器回访公网域名会被拦截；该现象不是此次目录清理导致，规则未擅自修改。

## 单个注册申请审批卡死修复：2026-09-14

业务代码提交：`645f3cf383392ca589460cfc9cb50c8aa27d4800`。

原因：`user_register_change.php` 保存审批结果后，同步访问旧邮件微服务，
超时设为 900 秒且仍持有 PHP 文件会话锁。同一管理员的其他请求也会等待，
最终可能先触发 Nginx/PHP 超时。现场检查旧微服务连接超时；本地模拟服务接收连接但
不返回响应时，原实现超过 6 秒仍未结束，且会话文件无法加锁。

修复仅涉及单个注册申请处理，不新增服务、依赖或表结构：

- 校验权限及一次性令牌后释放会话锁。
- 使用镜像已有的 cURL，连接超时 1 秒、请求总超时 3 秒。
- 分别提示审批保存结果和邮件通知结果；邮件失败不撤销审批。
- 只处理待审核账号，条件写入防止并发或旧链接误删已通过账号。
- 明确返回申请列表，不依赖浏览器历史缓存判断审批结果。

执行顺序：本地复现和修复、本地镜像回归、commit/push、生产备份校验、
生产 pull、只重建 Web、生产回归。服务器 Git 元数据部分由 root 管理，SSH 凭据属于
admin123，本次在 root shell 执行：

```bash
umask 022
git -c core.sshCommand='sudo -u admin123 ssh' pull --ff-only origin main
docker compose up -d --no-deps --build --wait web
```

备份：`/var/backups/hnieoj-unified/20260914-090608/`，包括完整 SQL、题目/上传、
凭据、动态封禁状态、旧提交号和 `code-before.bundle`。压缩包 SHA-256 与 gzip 校验通过。
旧镜像保留标签 `hnieoj-unified-web:before-approval-20260914`，不参与当前运行。
本地备份：`/Users/aq/hnieoj-archives/approval-fix-20260914/`。

注意：备份目录使用限制权限；同一 shell 拉取/构建前恢复 umask 022。
首轮生产回归发现备份用的 umask 077 导致新签出的 PHP 文件为 600、Nginx 返回 404；
已将三个本次变更的 PHP 文件恢复 644，重新构建并完整重测，没有扩大凭据文件权限。

容器内重跑命令（第二条会创建并清理随机前缀测试账号，需明确允许此项回归）：

```bash
docker compose exec -T web php /home/judge/src/web/tests/registration_approval_test.php
docker compose exec -T web php /home/judge/src/web/tests/registration_approval_http_test.php --fixtures
```

最终验收：

- 9 个隔离案例通过：慢邮件、正常响应、邮件 503、拒绝、旧链接拒绝已通过账号、
  无效操作、错误令牌、数据库写入失败、空邮箱。慢邮件本地约 3.02 秒，生产约 3.04 秒，
  邮件等待期间会话锁已释放。隔离测试使用 SQLite 和本机模拟服务，不访问业务数据。
- 生产真实 Nginx/PHP/数据库 HTTP 测试通过：批准/拒绝、CSRF、旧链接保护、个人信息
  修改申请、通过后的学生登录、普通学生无管理权限。没有审批真实学生申请或发送真实邮件。
- 临时账号、申请、权限、测试登录日志与 HTTP 会话已清理，测试账号残留数为 0。
- PHP 语法和知识地图逻辑回归通过。Web、DB、judge 健康，cache 运行中；DB/judge 未重启。
- 公网 HTTPS 真实账号登录、两个申请管理列表、首页/题库/状态/知识地图及携带令牌的
  POST 注销通过，注销后管理页面拒绝访问；未通过公网操作真实申请。
- 本地、生产源码、生产容器中审批文件 SHA-256 均为
  `e15f2080fc6c2da5de1414d50d725604f02a57976f505a92a863663e336218ba`。
- 生产测试日志：备份目录内 `approval-regression.log`；构建日志 `web-build.log`。

旧邮件微服务仍不可达，界面会明确提示通知失败；本次不恢复该外部服务，
也不将单个申请的回归结果扩展为一键批量审核或整个管理端的全面验收。
