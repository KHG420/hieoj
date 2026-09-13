# HnieOJ 在线评测系统 — 部署与使用说明

> 历史文档：以下为旧裸机部署记录，不是当前部署入口。新部署只使用根目录 `compose.yaml`，见 [README](README.md)；生产切换见 [迁移说明](docker/MIGRATION.md)。

> 最后更新：2025-08-04
> 系统类型：HUSTOJ（算法设计在线评测系统）
> 服务域名：hnieacm.com（证书已就绪，HTTPS 未启用）

---

## 1. 系统概览

本机是一台 **HUSTOJ 在线评测系统（OJ）** 服务器，采用经典的 LNMP 架构：

```
浏览器用户
    │  :80
    ▼
┌─────────────┐   fastcgi   ┌──────────────┐       ┌───────────┐
│   nginx     │ ──────────► │ php7.3-fpm   │ ────► │  MariaDB  │
│ (静态+转发)  │   .php      │ (PHP 解析)    │  SQL  │ (jol 库)  │
└─────────────┘             └──────────────┘       └───────────┘
      ▲                                                ▲
      │ 测试数据 /home/judge/data                       │ 轮询待判队列
      ▼                                                ▼
┌─────────────────────────────────────────────────────────────┐
│  judged 判题守护进程（并发 3 核）→ judge_client 编译+沙箱运行 │
│  运行目录 /home/judge/run0 run1 run2                        │
└─────────────────────────────────────────────────────────────┘
```

**核心组件清单**

| 组件 | 版本/位置 | 说明 |
|---|---|---|
| nginx | 1.18.0 (Ubuntu) | Web 入口，配置内联在 `/etc/nginx/nginx.conf` |
| PHP-FPM | 7.3 | OJ 实际使用的 PHP 解析器，socket: `/run/php/php7.3-fpm.sock` |
| PHP-FPM | 8.1 | 已安装运行，但 nginx 未使用（预留/残留） |
| MariaDB | 10.6.22 | 监听 0.0.0.0:3306，含 `jol`（OJ）和 `newdata`（充电桩）两库 |
| judged | `/usr/bin/judged` | 判题守护进程（当前运行中） |
| judge_client | `/usr/bin/judge_client` | 判题客户端（编译+运行+比对） |
| memcached | 127.0.0.1:11211 | OJ 缓存 |
| zerotier-one | :9993 | 内网组网（备用访问通道） |

---

## 2. 文件分布

### 2.1 站点与判题核心（核心目录 `/home/judge/`）

```
/home/judge/
├── src/
│   ├── web/                PHP 前端站点（nginx root，属主 www-data）
│   │   ├── index.php       首页入口
│   │   ├── admin/          管理后台（admin/index.php）
│   │   ├── include/        公共配置（db_info.inc.php 数据库连接）
│   │   ├── contest.php     比赛入口
│   │   ├── problem.php     题目页
│   │   ├── status.php      提交状态
│   │   ├── discuss3/       讨论区
│   │   └── config.yaml     静态资源压缩规则
│   ├── core/               judged / judge_client / sim 源码 + make.sh
│   ├── install/            HUSTOJ 安装/维护脚本（bak.sh、db.sql 等）
│   ├── web_backup/         前端备份副本
│   ├── docker/             容器化方案
│   ├── core.mv/ install.mv/  旧版本残留（可清理）
│   └── (src.bak.tar.gz / web.tar.gz / hustoj.tar.gz) 备份包
├── data/                   题目测试数据（3828 个题目目录，属主 www-data）
├── run0/ run1/ run2/       判题沙箱运行目录（对应 3 并发）
├── etc/
│   ├── judge.conf          判题核心配置（数据库、并发数、语言限制）
│   ├── judge.pid           判题进程 PID
│   └── java0.policy        Java 沙箱安全策略
├── log/                    判题日志
├── backup/                 备份目录
├── delete_cache.sh         清理缓存脚本（cron 每日 3:00）
└── *.pid / *.txt / *.php   历史操作记录（属主 judge，勿删）
```

### 2.2 系统级组件

```
/usr/bin/judged           判题守护进程（二进制）
/usr/bin/judge_client     判题客户端（二进制）
/etc/init.d/hustoj        判题服务启停脚本（start/stop/restart/status）
/etc/nginx/nginx.conf     nginx 主配置（server 块内联于此，末尾 #added by hustoj）
/etc/nginx/sites-enabled/default  冗余配置（实际未生效，include 被注释）
/etc/nginx/*.crt/.key     hnieacm.com 证书（未引用）
```

### 2.3 数据库（MariaDB）

| 数据库 | 表数 | 用途 | 连接用户 |
|---|---|---|---|
| **jol** | 38 | OJ 主库：users / problem / solution / contest / source_code / compileinfo / runtimeinfo / privilege 等 | hustoj |
| newdata | 5 | 充电桩业务（chargers / orders / transactions / device_status / users），与 OJ 无关 | kk |

数据库凭据：`/home/judge/etc/judge.conf` 与 `/home/judge/src/web/include/db_info.inc.php` 中一致
（`hustoj@127.0.0.1` / 库 `jol`）。

### 2.4 admin123 家目录（维护副本）

```
/home/admin123/
├── oj/                    HUSTOJ 官方 GitHub 仓库副本（含 docker/kubernetes/apk）
├── HnieOJ/                本说明文档所在目录（原为空）
├── src/  src.cp/          OJ 源码副本（root 所有）
├── data/                  题目数据副本（3790 项）
├── charging-backend/      Node.js 充电桩后端（端口 3000，当前未运行，连 newdata 库）
├── data.tar.gz (512M)     题目数据备份
├── src.tar.gz (215M)      源码备份
├── db_20251105.sql.bz2 (60M)  数据库备份
├── db_20251023.sql.bz2    旧版数据库备份
├── 25级学生插入数据.sql    学生账号批量导入脚本
├── frp_0.64.0_linux_amd64.tar.gz  内网穿透工具
├── login.sh               登录辅助脚本
└── nvm.sh / .nvm          Node 版本管理（含 reasonix 可执行文件）
```

### 2.5 定时任务（cron）

| 时间 | 脚本 | 作用 |
|---|---|---|
| 每日 00:01 | `/home/judge/src/install/bak.sh` | 清理脏数据 + 备份数据库与站点到 `/var/backups/` |
| 每小时 | `/home/judge/src/install/oomsaver.sh` | 内存保护（调 OOM 权重、swappiness） |
| 每日 03:00 | `/home/judge/delete_cache.sh` | 清空 Web 缓存目录 |

---

## 3. 服务管理

### 3.1 一键状态查看

```bash
systemctl status nginx php7.3-fpm mariadb   # 三大服务
/etc/init.d/hustoj status                   # 判题进程
ss -tlnp | grep -E ':80|:3306|:11211'       # 端口确认
```

### 3.2 判题服务（hustoj）

```bash
/etc/init.d/hustoj start      # 启动判题
/etc/init.d/hustoj stop       # 停止判题
/etc/init.d/hustoj restart    # 重启判题（改 judge.conf 后需重启）
/etc/init.d/hustoj status     # 查看判题进程
```

> 判题并发数在 `/home/judge/etc/judge.conf` 中 `OJ_RUNNING=3` 修改，改完重启 hustoj。

### 3.3 Web 服务

```bash
systemctl restart nginx        # 改 nginx 配置后重载
nginx -t                       # 先测试配置语法
systemctl restart php7.3-fpm   # 改 PHP 配置后重启
```

---

## 4. 使用指南

### 4.1 前台（普通用户）

浏览器访问 `http://<服务器IP>` 或 `http://hnieacm.com`：
- 注册/登录 → 查看题目 → 在线提交代码 → 实时查看判题结果
- 比赛页面：`contest.php`
- 讨论区：`discuss3`

### 4.2 管理后台

```
访问： http://<服务器IP>/admin/
账号： admin（初始密码 admin，首次登录后务必修改）
```

常用功能：
| 页面 | 功能 |
|---|---|
| admin/index.php | 后台首页/统计 |
| problem_add_page_hustoj.php | 添加题目（填题目 + 上传测试数据） |
| contest_add.php / contest_edit.php | 创建/编辑比赛 |
| news_add.php | 发布公告 |
| privilege_add.php | 授予用户管理员/裁判权限 |
| adduser.php | 批量导入用户 |
| phpfm.php | 文件管理器（在线编辑站点文件） |

> 批量导入学生账号可用 admin123 家目录的 `25级学生插入数据.sql`（需按表结构调整）。

### 4.3 添加题目（测试数据规范）

1. 后台「添加题目」，记录题号（如 1001）
2. 测试数据放在 `/home/judge/data/1001/` 下：
   ```
   /home/judge/data/1001/
   ├── test.in    标准输入
   ├── test.out   标准输出
   └── ...（多个 .in/.out 成对文件）
   ```
3. 属主需为 `www-data`（`chown -R www-data:www-data /home/judge/data/1001`）
4. Special Judge 题目：上传 spj 程序，配置 `OJ_RAW_TEXT_DIFF` 相关选项

### 4.4 判题结果含义

| 值 | 含义 | | 值 | 含义 |
|---|---|---|---|---|
| 0 | Pending | | 6 | Presentation Error |
| 1 | Running | | 7 | Memory Limit Exceeded |
| 2 | Compile Error | | 8 | Time Limit Exceeded |
| 3 | Accepted | | 9 | Runtime Error |
| 4 | Wrong Answer | | 10 | Compile Time Out |
| 5 | 已提交（中间态） | | 11 | 编译中 |

---

## 5. 备份与恢复

### 5.1 自动备份

每日 00:01 `bak.sh` 自动执行，产物在 **`/var/backups/`**：
```
db_YYYYMMDD.sql.bz2           数据库导出（保留 1 天）
hustoj_YYYYMMDD.tar.bz2       完整备份（保留 3 天）：
                              data + web + core + etc + 当日 db 导出
```
手动触发：`/home/judge/src/install/bak.sh`

### 5.2 手动恢复

```bash
# 1) 恢复数据库
bzip2 -d db_YYYYMMDD.sql.bz2
mysql -h127.0.0.1 -uhustoj -p jol < db_YYYYMMDD.sql

# 2) 恢复文件
tar xjf hustoj_YYYYMMDD.tar.bz2 -C /
# 注意保持属主：judge:judge、www-data:www-data

# 3) 重启服务
/etc/init.d/hustoj restart
systemctl restart nginx php7.3-fpm
```

---

## 6. 常见维护操作

### 6.1 查看/清理判题队列
```bash
mysql -h127.0.0.1 -uhustoj -p jol -e \
  "select solution_id,problem_id,result from solution where result<4;"
```

### 6.2 查看最近提交与日志
```bash
tail -f /home/judge/log/*.log
tail -f /var/log/nginx/access.log
```

### 6.3 服务器异常排查顺序
1. `systemctl status nginx php7.3-fpm mariadb` — 服务是否存活
2. `df -h /` — 磁盘是否写满（当前 59%，31G 可用）
3. `free -h` — 内存（oomsaver.sh 每时会保护关键进程）
4. `/etc/init.d/hustoj status` — 判题是否存活
5. 查看 `/home/judge/log/` 与 `/var/log/nginx/error.log`

---

## 7. 注意事项与遗留问题

1. **HTTPS 未启用**：证书 `hnieacm.com_bundle.crt/.key` 已放在 `/etc/nginx/`，但配置无 443 监听。如需启用，在 nginx server 块加 443 ssl 配置并引用证书。
2. **sites-enabled 配置失效**：`/etc/nginx/nginx.conf` 中 `include /etc/nginx/sites-enabled/*` 被注释，当前生效的是 nginx.conf 内联 server 块。改配置认准 nginx.conf，`sites-enabled/default` 是冗余。
3. **php8.1-fpm 空转**：运行中但未被 nginx 引用，可停用节省资源。
4. **docker 残留**：20 个 Exited 容器（同镜像），安装遗留，可 `docker rm` 清理。
5. **数据库口令明文**：judge.conf 与 db_info.inc.php 中含明文口令，注意文件权限（当前 judge.conf 为 600）。
6. **同机充电桩项目**：`newdata` 库 + `charging-backend`（端口 3000，未运行）与 OJ 无关，勿混淆。
7. **非交互 shell 无 nvm**：admin123 的 nvm（含 reasonix）在 `.bashrc` 加载，cron / `su -c` 场景需另行配置 PATH。

---

## 8. 关键路径速查表

| 用途 | 路径 |
|---|---|
| nginx 实际配置 | `/etc/nginx/nginx.conf` |
| 站点根目录 | `/home/judge/src/web` |
| 判题配置 | `/home/judge/etc/judge.conf` |
| 判题服务脚本 | `/etc/init.d/hustoj` |
| 题目数据 | `/home/judge/data/<题号>/` |
| 判题沙箱 | `/home/judge/run{0,1,2}/` |
| 数据库 | MariaDB `jol` 库 |
| 自动备份 | `/var/backups/` |
| 管理后台 | `http://<IP>/admin/` |
