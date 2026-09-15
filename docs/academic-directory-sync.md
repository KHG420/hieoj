# 学院班级目录与管理员手动同步

本文说明学院 / 班级目录的数据模型、迁移、**管理员手动同步（主路径）**、导入器与
可选的旧采集器。范围只涉及 `collegiate` 与 `schoolList` 两张表及注册 / 修改资料 /
排名 / 登录 / `getClass.php` 的数据来源；不修改 `users`、不重做排名统计、不引入新依赖。

## 1. 数据模型与关键约定

| 表 | 关键字段 | 含义 |
| --- | --- | --- |
| `collegiate` | `id`, `name`, `source_id`(nullable, unique) | 本地学院。`id` 是本地数字代码，与页面既有两位代码一致；`source_id` 是教务源的学院标识（数字串或 32 位 ID）。 |
| `schoolList` | `school_id`, `num`, `value`, `join_time`, `collegiate_id`, `source_id`(nullable, unique) | 本地班级目录。`num` 是**显示班级编号**（8/10/11 位），`source_id` 是**稳定源 ID**（`field0`，可能 32 位）。 |

- **源 ID 与编号分离**：`source_id` 才是稳定身份，`num`/`bh` 只是显示编号，可能变长
  （现有 `varchar(10)` 已扩到 `varchar(64)`）。绝不能用源 ID 直接当本地数字学院 `id`。
- **学院 id = 源 code**：`collegiate.id` 继续使用页面既有数字代码（`code`）。例如源学院
  `source_id=36`、`code=80` 对应本地 `collegiate.id=80`（研究生院）。
- **年级派生**：只有长度 10/11 且以 `19`/`20` 开头的 `num` 才把前 4 位当作入学年份；
  8 位等旧编号不猜年份。**不从班级名称反推年级**。
- **同步范围**：管理员同步与导入器只同步**2024 级及以后（含 2024）**的班级及其所属学院。
  范围判断只看显示编号 `bh`/`num`：必须 10 或 11 位纯数字、以 `20` 开头且前四位 `>= 2024`；
  8 位旧编号、含字母编号与 32 位稳定源 ID 一律不在范围，**不从班级名称 / 尾号猜年级**。
  库内历史旧年级记录、`users` 与只读查询行为保持不变，不删除、不改写、不迁移。
- **历史数据**：源缺失的学院 / 班级一律保留，不删除、不自动停用。`users.school` /
  `users.xueYuan` 是历史文字，保持不变。

## 2. 迁移（migration）

脚本：`web/cli/academic_directory_migrate.php`，独立 CLI、幂等、默认只检查。
**后台页面不会自动执行 DDL**：未迁移 / 半迁移时，后台提示运维执行本脚本，并拒绝写入。

```bash
# 在 web 容器内
docker compose exec -T web php /home/judge/src/web/cli/academic_directory_migrate.php            # dry-run
docker compose exec -T web php /home/judge/src/web/cli/academic_directory_migrate.php --apply    # 执行
```

做的改动：

1. 迁移前安全检查：缺表、`collegiate` 重复 `id` / NULL `id` 直接报错且**零写入**。
2. `collegiate` 增加 `source_id VARCHAR(64) NULL` + 唯一索引 `uniq_collegiate_source_id` + `idx_collegiate_id`；
   若 `collegiate` 不是 InnoDB（旧库可能是 MyISAM）会执行 `ALTER TABLE collegiate ENGINE=InnoDB`，
   保证参与写入的两张表都可事务回滚。
3. `schoolList` 增加 `source_id`，`num` 扩到 `VARCHAR(64)`，转换 `ENGINE=InnoDB`，
   增加 `source_id` 唯一索引与 `collegiate_id` / `value` / `num` 索引；**保留原主键**
   `(school_id, num)` 与自增，`num` 允许重复。
4. 补齐页面既有 17 个学院：缺的插入；已有同 `id` 且由源同步接管（`source_id` 非空）时
   不改名；否则按当前页面名称更新（如 `07 管理学院 → 商学院`、`09 建筑工程学院 →
   智慧建造与能源工程学院`）。旧的 `经济学院(05)` 等不在源中的学院保留。

迁移不会自动对生产执行：必须显式 `--apply`。DDL 在 MySQL 中会自动提交，因此若中途失败，
直接再次运行即可（脚本幂等，会跳过已完成项）。**导入器 / 后台不会假设“有 source_id 列就是迁移完整”**：
apply 前会校验两张表的 InnoDB 引擎、`source_id` 单列唯一索引、`num`/`source_id` 字段宽度，
任一未完成都明确要求重跑 migration 并且零写入（覆盖 DDL 中途失败留下的半迁移状态）。

**备份 / 回滚**：执行前先备份 `collegiate`、`schoolList`（例如
`mysqldump jol collegiate schoolList > backup.sql`）。回滚时恢复备份即可；表结构回滚可
`ALTER TABLE schoolList ENGINE=MyISAM`（如需）并删除新增列 / 索引。

## 3. 管理员手动同步（主路径）

入口：管理后台左侧「用户类 → 同步学院班级」，页面 `web/admin/academic_directory.php`。
不走 shell、不依赖 macOS / Chrome，只用容器内现有 PHP cURL + DOM/JSON + PDO。

流程：

1. **获取验证码**：点击后由服务器访问教务源 `https://jwcmis.hnie.edu.cn` 的
   `/jsxsd/` 与 `/jsxsd/verifycode.servlet`（同一内存 cookie 会话），把验证码图片
   以 data URI 显示在当前页面。验证码来自教务系统本身，本页不识别、不绕过。
2. **临时登录并采集**：填写临时教务账号、密码、验证码，点击「采集并预览」。
   - 登录 POST `/jsxsd/xk/LoginToXk`：`loginMethod=LoginToXk`、`userAccount=<账号>`、
     `userPassword=`（空）、`RANDOMCODE=<验证码>`、
     `encoded=base64(账号) + "%%%" + base64(密码)`（标准 base64，与源站 `encodeInp` 一致）。
   - 成功后 `GET /jsxsd/framework/xsMainV.htmlx` 确认学生登录（登录 HTML 一律拒绝），
     `GET /jsxsd/view/kbxx/kbcx/llsykb_frm.jsp` 取 iframe 的
     `/Logon.do?method=toFinGlKbCx&token=...`，校验**同主机 / 同端口 / 预期路径**后
     升级为 HTTPS 并交换课表会话。
   - `GET /tkglAction.do?method=llsykbFind&kbtype=xx04&init=1&isview=1` 解析 `#yxbh`
     学院下拉（`option.value`=源 `source_id`，标签形如 `【01】电气与信息工程学院`）。
   - `GET /common/llsykb/xx04_select.htmlx?...&PageNum=N&pageSize=500` 抓取全部班级页；
     逐页校验 `createPage({pageNum,current,total,each})` 与隐藏域 `dataTotal`、每页条数、
     全量条数、源 ID / 编号唯一性。任一分页字段缺失、页数 / 条数不一致都拒绝，**不产出截断快照**。
      范围筛选发生在完整构建 / 校验**之后**，不会用筛选掩盖截断或坏数据。
3. **预览**：采集完成后先按第 1 节的同步范围收窄（仅 2024 级及以后，含 2024），再显示
   源学院 / 班级数、新增 / 更新 / 保留、冲突样例与受影响学院清单、前若干条变更样例
   （总数真实，不罗列全部班级）。预览与「确认同步」使用同一份已收窄快照，统计一致。
   **预览绝不写库**。若采集结果里没有任何 2024 级及以后的班级，页面明确提示
   「没有2024级及以后的班级」并保持零写入，**不产生可确认的空预览**。若数据库尚未完成
   migration（含半迁移），页面提示联系维护人员按第 2 节处理，并且**不渲染「确认同步」**。
4. **确认同步**：只使用服务端 session 里那份已校验快照（不接受客户端提交的快照或计划），
   且只有该 nonce 的预览确实成功生成过变更计划时才渲染 / 接受「确认同步」；伪造或缺省该
   标记的 POST 一律拒绝。经共享执行器在同一事务 / 锁语义下写入，并显示实际统计；成功后
   提示“本次密码未保存”。无变化时明确提示无需更新。

安全与时限：

- **仅 administrator** 可访问；未登录、普通用户、`contest_creator` / `problem_editor` 返回 403。
- 所有动作走 **POST + 现有 `postkey` + `hash_equals`**；CSRF 失败返回 403，且不触发任何
  网络请求或数据库写。GET 只渲染页面；页面响应 `Cache-Control: no-store`。
- **密码只在当前请求内存中存在**：不保存、不回显、不写入 session / 文件 / URL / 日志 /
  隐藏域；密码输入框始终为空。远端登录响应原文、URL、token 与异常原文都不会显示。
- 临时 cookie challenge 只存服务端当前管理员 session，**10 分钟**到期；验证码刷新会替换
  旧状态并生成新的 challenge nonce，使旧页面的提交被拒绝。cookie 使用 cURL 内存 cookie
  列表，不创建磁盘文件；采集成功后立即清理全部远端 cookie。
- 预览 nonce + 到期时间绑定当前管理员 session 的这一份快照，防止旧标签页确认另一份快照；
  成功后一次性消费，失败 / 取消 / 重新开始都会安全清理并提示重新预览。
- 源地址固定为 HTTPS origin，不接受用户传入的 URL；cURL 开启 TLS 校验，设置有限的连接 /
  读取超时、单页 / 总字节上限与最大页数；跳转只在同主机同端口内手动跟随有限次
  （http 自动升级为 https），不跟随跨域跳转；不启用 `CURLOPT_VERBOSE`。

## 4. 导入器（importer，与后台共用执行器）

脚本：`web/cli/academic_directory_import.php`，默认 dry-run，`--apply` 才写。
快照格式与 `live-directory.json` 一致（`colleges` / `total` / `classes`）。
锁 / migration 完整性 / 规划 / 事务写入的语义在共享函数
`academic_directory_sync_execute()` 中，与后台「确认同步」完全一致。

```bash
# 默认 dry-run，从 stdin 读快照
docker compose exec -T web php /home/judge/src/web/cli/academic_directory_import.php < snapshot.json
# 实际写入
docker compose exec -T web php /home/judge/src/web/cli/academic_directory_import.php --apply < snapshot.json
```

行为：

- **先校验后写**：学院来源映射、总条数（`total` 必填且为正整数、与班级条数一致）、
  源 ID 唯一性、编号唯一性、字段长度、code 格式；未知学院 / 重复 ID / 空数据 / 坏 JSON
  一律失败且零写入。
- **同步范围**：完整校验通过后，共享执行器把快照收窄到 2024 级及以后（含 2024）：只保留
  范围内班级及其所属学院，`total` 与统计改为范围内数量。CLI 传入旧的**全量**快照也自动
  收窄（幂等），与后台「确认同步」完全一致；没有任何范围内班级时明确报错
  「没有2024级及以后的班级」并零写入。注意 `live-directory.json` 的 `total`（例如 4141）
  是教务**全量**班级数，不等于实际同步条数；不要把全量总数当作同步数量。
- **幂等 / 并发**：`PDO` 异常 + 事务；MySQL 下 **apply 会先取 advisory lock
  `GET_LOCK('hnieoj_academic_directory_sync', 30)`，再在锁内读取现有数据并计算 plan**，
  所有成功 / 失败退出都会释放锁（dry-run 只读，不加锁）。这样两个并发 run
  不会用过期状态生成 insert/update：第二个会在锁内重新查询并得到零新增零更新。
- **未迁移 / 半迁移明确报错**：缺少 `source_id` 列或迁移不完整（引擎、唯一索引、字段宽度）
  时提示先/重新运行 migration，apply 零写入。
- **学院 id = 源 code**：已同步的 `source_id` 若对应本地 `id` 与快照 `code` 不一致，
  整次写拒绝（不隐式改写本地 id）；仅在同一 `source_id` 下改名 / 换学院允许更新。
- **匹配顺序**：先按 `schoolList.source_id` 匹配已同步记录；旧班级只用**唯一匹配**的
  “`num`+`name`” 或 “`name`+`collegiate_id`” 建立映射（adopt）。多个候选不删除、不合并：
  插入独立源记录并计入冲突。
- **可更新**：已同步记录允许改名 / 换学院（更新目录记录），但**从不更新 `users`**。
- 每次输出 JSON 统计（新增 / 更新 / 保留 / 冲突）与冲突样例；失败返回非零，且不含凭证。

## 5. 页面行为

- `include/academic_directory.php` 提供只读查询：`academic_directory_colleges()` 返回
  `array(array(名称, 两位代码), ...)`，保持既有模板约定；collegiate 为空时回退到 17 学院。
- `getClass.php` 参数（`nj`、`xy`）与 JSON `value` 响应不变；按 `collegiate_id` 明确归属查询，
  不再用 `SUBSTR(num,5,2)` 猜学院；结果按名称去重。
  - 同名班级若已有权威源行归属其它学院，则旧的错配行不再出现在该学院；
  - 同名多条源记录仍按各自学院正确返回；
  - 完全没有源行时保留旧行行为；
  - `nj` 与 `xy` 都为空时返回 `[]`（保持原 `getClass.php` 无筛选契约，不返回全部班级）。
- 注册 / 修改资料 / 排名 / 登录的学院下拉统一来自 `collegiate`；年级选项来自编号派生。
- 修改资料页保留用户当前历史学院值（即使已不在动态列表中）并默认选中，避免不改却被提交成
  其它学院；新注册的学院 / 班级选择见 5.1（仅开放年级范围内，不再提供手填班级兜底）。

### 5.1 注册开放年级范围（策略文件，不改数据库）

新用户注册只允许选择“当前开放年级范围”内的学院 / 班级；历史（已毕业）与未来年级不再出现在
注册下拉中，既有用户数据、历史班级与其它页面（登录 / 修改资料 / 排名 / 默认 `getClass.php`）
行为完全不变。

- 策略保存在既有 `OJ_DATA` 持久目录（生产 `/home/judge/data`，由 `judge-data` 卷挂载、
  `www-data` 可写）下的小 JSON 文件 `registration_year_policy.json`，**不建表、不加列、不迁移**；
  文件位于 webroot 之外，随卷在容器重建后保留。
- 文件缺失时使用**自动模式**：开放 `[当前日历年 - 2, 当前日历年]`（含两端），按当前年份逐年
  滚动，与导入的旧数据无关；2026 年即 2024..2026，2027 年即 2025..2027，**不需要改写文件**。
- 管理员可在后台「同步学院班级」页面改为**自定义范围**：起始 / 截止入学年份均为闭区间，
  取值 1900..2099，且起始不晚于截止；页面同时说明自动规则与当前生效范围。
- 读取（注册页、`getClass.php`、`register.php` 校验）**绝不写文件**；保存走 POST + 现有
  `postkey`（`hash_equals`），只有 administrator 可保存，使用“同目录临时文件 + `rename`”原子
  替换并在写入后复读校验；保存不访问教务网络、不写数据库，也不清理同步 session 状态。
- 非法年份 / 数组等非法输入一律拒绝且旧文件保持原样；现有文件损坏 / 不可读时页面显式提示，
  保存有效配置会替换损坏文件并在页面说明（不静默假装成功）。
- 注册专用查询只认“有效数字 10/11 位编号”（长度 10/11、以 19/20 开头、逐位为数字），年份只从
  `num` 前 4 位派生，不从名称 / 源 ID 猜；沿用权威源行归属去重语义；结果年份倒序、同年按编号
  升序、按名称去重。学院下拉只包含当前范围内确有合格班级的学院，无合格班级时不回退 17 学院全量。
- `getClass.php` 增加 opt-in 参数 `registration=1`（默认行为与 JSON 数组 `value` 契约不变）；
  注册模式没有 `xy` 时返回 `[]`，不提供无筛选旁路。注册页 AJAX 始终带 `registration=1`，不再
  使用手填班级兜底：列表为空 / 失败时清空旧班级、保留必填占位并提示联系管理员。
- `register.php` 在写入 `users` 之前用同一份当前策略校验提交的“学院 + 班级”，拒绝已毕业 /
  未来 / 不匹配 / 任意拼凑组合并给出中文错误；策略读取失败时失败关闭。是否强制校验只依据可信的
  `OJ_TEMPLATE` 配置：使用学院 / 班级下拉的模板（syzoj / sta_sty）**无论请求里学院 / 班级字段
  为空、缺失还是数组都必须拒绝**，不能凭请求自带字段绕过；bs3 / sweet 等自由填写班级的旧模板
  保持原行为。注册页不再使用输出缓存，管理员保存后立即生效。

## 6. 旧采集器（macOS Chrome，保留但非默认）

> 这是历史工具，**不是主路径**。管理员手动同步（见第 3 节）已不再依赖 macOS / Chrome。
> 本机每周 LaunchAgent 已停用并移走，仓库也不再提供默认启用方式。

脚本目录 `scripts/academic-directory-sync/`：

- `collect_directory.py`：通过 `osascript` 在**用户已打开、已登录**的 jwcmis Chrome 标签里
  执行同步 XHR，不 launch 浏览器、不新建 / 切换标签、不改设置、不保存账号密码。
- `collect_payload.js`：页面内 JS，解析内联 `qz_option` 的 `data` JSON 数组（不使用 `eval`），
  按 `createPage` / `dataTotal` 校验分页。
- `run_chrome_js.js`：JXA 包装，仅查找已有标签并执行 JS。

流程与管理员同步的采集部分一致（会话交换、22 学院、分页校验、原子写出）。任一步失败都
以非零退出，**不覆盖旧快照**；分页字段缺失（例如 `current` 缺失）不再被当作成功。
**采集协议与输出保持不变**：旧采集器仍产出完整（全年级）快照；导入时统一走共享执行器，
自动收窄到 2024 级及以后（含 2024），不需要也不应该改动采集器。

```bash
python3 scripts/academic-directory-sync/collect_directory.py --out output/academic-directory.json
```

`scripts/academic-directory-sync/run-directory-sync.sh`（默认 dry-run，`--apply` 才写）会先
采集、再用项目 compose 在 `web` 容器内运行 importer；它需要 macOS + Docker，因此**不是默认
运行方式**，仅在确实需要无人值守时手动调用。

## 7. 测试与验证

```bash
# PHP 离线测试（SQLite，无需数据库 / 网络）
docker run --rm --network none --user www-data --entrypoint php -v "$PWD:/work:ro" -w /work \
  hnieoj-unified-web web/tests/academic_directory_test.php

# 管理员同步离线测试（fake 传输，无网络 / 无数据库）
docker run --rm --network none --user www-data --entrypoint php -v "$PWD:/work:ro" -w /work \
  hnieoj-unified-web web/tests/academic_directory_admin_test.php

# 管理员同步页面级回归（真实执行页面 + navbar，临时 SQLite 夹具，无网络 / 无真实数据库）
docker run --rm --network none --user www-data --entrypoint php -v "$PWD:/work:ro" -w /work \
  hnieoj-unified-web web/tests/academic_directory_admin_page_test.php

# 注册年级范围离线回归（SQLite 查询语义 + 策略文件往返 + getClass 注册模式 +
# register.php 服务端校验，临时目录 / 临时数据库，无网络 / 无真实数据库）
docker run --rm --network none --user www-data --entrypoint php -v "$PWD:/work:ro" -w /work \
  hnieoj-unified-web web/tests/academic_registration_test.php

# Python 采集器解析 / 分页完整性 / 失败不覆盖测试
python3 scripts/academic-directory-sync/test_collector.py

# wrapper 可执行回归测试（真实 /bin/bash 3.2 + 假 collector/docker，不联网/不碰库）
bash scripts/academic-directory-sync/test_run_sync.sh

# collect_payload.js 异常兜底不泄露 token / URL（node:vm 桩 XHR，无网络）
node scripts/academic-directory-sync/test_collect_payload.mjs

# MySQL/MariaDB 集成测试：并发、迁移完整性、触发器回滚、total 必填、反向映射冲突
scripts/academic-directory-sync/mysql_integration_test.sh

# 追加真实完整快照两次 apply 幂等场景（按新语义断言只同步 2024 级及以后）
ACADEMIC_ITEST_SNAPSHOT=/path/to/live-directory.json \
  scripts/academic-directory-sync/mysql_integration_test.sh
```

离线测试覆盖查询语义、去重 / 历史行处理、快照校验（`total` 必填）、计划幂等与冲突、
反向映射冲突、无筛选 `getClass` 返回空、`users` 不被触碰，以及**同步范围**（2024 级及以后，
含 2024）的编号边界、8 位 / 32 位 / 含字母编号不参与、学院只保留范围内班级引用者、
零匹配拒绝与「筛选前仍做完整校验」。
管理员同步离线测试覆盖 base64 协议、真实格式学院 / 班级解析、完整快照构建、同源跳转边界、
登录页拒绝、截断分页拒绝、权限 / CSRF / challenge / preview nonce 过期拒绝、采集后按
同步范围收窄（零匹配给出清晰提示），以及在注入 fake 传输下的完整采集编排
（采集成功后清理远端 cookie、密码不进入快照 / session 状态）。
页面级回归在临时目录复制当前产品页面 / navbar / include，用测试夹具复现 `$dbh` 惰性初始化，
真实执行页面：初始页不显示同步结果、合法预览计数正确且无 PHP 警告、确认结果不被 navbar
覆盖、migration 不完整时无确认按钮、伪造确认被拒绝且零写入；并额外覆盖注册年级策略卡片
（GET 只读、自定义保存与切回自动、非法范围与 CSRF 失败零写入、非管理员 403、策略保存不清空
同步 session 状态、损坏文件显式提示并可被有效配置替换）。
注册年级范围离线测试覆盖：有效数字 10/11 位编号、年份只从 `num` 派生、开放范围闭区间、
历史 / 未来 / 畸形编号排除、来源归属去重、年份倒序 + 同年编号升序、学院列表严格匹配、
策略文件缺失 = 自动滚动、自定义往返、非法输入不落盘、损坏文件显式报错、原子替换无残留；
并以隔离夹具真实执行 `getClass.php`（注册模式与默认模式）与 `register.php`（合法写入、
已毕业 / 未来 / 不匹配 / 任意组合拒绝，syzoj / sta_sty 空 / 缺失 / 数组学院班级均拒绝且无 PHP
警告，bs3 / sweet 旧模板显式配置后自由填写保留，策略损坏失败关闭），全部使用临时路径 /
临时数据库，不修改任何真实数据。
集成测试在**一次性、任务专属**的 `oj-academic-test-*` MariaDB 容器里真实验证
`GET_LOCK` 并发、InnoDB 事务回滚、半迁移拒绝、引擎 / 索引 / 字段宽度检查，并可追加真实
**全量**（例如 `22 学院 / 4141 班级`）快照的两次 apply 幂等场景；该场景按新语义断言只写入
收窄后（2024 级及以后，含 2024）的数量，而不是 4141。脚本结束时销毁自己创建的容器、网络与
临时文件，不挂载任何现有 volume 或凭证。
