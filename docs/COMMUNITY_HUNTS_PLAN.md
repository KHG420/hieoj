# 用户反例题目与评论

本方案已获用户批准，范围为本地开发和测试，不推送或部署生产。
工作树：`/Users/aq/hnieoj-community-hunts`；分支：`codex/community-hunts`。
本地隔离站点：<http://127.0.0.1:18889/hunt.php>。

## 已实现行为

- 今日冒险的“反例猎人”增加社区入口和出题入口；所有正常登录用户可直接发布题目，无前置审核。
- 出题表单包含标题、题意与输入范围、错误程序、参考程序、输入校验程序，语言为当前站点开放的 C++ 或 Python 3。三段完整程序使用同一种语言，通过标准输入/输出工作。
- 作者可编辑、下架、重新公开自己的题目；管理员可下架题目和删除违规评论。
- 社区题目和“我的题目”列表分页；下架题目仅作者和管理员可见，暂停新挑战与评论。
- 每个社区题目与三个内置题目各有独立评论区。支持普通文字和代码、分页、作者删除和管理员删除，无楼中楼、点赞、附件。
- 个人菜单固定为 196 px，右对齐向下展开，各项逐行排列；移动端菜单脱离横向滚动容器的裁切，仍对齐个人入口。保留现有菜单项。

## 验证规则

校验程序对合法输入只输出 `VALID`，不合法输入输出 `INVALID`（允许前后空白）。
校验、参考和错误程序作为三条独立自定义输入任务进入已有沙箱队列。

1. 三条任务结束后再给出结论；编译错误提示作者修题。
2. 校验程序失败或输出协议错误：题目配置错误；`INVALID`：输入不合法。
3. 参考程序失败或缺少完整输出：题目配置错误，不算命中。
4. 合法输入上，错误程序正常输出与参考输出不同，或发生超时、内存超限、运行错误：命中。
5. 两个正常输出按空白分隔的内容比较；仅空白差异不算命中。
6. 输出超限或不可比较的输出不算命中，提示检查程序。

输入最多 8 KB，可为空；每段源码最多 32 KB；题意最多 16 KB；评论最多 6 KB；标题最多 160 字。
沿用既有业务表的 UTF-8 三字节字符集，表情与特殊控制字符会给出校验提示。
每用户每小时最多发布 10 题，每分钟最多验证 3 次，最多同时挂起 3 组验证；每分钟最多 5 条评论。

自定义试运行沿用基础 5 秒 / 512 MB，解释型语言按站点现有配置获得额外额度。
`test_run` 成功仍保存 `OJ_TR=13`，失败保留 TLE/MLE/OLE/RE；同时初始化文件路径和错误文件名偏移。
自定义输出上限为 16 KB，运行结束后再次检查，避免监控采样漏掉快速输出；含 NUL 的非文本输出不能进入文本比较。
普通题目的输出限制不变。未修改既有判题协议或增加服务、生产依赖。

## 持久化与并发

新增三个 InnoDB 表，不修改既有业务表结构：

- `hunt_challenge`：作者、题目、三段源码、语言、公开状态、版本和时间。
- `hunt_attempt`：题目及版本、挑战者、输入、三条 solution ID 和时间。
- `hunt_comment`：`custom:<id>` 或 `builtin:<slug>` 目标、作者、正文、删除状态和时间。

继续使用 `solution`、`source_code`、`source_code_user`、`custominput`、`runtimeinfo`。
每次挑战把当时版本的源码存入现有源码表；题目编辑不改变历史任务与结果。
提交时锁定用户和题目，检查版本及限流，以结果 14 暂存三条任务；全部载荷写入完成后才统一置 0 并提交事务。
现有源码、输入表使用 MyISAM，失败时显式清理本次新建的载荷，InnoDB 记录回滚。
所有写操作检查正常账号、CSRF 与对象权限；页面内容按文本转义，不执行作者代码。

## 数据库初始化与迁移

`docker/db/community-hunts.sql` 是幂等迁移，新数据库初始化时由 DB 镜像自动载入。
已有数据库不会在重启时自动改表；需要显式应用迁移。本次只操作独立的 `hnieoj-community-hunts` 数据库。

在本工作树执行：

```sh
docker compose cp docker/db/community-hunts.sql db:/tmp/community-hunts.sql
docker compose exec -T db sh -c 'MYSQL_PWD="$(cat /run/oj-secrets/root-password)" mariadb -uroot jol < /tmp/community-hunts.sql'
docker compose build web db
docker compose up -d --no-deps web
```

本地 `.env` 配置 `COMPOSE_PROJECT_NAME=hnieoj-community-hunts` 和 `WEB_PORT=18889`，与主工作树 18888 的已有数据隔离。

## 本地验证

最近一次社区 HTTP/DB 回归通过 **183 项断言**，C++ 局部回归通过 **16 个场景**。浏览器实际验证发布、编辑、下架、重新公开、评论与排队提交；普通用户和管理员菜单均验证了排列、定位与键盘 Tab。临时测试数据已清理，本地仅保留 1 道标明“本地示例”的题目和 1 条示例讨论。

本地管理员用户名为 `admin`，在本工作树执行以下命令读取独立数据库的初始密码（与原本地站点分开）：

```sh
docker compose exec -T db cat /run/oj-secrets/admin-password
```

```sh
# 运行于 Web 容器内，使用临时用户、题目、评论和任务，结束后清理。
docker compose exec -T -u www-data web php /home/judge/src/web/tests/community_hunts_http_test.php --fixtures
# 编译实际 test_run 函数，进程/数据库调用使用测试桩，不执行不可信程序。
python3 core/judge_client/tests/custom_run_test.py
# 既有冒险逻辑回归。
docker compose exec -T -u www-data web php /home/judge/src/web/tests/adventure_test.php
```

HTTP 测试覆盖实际表单目标、普通用户发布、编辑和下架权限、CSRF、XSS、禁用账号、输入边界、评论目标隔离与删除、分页、旧版本源码保留、完整队列载荷、限流和判定真值表。
判题结果在该测试中显式模拟，不代表原生沙箱已执行。

另验证了 DB 全新初始化、重复迁移、完整判题源码 HTTP 版本在本地 x86_64 容器中的编译，以及桌面和手机浏览器的出题与评论操作、表单、菜单展开和布局。
既有 adventure HTTP 集成测试依赖题库和知识分类映射；隔离空库测试时补充了临时映射和公开题，运行后全部删除。

**环境限制：** 本机是 Mac ARM，原生 Linux x86_64 的 ptrace 沙箱端到端运行尚未验收。独立本地 Web 可用于出题、讨论和页面验收；没有启动受支持的判题服务时，自定义反例验证保持排队状态。实际 Linux 沙箱编译/执行验收是上线前剩余的环境验证项，本次不连接远端主机。
