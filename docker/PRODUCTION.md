# 生产切换记录：2026-09-14

## 唯一活动部署

- 内网服务器：`10.95.222.28`，代码：`/opt/hnieoj`，分支 `main`。
- Compose 项目：`hnieoj-unified`。生产 `.env` 保存在服务器，未提交 Git。
- Web 监听 `127.0.0.1:80`。公网 Nginx / FRP 链路保持原配置，流量已到新版。
- 运行代码提交：`94d9157180b3e3e5567e2ea2743c4e59d58d6933`；其后的本次记录提交只改文档。

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
