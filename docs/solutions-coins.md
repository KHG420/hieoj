# 题解与金币

## 已实现规则

- 通过某道公开题目后可以提交该题的题解，正文使用纯文本，保留换行和代码缩进。
- 题解提交后处于待审核状态；管理员审核通过后公开，并向作者发放 10 金币。驳回时必须填写原因，不发奖。
- **通过该题的用户可以免费查看该题全部已审核题解。** 作者可以查看自己的待审核、已通过和已驳回题解，管理员可以免费审核。
- 未通过该题的用户可支付 **5 金币，永久解锁该题全部已审核题解，包括后续新增题解**。同题重复解锁不扣费，不同题目独立解锁。
- 支付的金币由系统扣除，不转给作者。付费后再通过题目，仍获得首次通过的 2 金币，不退还之前的解锁费用。
- 功能上线后首次通过一道题奖励 2 金币，同题重复通过、重判不重复奖励。历史已通过题目不补发金币，但保留投稿和免费阅读资格。
- 金币榜单按当前余额降序展示正常、已审核的注册用户；同余额按用户名升序排列。金币明细只允许本人查看。
- 隐藏题、私有比赛题、尚未结束的有效比赛题不出现在公开题解中，直接链接和已付费用户也不能绕过题目可见性。现场比赛模式下仍跳转到比赛页面。

## 页面入口

- 不提供全站题解浏览和按题号查找题解的页面；题解入口位于各自题目页，直接访问 `solutions.php` 会跳转到题库。
- `solutions.php?problem_id=1000`：指定题目的题解和投稿表单。
- `solutions.php?tab=mine`：自己的题解及审核状态。
- `solutions.php?id=1`：题解正文或按题目解锁入口，标题与返回链接始终指向该题解所属题目。
- `solutions.php?tab=review`：仅管理员可访问的审核队列，后台侧栏提供入口。
- `coins.php`：金币榜单。
- `coins.php?tab=history`：自己的余额和金币流水。

## 数据与并发

新增五张 InnoDB 表：`problem_editorial`、`problem_unlock`、`coin_wallet`、`coin_ledger`、`coin_first_ac`。现有用户、题目、提交表结构保持不变。

`solution` 上的两个 AFTER 触发器覆盖判题 UPDATE 和直接 AC INSERT，与判题结果同一事务写入首次通过标记、余额及流水。首次通过以 `(user_id, problem_id)` 唯一约束防重，使用局部重复键处理器识别重复通过。

审核锁定题解记录，状态修改、作者奖励及流水在同一事务内完成。解锁以钱包行的排他锁串行处理同一用户的消费，解锁记录以 `(user_id, problem_id)` 唯一约束防重，余额不足时回滚。题解正文只在服务器确认作者身份、管理员身份、通过记录或已购题目权限后读取并输出，HTML 始终转义。

## 安装与升级

新安装的数据库容器在初始化时自动执行 `docker/db/solutions-coins.sql`。

已有数据库需要执行一次该迁移。**第一次迁移期间暂停提交与判题写入**，使历史 AC 标记与触发器启用之间没有漏发窗口；迁移前按现有运维流程备份数据库。`solution` 必须使用 InnoDB（当前仓库已满足）。迁移账号需要建表、创建触发器和读写相关表的权限，触发器的 DEFINER 账号需要保留。

在项目目录执行（根据部署方式调整 Compose 项目参数）：

```sh
# 先按站点维护流程暂停 web/judge，数据库继续运行。
docker compose cp docker/db/solutions-coins.sql db:/tmp/solutions-coins.sql
docker compose exec -T db sh -c 'MYSQL_PWD="$(cat /run/oj-secrets/root-password)" mariadb -uroot jol < /tmp/solutions-coins.sql'
# 发布本分支 web 代码，恢复 web/judge。
```

迁移可以重复执行，不重置余额、流水或解锁记录，不重复发奖。部署代码前可检查：

```sql
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema=DATABASE()
  AND table_name IN ('problem_editorial','problem_unlock','coin_wallet','coin_ledger','coin_first_ac');
-- 预期 5
SELECT trigger_name FROM information_schema.triggers
WHERE trigger_schema=DATABASE() AND trigger_name LIKE 'coin_solution_accepted_%';
-- 预期 insert、update 两个触发器
```

缺少表或触发器时，新页面提示模块暂未开放，不自动修改运行中的数据库。

## 验证

仅在本地隔离测试环境执行；HTTP 测试创建随机用户、题目、投稿及会话，结束后清理这些数据。

```sh
docker compose exec -T --user www-data web php /home/judge/src/web/tests/editorial_test.php --fixtures
```

该测试涵盖实际 MariaDB 判题奖励、历史标记、重复与并发 AC、投稿资格、匿名访问、CSRF、审核与驳回、按题目付费、通过后免费阅读、未来题解自动包含、同题多篇并发解锁、不同题目竞争余额、事务失败回滚、正文防泄露、HTML 转义、可见性、金币对账及列表分页。

迁移测试单独创建临时数据库，验证历史 AC 排除、未来 AC 奖励、幂等复跑与重判防重；只在隔离数据库容器中执行：

```sh
docker compose cp docker/tests/editorial-migration.sh db:/tmp/editorial-migration.sh
docker compose exec -T db sh /tmp/editorial-migration.sh
```
