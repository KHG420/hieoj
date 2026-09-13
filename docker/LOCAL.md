# 本地唯一工作目录

2026-09-14 整理后，本地正式目录为 `/Users/aq/hnieoj-unified`。
这是拥有独立 `.git` 的仓库，不再是依附旧备份目录的 worktree；分支为 `main`，
源码与生产 `/opt/hnieoj`、GitHub `KHG420/hieoj` 的 main 同步。

## ARM Mac 启动

```bash
cd /Users/aq/hnieoj-unified
docker compose up -d --build db cache web
```

访问 `http://127.0.0.1:18888`。本地 `.env` 固定该端口，未提交 Git。
80 端口需要当前不可用的 Docker Desktop 特权端口辅助服务，8080 已被其他项目使用，
因此选择空闲的 18888，没有修改其他项目。

本地仍使用根 Compose 和相同源码，不增加第二套部署文件。
这台 Mac 是 ARM64，**只运行 Web、数据库、缓存，不运行判题器**。
不要将本地提交功能视为可完成判题；完整判题部署在原生 Linux x86_64 生产服务器。

## 数据与验证

- 保留原本地数据库快照：12,120 用户、1,154 题、541,379 提交、153 比赛。
- 保留原本地题目测试数据及上传文件；不是重新下载生产的完整实时数据。
- 数据库所有表校验值一致，业务文件比较通过；迁移时排除 macOS Finder 附加元数据。
- 数据卷权限已按容器 Web 用户调整，原账号登录、页面、知识地图回归及容器重建后登录通过。
- 本地与生产**代码一致，但数据库不是实时同步**。本地仅保留原有测试数据子集。

## 清理与备份

旧 `hnieoj` 本地部署、临时验收项目的容器、卷及镜像已删除。
旧展开目录 `hnieoj-production-backup-2026-09-11` 已从工作区移除并放入 macOS 回收站，
正式冷备位于：

`/Users/aq/hnieoj-archives/local-before-consolidation-20260914/`

冷备包含旧目录、数据库导出、旧测试数据卷、容器配置和 SHA-256 清单。
不要上传这些私密归档到代码仓库。

`/Users/aq/HnieOj` 下的 Java/前端重写工程属于 `haoran37/HnieOJ*` 仓库，
不是当前 PHP 项目的副本，本次没有修改它及其 Docker 资源。
