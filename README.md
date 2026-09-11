# HnieOJ 在线评测系统源码仓库

HUSTOJ 在线评测系统（hnieacm.com）源码，从生产服务器 `/home/judge/src` 提取整理。

- 部署/维护完整说明见 [`OJ系统说明文档.md`](OJ系统说明文档.md)
- 系统架构：nginx + php7.3-fpm + MariaDB(jol) + judged 判题守护进程（LNMP）

## 目录结构

| 目录 | 内容 | 来源 |
|---|---|---|
| `web/` | PHP 前端站点（nginx root） | `/home/judge/src/web` |
| `core/` | judged / judge_client / sim C++ 源码 + make.sh | `/home/judge/src/core` |
| `install/` | 安装/维护脚本（install.sh、bak.sh、db.sql 等） | `/home/judge/src/install` |
| `docker/` | 容器化方案 | `/home/judge/src/docker` |
| `deploy/` | 线上生效的部署配置（nginx.conf、hustoj 启停脚本、delete_cache.sh） | `/etc/nginx`、`/etc/init.d` |
| `etc/` | 判题核心配置模板 + Java 沙箱策略 | `/home/judge/etc` |

## 部署要点（敏感文件需手动创建）

仓库**不包含**含真实口令的配置文件，部署前按模板补齐：

```bash
# 1) Web 数据库连接（模板见 web/include/db_info.inc.php.example）
cp web/include/db_info.inc.php.example web/include/db_info.inc.php
#    填入真实的 $DB_PASS（hustoj@127.0.0.1 / jol 库）

# 2) 判题核心配置（模板见 etc/judge.conf.example）
cp etc/judge.conf.example /home/judge/etc/judge.conf
#    填入真实的 OJ_PASSWORD / OJ_HTTP_PASSWORD，chmod 600
```

## 已排除的内容（不进入本仓库）

- **题目测试数据** `/home/judge/data/`（2.4G，3828 个题目目录）— 由 `/home/judge/src/install/bak.sh` 每日备份到 `/var/backups/`
- 用户上传 `web/upload/`、缓存、日志（`curl.log` 等）
- 编译产物（`*.o`、`*.http`、judge_client/judged 二进制，用 make.sh 重新编译）
- 备份副本：`web_backup/`、`admin.cp/`、`template_backup/`、`core.mv/`、`install.mv/`
- 敏感配置：`web/include/db_info.inc.php`、`etc/judge.conf`、`install/judge.conf`、`install/jol.tar.gz`（含真实数据库表）
- vim 残留 `.echarts.min.js.swp`（84M）、`*.bak`

## 本地开发 / 提交

```bash
cd /home/admin123/HnieOJ
git status          # 查看改动
git add .           # 暂存
git commit -m "..." # 提交
```

## 推送远程仓库

```bash
cd /home/admin123/HnieOJ
git remote add origin <你的远程仓库URL>
git push -u origin master
```
