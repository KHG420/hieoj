<?php
/**
 * 注册年级范围：离线回归测试（SQLite 内存库 + 临时目录，无网络 / 无真实数据库）。
 *
 * 覆盖：
 *   - 注册专用班级查询语义：有效数字 10/11 位编号、年份只从 num 派生、
 *     开放范围闭区间、历史 / 未来 / 畸形编号排除、来源归属去重；
 *   - 排序：年份倒序，同年按编号升序稳定；
 *   - 学院列表：只含确有合格班级的学院，无合格行时不回退全量；
 *   - 策略文件：缺失 = 自动 [当年-2, 当年] 且逐年滚动、自定义范围读写往返、
 *     非法输入拒绝且旧文件不改、损坏文件显式报错、原子替换无残留；
 *   - 服务端校验 / 前端接线：register.php 依据可信 OJ_TEMPLATE 在写 users 前校验
 *     （空 / 缺失 / 数组学院班级一律拒绝，bs3 / sweet 旧模板保持自由填写）、
 *     getClass 注册模式、注册页去缓存、syzoj 模板不再手填兜底且消息框带 visible。
 *
 * 运行：php web/tests/academic_registration_test.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$GLOBALS['RG_FAIL'] = 0;
$GLOBALS['RG_CHECKS'] = 0;

function rg_check($condition, $message)
{
    $GLOBALS['RG_CHECKS']++;
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $message . "\n";
    if (!$condition) {
        $GLOBALS['RG_FAIL']++;
    }
}

// ---------------------------------------------------------------------------
// SQLite 桩（与 academic_directory_test.php 相同契约）
// ---------------------------------------------------------------------------
$GLOBALS['TEST_PDO'] = new PDO('sqlite::memory:');
$GLOBALS['TEST_PDO']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$GLOBALS['TEST_PDO']->sqliteCreateFunction('CHAR_LENGTH', function ($value) {
    return $value === null ? null : mb_strlen((string)$value, 'UTF-8');
}, 1);

function pdo_query($sql)
{
    $args = func_get_args();
    array_shift($args);
    $stmt = $GLOBALS['TEST_PDO']->prepare($sql);
    $stmt->execute($args);
    if (stripos(ltrim($sql), 'select') === 0) {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $stmt->rowCount();
}

require_once __DIR__ . '/../include/academic_directory.php';
require_once __DIR__ . '/../include/academic_registration.php';

$pdo = $GLOBALS['TEST_PDO'];
$pdo->exec('CREATE TABLE collegiate (id INTEGER PRIMARY KEY, name TEXT, source_id TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uniq_collegiate_source_id ON collegiate(source_id)');
$pdo->exec('CREATE TABLE schoolList (school_id INTEGER PRIMARY KEY AUTOINCREMENT, num TEXT, value TEXT, join_time TEXT, collegiate_id INTEGER, source_id TEXT)');

$pdo->exec("INSERT INTO collegiate (id,name,source_id) VALUES
    (1,'电气与信息工程学院',NULL),
    (2,'机械工程学院',NULL),
    (5,'经济学院',NULL),
    (9,'纺织服装学院',NULL),
    (10,'排序测试学院',NULL),
    (11,'被遮蔽学院',NULL),
    (80,'研究生院','36')");

$pdo->exec("INSERT INTO schoolList (school_id,num,value,collegiate_id,source_id) VALUES
    (1,'2024010101','电2401',1,'s1'),
    (2,'2025010101','电2501',1,'s2'),
    (3,'2026010101','电2601',1,'s3'),
    (4,'2024010201','电2402',1,'s4'),
    (5,'2025010201','电2502',1,'s5'),
    (6,'2024a10101','坏编号2401',1,'s6'),
    (7,'2023010101','旧2023',1,'s7'),
    (8,'2027010101','未来2027',1,'s8'),
    (9,'2004010101','旧2004',1,'s9'),
    (10,'02011202','旧八位',1,NULL),
    (11,'0123456789abcdef0123456789abcdef','三十二位源ID',1,'s11'),
    (12,'202401010','九位编号',1,'s12'),
    (13,'2024010101','重复名',1,'s13'),
    (14,'2026010101','重复名',1,'s14'),
    (15,'2025010101','网络工程2501',1,'s15'),
    (16,'2024010101','无源行保留',1,NULL),
    (17,'2025010101','网络工程2501',2,NULL),
    (18,'2025010101','机械2501',2,'s18'),
    (19,'2024010101','同名源A',2,'s19'),
    (20,'2024010101','同名源A',1,'s20'),
    (21,'2024010101','经济2401',5,NULL),
    (22,'2024010201','Zeta2402',10,'o1'),
    (23,'2024010101','Alpha2401',10,'o2'),
    (24,'2025010101','Beta2501',10,'o3'),
    (25,'2026010101','Gamma2601',10,'o4'),
    (26,'2004010101','纺织0401',9,NULL),
    (27,'2004010101','动力0401',80,NULL),
    (28,'2025010101','网络工程2501',11,NULL)");

function rg_values($rows)
{
    $out = array();
    foreach ($rows as $row) {
        $out[] = $row['value'];
    }
    return $out;
}

// ---------------------------------------------------------------------------
// 1. 注册专用班级查询
// ---------------------------------------------------------------------------
$dq = rg_values(academic_directory_registration_classes('电气与信息工程学院', '', 2024, 2026));
$dqSorted = $dq;
sort($dqSorted);
rg_check(count($dqSorted) === 9, '默认范围电气学院返回 9 个去重班级');
rg_check(!in_array('坏编号2401', $dqSorted, true), '含字母的畸形编号被排除');
rg_check(!in_array('旧2023', $dqSorted, true), '历史年级 2023 被排除');
rg_check(!in_array('未来2027', $dqSorted, true), '未来年级 2027 被排除');
rg_check(!in_array('旧2004', $dqSorted, true), '更早年级 2004 被排除');
rg_check(!in_array('旧八位', $dqSorted, true), '8 位旧编号被排除');
rg_check(!in_array('三十二位源ID', $dqSorted, true), '32 位稳定源 ID 不作为编号');
rg_check(!in_array('九位编号', $dqSorted, true), '9 位编号被排除');
rg_check(count(array_unique($dq)) === count($dq), '同名班级按名称去重');
rg_check(in_array('重复名', $dqSorted, true), '跨年级同名班级保留一条');
rg_check(in_array('网络工程2501', $dqSorted, true), '权威源行按其学院正常返回');
rg_check(in_array('无源行保留', $dqSorted, true), '无源行历史记录在范围内保留');
$firstYear = substr((string)$pdo->query("SELECT num FROM schoolList WHERE value='电2601'")->fetchColumn(), 0, 4);
rg_check($firstYear === '2026', '夹具年份派生自 num 前四位');

$order = rg_values(academic_directory_registration_classes('排序测试学院', '', 2024, 2026));
rg_check(
    $order === array('Gamma2601', 'Beta2501', 'Alpha2401', 'Zeta2402'),
    '年份倒序 + 同年编号升序稳定'
);

$y2025 = rg_values(academic_directory_registration_classes('电气与信息工程学院', '2025', 2024, 2026));
rg_check($y2025 === array('电2501', '网络工程2501', '电2502'), 'nj 与开放范围取交集并稳定排序');
rg_check(academic_directory_registration_classes('电气与信息工程学院', '2023', 2024, 2026) === array(), 'nj 早于开放范围返回空');
rg_check(academic_directory_registration_classes('电气与信息工程学院', '2027', 2024, 2026) === array(), 'nj 晚于开放范围返回空');
rg_check(academic_directory_registration_classes('电气与信息工程学院', 'abcd', 2024, 2026) === array(), '非法 nj 返回空');
rg_check(academic_directory_registration_classes('', '', 2024, 2026) === array(), '注册模式无 xy 不返回全部（无旁路）');
rg_check(academic_directory_registration_classes('', '2025', 2024, 2026) === array(), '注册模式无 xy 即使给 nj 也返回空');
rg_check(academic_directory_registration_classes('不存在的学院', '', 2024, 2026) === array(), '未知学院返回空');
rg_check(academic_directory_registration_classes('电气与信息工程学院', '', 2099, 1900) === array(), '非法范围返回空');

$future = rg_values(academic_directory_registration_classes('电气与信息工程学院', '', 2027, 2027));
rg_check($future === array('未来2027'), '范围本身可覆盖未来年份（排除只由当前策略决定）');

// 来源归属：同名历史错配行不泄漏到其它学院
$jixie = rg_values(academic_directory_registration_classes('机械工程学院', '', 2024, 2026));
rg_check(!in_array('网络工程2501', $jixie, true), '同名历史错配行不出现在错误学院');
rg_check(in_array('机械2501', $jixie, true), '同学院权威源行正常返回');
rg_check(in_array('同名源A', $jixie, true), '同名多条源记录按各自学院分别返回');

// 旧行为对照：共享 getClass 查询不受注册范围影响
$shared = rg_values(academic_directory_classes('2027', '电气与信息工程学院'));
rg_check($shared === array('未来2027'), '共享 academic_directory_classes 仍可查未来年份（历史行为保留）');
$sharedOld = rg_values(academic_directory_classes('2004', '电气与信息工程学院'));
rg_check($sharedOld === array('旧2004'), '共享查询仍可查 2004 历史年级');

// ---------------------------------------------------------------------------
// 2. 学院列表严格匹配合格班级
// ---------------------------------------------------------------------------
$colleges = academic_directory_registration_colleges(2024, 2026);
rg_check(
    $colleges === array(
        array('电气与信息工程学院', '01'),
        array('机械工程学院', '02'),
        array('经济学院', '05'),
        array('排序测试学院', '10'),
    ),
    '学院列表只含确有合格班级的学院（保持 [名称, 两位代码] 形状）'
);
rg_check(academic_directory_registration_colleges(2030, 2031) === array(), '无合格班级时学院列表为空（不回退全量）');
rg_check(!in_array(array('被遮蔽学院', '11'), $colleges, true), '仅有被遮蔽历史行的学院不出现在下拉');
rg_check(academic_directory_registration_classes('被遮蔽学院', '', 2024, 2026) === array(), '被遮蔽学院查询不到错配班级');
$oldColleges = academic_directory_registration_colleges(2004, 2004);
rg_check(
    count($oldColleges) === 3 && $oldColleges[0][1] === '01' && $oldColleges[1][1] === '09' && $oldColleges[2][1] === '80',
    '自定义早期范围时学院列表随之变化'
);

// ---------------------------------------------------------------------------
// 3. 学院 + 班级组合校验（服务端 register.php 复用）
// ---------------------------------------------------------------------------
rg_check(academic_directory_registration_class_allowed('电气与信息工程学院', '电2601', 2024, 2026), '范围内合法组合通过');
rg_check(academic_directory_registration_class_allowed('机械工程学院', '机械2501', 2024, 2026), '另一学院合法组合通过');
rg_check(!academic_directory_registration_class_allowed('电气与信息工程学院', '电2601', 2024, 2025), '范围外（已超出截止）组合拒绝');
rg_check(!academic_directory_registration_class_allowed('电气与信息工程学院', '旧2023', 2024, 2026), '已毕业组合拒绝');
rg_check(!academic_directory_registration_class_allowed('电气与信息工程学院', '机械2501', 2024, 2026), '学院与班级不匹配拒绝');
rg_check(!academic_directory_registration_class_allowed('机械工程学院', '网络工程2501', 2024, 2026), '错配历史行不构成合法组合');
rg_check(!academic_directory_registration_class_allowed('', '电2601', 2024, 2026), '空学院拒绝');
rg_check(!academic_directory_registration_class_allowed('电气与信息工程学院', '', 2024, 2026), '空班级拒绝');
rg_check(!academic_directory_registration_class_allowed('任意学院', '任意班级', 2024, 2026), '任意拼凑组合拒绝');

// ---------------------------------------------------------------------------
// 4. 策略文件读写 / 校验 / 原子替换
// ---------------------------------------------------------------------------
$rgDir = sys_get_temp_dir() . '/oj-reg-' . bin2hex(random_bytes(6));
mkdir($rgDir, 0700, true);
$rgFile = academic_registration_policy_path($rgDir);
rg_check(basename($rgFile) === 'registration_year_policy.json', '策略文件位于数据目录下且文件名固定');

// 缺失 => 自动 [当年-2, 当年]，逐年滚动，且 GET 不落盘
$missing = academic_registration_policy_load($rgFile, 2026);
rg_check($missing['ok'] === true && $missing['exists'] === false, '缺失配置按自动模式处理');
rg_check($missing['mode'] === 'auto' && $missing['effective_min'] === 2024 && $missing['effective_max'] === 2026, '2026 年默认开放 2024..2026');
rg_check(academic_registration_policy_load($rgFile, 2027)['effective_min'] === 2025
    && academic_registration_policy_load($rgFile, 2027)['effective_max'] === 2027, '2027 年自动滚动为 2025..2027');
rg_check(!file_exists($rgFile), '只读加载绝不写文件');

// 自定义范围保存 + 往返
$save = academic_registration_policy_save($rgFile, array('mode' => 'custom', 'min' => '2025', 'max' => '2026'), 2026);
rg_check($save['ok'] === true && $save['replaced_invalid'] === false, '自定义 2025..2026 保存成功');
rg_check(file_exists($rgFile), '保存后策略文件存在');
$raw = json_decode((string)file_get_contents($rgFile), true);
rg_check($raw['mode'] === 'custom' && $raw['min'] === 2025 && $raw['max'] === 2026, '落盘 JSON 字段正确');
$reload = academic_registration_policy_load($rgFile, 2026);
rg_check($reload['mode'] === 'custom' && $reload['effective_min'] === 2025 && $reload['effective_max'] === 2026, '重新读取保持自定义范围');
$reloadNext = academic_registration_policy_load($rgFile, 2027);
rg_check($reloadNext['effective_min'] === 2025 && $reloadNext['effective_max'] === 2026, '自定义范围不随年份滚动');

// 切回自动
$saveAuto = academic_registration_policy_save($rgFile, array('mode' => 'auto'), 2026);
rg_check($saveAuto['ok'] === true && $saveAuto['state']['mode'] === 'auto', '切回自动更新成功');
rg_check(academic_registration_policy_load($rgFile, 2026)['effective_min'] === 2024, '切回自动后按当年生效');

// 非法输入：拒绝且旧文件原样
$beforeBytes = (string)file_get_contents($rgFile);
$invalidCases = array(
    array('mode' => 'custom', 'min' => '2026', 'max' => '2025'),
    array('mode' => 'custom', 'min' => '1899', 'max' => '2026'),
    array('mode' => 'custom', 'min' => '2025', 'max' => '2100'),
    array('mode' => 'custom', 'min' => 'abcd', 'max' => '2026'),
    array('mode' => 'custom', 'min' => '', 'max' => ''),
    array('mode' => 'custom', 'min' => array(2025), 'max' => '2026'),
    array('mode' => array('auto')),
    array('mode' => 'auto', 'min' => array(2025)),
    array('mode' => 'whatever'),
    array(),
);
foreach ($invalidCases as $i => $case) {
    $res = academic_registration_policy_save($rgFile, $case, 2026);
    rg_check($res['ok'] === false && $res['error'] !== '', '非法输入 #' . $i . ' 被拒绝并给出原因');
}
rg_check((string)file_get_contents($rgFile) === $beforeBytes, '非法输入后旧文件保持原样');

// 损坏文件：显式报错，合法保存替换并说明
file_put_contents($rgFile, '{ this is not json');
$broken = academic_registration_policy_load($rgFile, 2026);
rg_check($broken['ok'] === false && $broken['exists'] === true && $broken['error'] !== '', '损坏文件被显式识别为错误');
$repair = academic_registration_policy_save($rgFile, array('mode' => 'custom', 'min' => '2025', 'max' => '2026'), 2026);
rg_check($repair['ok'] === true && $repair['replaced_invalid'] === true, '合法保存替换损坏文件并显式标注');
rg_check(academic_registration_policy_load($rgFile, 2026)['ok'] === true, '替换后文件可正常读取');

// 结构错误：自动模式带 min / 自定义 min>max
file_put_contents($rgFile, json_encode(array('mode' => 'auto', 'min' => 2025, 'max' => null)));
rg_check(academic_registration_policy_load($rgFile, 2026)['ok'] === false, '自动模式携带 min 视为损坏');
file_put_contents($rgFile, json_encode(array('mode' => 'custom', 'min' => 2026, 'max' => 2025)));
rg_check(academic_registration_policy_load($rgFile, 2026)['ok'] === false, '自定义 min>max 视为损坏');
file_put_contents($rgFile, '[]');
rg_check(academic_registration_policy_load($rgFile, 2026)['ok'] === false, 'JSON 数组视为损坏（只接受对象）');

// 写入失败：目录不存在 / 父路径是文件 => 明确报错且不残留
$noDirFile = $rgDir . '/nope/policy.json';
$fail1 = academic_registration_policy_save($noDirFile, array('mode' => 'auto'), 2026);
rg_check($fail1['ok'] === false && strpos($fail1['error'], '目录') !== false, '数据目录不存在时保存失败并提示');
$fileAsDir = $rgDir . '/blocker';
file_put_contents($fileAsDir, 'x');
$fail2 = academic_registration_policy_save($fileAsDir . '/policy.json', array('mode' => 'auto'), 2026);
rg_check($fail2['ok'] === false && $fail2['error'] !== '', '父路径是文件时保存失败并提示');

// 原子替换：目录内不残留临时文件
academic_registration_policy_save($rgFile, array('mode' => 'custom', 'min' => '2024', 'max' => '2026'), 2026);
$leftovers = glob($rgDir . '/*.tmp.*');
rg_check($leftovers === array() || $leftovers === false, '原子替换后无临时文件残留');

// ---------------------------------------------------------------------------
// 4b. 模板判定 / 安全标量读取（register.php 门禁依赖的助手）
// ---------------------------------------------------------------------------
rg_check(academic_registration_policy_template_requires_selection('syzoj') === true, 'syzoj 模板需要注册范围校验');
rg_check(academic_registration_policy_template_requires_selection('sta_sty') === true, 'sta_sty 模板需要注册范围校验');
rg_check(academic_registration_policy_template_requires_selection('bs3') === false, 'bs3 模板不强制校验（保留自由填写）');
rg_check(academic_registration_policy_template_requires_selection('sweet') === false, 'sweet 模板不强制校验（保留自由填写）');
rg_check(academic_registration_policy_template_requires_selection('') === false, '未知 / 空模板不强制校验');
$rgScalar = null;
rg_check(academic_registration_policy_scalar_request(array('x' => '  电气  '), 'x', $rgScalar) === '电气' && $rgScalar === true, '安全读取合法标量并去除首尾空白');
rg_check(academic_registration_policy_scalar_request(array('x' => array('a')), 'x', $rgScalar) === '' && $rgScalar === false, '安全读取把数组视为非标量');
rg_check(academic_registration_policy_scalar_request(array('x' => true), 'x', $rgScalar) === '' && $rgScalar === false, '安全读取把布尔视为非标量');
rg_check(academic_registration_policy_scalar_request(array(), 'x', $rgScalar) === '' && $rgScalar === false, '安全读取把缺失字段视为非标量');

// ---------------------------------------------------------------------------
// 5. 接线约束（源码层）
// ---------------------------------------------------------------------------
$regSrc = (string)file_get_contents(__DIR__ . '/../register.php');
$rulePos = strpos($regSrc, 'academic_directory_registration_class_allowed');
$insertPos = strpos($regSrc, 'INSERT INTO `users`');
rg_check(strpos($regSrc, 'academic_registration.php') !== false, 'register.php 引入注册年级策略模块');
rg_check($rulePos !== false && $insertPos !== false && $rulePos < $insertPos, 'register.php 在写 users 之前校验学院 + 班级');
rg_check(strpos($regSrc, 'academic_registration_policy_load') !== false, 'register.php 读取当前策略');
rg_check(strpos($regSrc, 'academic_registration_policy_template_requires_selection') !== false
    && strpos($regSrc, "isset(\$OJ_TEMPLATE) ? \$OJ_TEMPLATE : ''") !== false,
    'register.php 依据可信 OJ_TEMPLATE 判断是否校验（不看请求字段）');
rg_check(strpos($regSrc, "if ((string)\$xueYuan !== '')") === false, 'register.php 不再用请求字段作为启用条件（避免空 / 缺失绕过）');
rg_check(strpos($regSrc, '!$xueYuan_scalar || !$school_scalar') !== false, 'register.php 对非标量学院 / 班级显式拒绝');
rg_check(strpos($regSrc, '$xueYuan=academic_registration_policy_scalar_request') !== false
    && strpos($regSrc, '$school=academic_registration_policy_scalar_request') !== false,
    'register.php 安全读取学院 / 班级（缺失 / 数组不报警告）');
rg_check(strpos($regSrc, 'strlen($_POST[\'school\'])') === false, 'register.php 不再直接 strlen 请求数组');
rg_check(preg_match('/UPDATE|DELETE/i', substr($regSrc, max(0, $insertPos - 400), 400)) === 0, '校验发生在 INSERT 之前而不改写既有用户');

$gcSrc = (string)file_get_contents(__DIR__ . '/../getClass.php');
rg_check(strpos($gcSrc, 'academic_directory_registration_classes') !== false, 'getClass.php 注册模式使用范围查询');
rg_check(strpos($gcSrc, 'academic_directory_classes($nj, $xy)') !== false, 'getClass.php 默认模式契约保留');
rg_check(strpos($gcSrc, "exit('[]')") !== false, 'getClass.php 无参数 / 无策略时返回空数组');
rg_check(
    strpos($gcSrc, 'if ($registration)') !== false
    && strpos($gcSrc, 'require_once(\'./include/cache_start.php\')') !== false
    && strpos($gcSrc, 'if ($registration)') < strpos($gcSrc, 'require_once(\'./include/cache_start.php\')'),
    'getClass.php 注册模式在默认页面缓存之前返回（管理员保存后立即生效）'
);

$rpSrc = (string)file_get_contents(__DIR__ . '/../registerpage.php');
rg_check(strpos($rpSrc, 'cache_start.php') === false && strpos($rpSrc, 'cache_end.php') === false, '注册页不再使用输出缓存');
rg_check(strpos($rpSrc, 'academic_registration_policy_load') !== false, '注册页读取策略');
rg_check(strpos($rpSrc, '$xueYuan = academic_directory_colleges();') !== false, '其它模板沿用变量保持原有赋值');

$tplSrc = (string)file_get_contents(__DIR__ . '/../template/syzoj/registerpage.php');
rg_check(strpos($tplSrc, 'school_manual') === false, 'syzoj 注册模板不再提供手填班级兜底');
rg_check(strpos($tplSrc, 'registration: 1') !== false, '前端调用注册范围端点');
rg_check(strpos($tplSrc, 'register_xueYuan') !== false, '前端使用仅含合格班级的学院列表');
rg_check(strpos($tplSrc, '暂无可注册班级') !== false, '空列表时给出联系管理员提示');
rg_check(strpos($tplSrc, '请选择专业班级') !== false, '班级下拉始终带必填占位');
rg_check(strpos($tplSrc, 'sel.val("")') !== false, '不静默选中第一个班级');
rg_check(strpos($tplSrc, 'role="alert"') !== false && strpos($tplSrc, 'role="status"') !== false, '空列表 / 失败具备可读状态角色');

// R2: oj-bundle.css 里 .ui.form 下的 error / warning 消息默认 display:none，
// 仅靠移除 hidden 不会显示；必须带 visible 或使用会被显示的变体。
$cssSrc = (string)file_get_contents(__DIR__ . '/../template/syzoj/css/oj-bundle.css');
rg_check(
    strpos($cssSrc, '.ui.form .error.message,.ui.form .success.message,.ui.form .warning.message{display:none}') !== false,
    'CSS 前提：.ui.form 下 error / warning / success 消息默认隐藏'
);
rg_check(
    strpos($cssSrc, '.ui.visible.visible.visible.visible.message{display:block}') !== false,
    'CSS 依据：visible 消息强制显示'
);
$tplFormStart = strpos($tplSrc, '<form ');
$tplFormEnd = strpos($tplSrc, '</form>');
$tplFormSrc = ($tplFormStart !== false && $tplFormEnd !== false)
    ? substr($tplSrc, $tplFormStart, $tplFormEnd - $tplFormStart)
    : '';
preg_match_all('/class="([^"]*\bmessage\b[^"]*)"/', $tplFormSrc, $tplMsgMatches);
$tplFormMsgCount = 0;
$tplFormMsgMissingVisible = 0;
foreach ($tplMsgMatches[1] as $tplMsgClass) {
    if (preg_match('/\b(error|warning|success)\b/', $tplMsgClass) !== 1) {
        continue;
    }
    $tplFormMsgCount++;
    if (preg_match('/\bvisible\b/', $tplMsgClass) !== 1) {
        $tplFormMsgMissingVisible++;
    }
}
rg_check(
    $tplFormMsgCount >= 2 && $tplFormMsgMissingVisible === 0,
    '表单内 error / warning 消息均带 visible（浏览器不会停在 display:none）'
);
rg_check(strpos($tplSrc, '.addClass("visible")') !== false, 'setClassStatus 显示状态时添加 visible');
rg_check(strpos($tplSrc, 'class="ui message" role="status"') !== false, '状态元素保留 ui message 基类 + role=status');

// ---------------------------------------------------------------------------
// 5b. getClass.php 注册模式端到端（隔离夹具，SQLite，无网络）
// ---------------------------------------------------------------------------
function rg_rmtree($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

$rgEndpoint = sys_get_temp_dir() . '/oj-reg-endpoint-' . bin2hex(random_bytes(6));
mkdir($rgEndpoint . '/include', 0700, true);
mkdir($rgEndpoint . '/data', 0700);
mkdir($rgEndpoint . '/tmp', 0700);
foreach (array('getClass.php', 'include/academic_directory.php', 'include/academic_registration.php', 'include/cache_start.php', 'include/cache_layer.php') as $relative) {
    copy(__DIR__ . '/../' . $relative, $rgEndpoint . '/' . $relative);
}
$rgEndpointData = $rgEndpoint . '/data';
file_put_contents(
    academic_registration_policy_path($rgEndpointData),
    json_encode(array('mode' => 'custom', 'min' => 2024, 'max' => 2026))
);

file_put_contents($rgEndpoint . '/include/db_info.inc.php', <<<'PHP'
<?php
$OJ_NAME = 'test';
$OJ_TEMPLATE = 'syzoj';
$OJ_LANG = 'cn';
$OJ_DATA = getenv('RG_ENDPOINT_DATA');
$dbh = null;
$RG_ENDPOINT_DSN = getenv('RG_ENDPOINT_DSN');
function pdo_query($sql)
{
    global $dbh, $RG_ENDPOINT_DSN;
    $args = func_get_args();
    array_shift($args);
    if (!($dbh instanceof PDO)) {
        $dbh = new PDO('sqlite:' . $RG_ENDPOINT_DSN);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $dbh->sqliteCreateFunction('CHAR_LENGTH', function ($v) {
            return $v === null ? null : mb_strlen((string)$v, 'UTF-8');
        }, 1);
    }
    $stmt = $dbh->prepare($sql);
    $stmt->execute($args);
    if (stripos(ltrim($sql), 'select') === 0) {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $stmt->rowCount();
}
PHP
);

file_put_contents($rgEndpoint . '/prepend.php', <<<'PHP'
<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', getenv('RG_ENDPOINT_ERRLOG'));
error_reporting(E_ALL);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/getClass.php';
$__rgGet = json_decode((string)getenv('RG_ENDPOINT_GET'), true);
$_GET = is_array($__rgGet) ? $__rgGet : array();
$_SERVER['REQUEST_URI'] = '/getClass.php?' . http_build_query($_GET);
PHP
);

$rgEndpointDb = $rgEndpoint . '/oj.sqlite';
$epdo = new PDO('sqlite:' . $rgEndpointDb);
$epdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$epdo->exec('CREATE TABLE collegiate (id INTEGER PRIMARY KEY, name TEXT, source_id TEXT)');
$epdo->exec('CREATE TABLE schoolList (school_id INTEGER PRIMARY KEY AUTOINCREMENT, num TEXT, value TEXT, join_time TEXT, collegiate_id INTEGER, source_id TEXT)');
$epdo->exec("INSERT INTO collegiate (id,name,source_id) VALUES (1,'电气与信息工程学院',NULL),(2,'机械工程学院',NULL)");
$epdo->exec("INSERT INTO schoolList (school_id,num,value,collegiate_id,source_id) VALUES
    (1,'2024010101','电2401',1,'e1'),
    (2,'2025010101','电2501',1,'e2'),
    (3,'2026010101','电2601',1,'e3'),
    (4,'2027010101','电2701',1,'e4'),
    (5,'2023010101','电2301',1,'e5'),
    (6,'2024010101','机械2401',2,'e6')");

function rg_endpoint_run($rgEndpoint, $get)
{
    $errlog = $rgEndpoint . '/err-' . bin2hex(random_bytes(4)) . '.log';
    putenv('RG_ENDPOINT_DSN=' . $rgEndpoint . '/oj.sqlite');
    putenv('RG_ENDPOINT_DATA=' . $rgEndpoint . '/data');
    putenv('RG_ENDPOINT_GET=' . json_encode($get));
    putenv('RG_ENDPOINT_ERRLOG=' . $errlog);
    putenv('TMPDIR=' . $rgEndpoint . '/tmp');
    $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $process = proc_open(
        array(PHP_BINARY, '-d', 'auto_prepend_file=' . $rgEndpoint . '/prepend.php', $rgEndpoint . '/getClass.php'),
        $descriptors,
        $pipes,
        $rgEndpoint
    );
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $log = is_file($errlog) ? (string)file_get_contents($errlog) : '';
    return array('stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit, 'log' => $log);
}

$regEp = rg_endpoint_run($rgEndpoint, array('registration' => '1', 'xy' => '电气与信息工程学院'));
rg_check($regEp['exit'] === 0, '注册模式 endpoint 正常结束');
rg_check(json_decode(trim($regEp['stdout']), true) === array(
    array('value' => '电2601'),
    array('value' => '电2501'),
    array('value' => '电2401'),
), '注册模式只返回范围内班级且最新在前');
rg_check(preg_match('/Warning|Notice|Deprecated|Fatal error|Undefined/i', $regEp['log']) === 0, '注册模式 endpoint 无 PHP 警告');

$regNj = rg_endpoint_run($rgEndpoint, array('registration' => '1', 'xy' => '电气与信息工程学院', 'nj' => '2025'));
rg_check(json_decode(trim($regNj['stdout']), true) === array(array('value' => '电2501')), '注册模式 nj 过滤生效');

$regNoXy = rg_endpoint_run($rgEndpoint, array('registration' => '1'));
rg_check(trim($regNoXy['stdout']) === '[]', '注册模式缺少 xy 返回空数组（无旁路）');

$defaultNoArgs = rg_endpoint_run($rgEndpoint, array());
rg_check(trim($defaultNoArgs['stdout']) === '[]', '默认模式无参数仍返回空数组');

$defaultXy = rg_endpoint_run($rgEndpoint, array('xy' => '电气与信息工程学院'));
$defaultValues = array();
foreach ((array)json_decode(trim($defaultXy['stdout']), true) as $row) {
    $defaultValues[] = $row['value'];
}
rg_check(in_array('电2701', $defaultValues, true) && in_array('电2301', $defaultValues, true), '默认 getClass 模式仍返回全部（历史行为保留）');
rg_check(strpos($regEp['stdout'], '电2701') === false && strpos($regEp['stdout'], '电2301') === false, '注册范围排除 2027 与 2023 年级');

rg_rmtree($rgEndpoint);
rg_check(!is_dir($rgEndpoint), 'endpoint 夹具已清理');

// ---------------------------------------------------------------------------
// 5c. register.php 服务端校验端到端（隔离夹具，SQLite，无网络）
// ---------------------------------------------------------------------------
$rgReg = sys_get_temp_dir() . '/oj-reg-register-' . bin2hex(random_bytes(6));
mkdir($rgReg . '/include', 0700, true);
mkdir($rgReg . '/data', 0700);
mkdir($rgReg . '/tmp', 0700);
foreach (array('register.php', 'include/my_func.inc.php', 'include/academic_directory.php', 'include/academic_registration.php') as $relative) {
    copy(__DIR__ . '/../' . $relative, $rgReg . '/' . $relative);
}
$rgRegPolicy = academic_registration_policy_path($rgReg . '/data');
file_put_contents($rgRegPolicy, json_encode(array('mode' => 'custom', 'min' => 2024, 'max' => 2026)));

file_put_contents($rgReg . '/include/db_info.inc.php', <<<'PHP'
<?php
$OJ_NAME = 'test';
$OJ_TEMPLATE = getenv('RG_REG_TEMPLATE');
if (!is_string($OJ_TEMPLATE) || $OJ_TEMPLATE === '') {
    $OJ_TEMPLATE = 'syzoj';
}
$OJ_LANG = 'cn';
$OJ_LOGIN_MOD = 'hustoj';
$OJ_VCODE = false;
$OJ_REG_NEED_CONFIRM = false;
$OJ_REGISTER = true;
$OJ_CSRF = false;
$OJ_DATA = getenv('RG_REG_DATA');
$dbh = null;
$RG_REG_DSN = getenv('RG_REG_DSN');
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
function pdo_query($sql)
{
    global $dbh, $RG_REG_DSN;
    $args = func_get_args();
    array_shift($args);
    if (!($dbh instanceof PDO)) {
        $dbh = new PDO('sqlite:' . $RG_REG_DSN);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $dbh->sqliteCreateFunction('NOW', function () {
            return date('Y-m-d H:i:s');
        });
        $dbh->sqliteCreateFunction('CHAR_LENGTH', function ($v) {
            return $v === null ? null : mb_strlen((string)$v, 'UTF-8');
        }, 1);
    }
    $stmt = $dbh->prepare($sql);
    $stmt->execute($args);
    if (stripos(ltrim($sql), 'select') === 0) {
        return $stmt->fetchAll();
    }
    if (stripos(ltrim($sql), 'insert') === 0) {
        return stripos($sql, 'users') !== false ? true : $dbh->lastInsertId();
    }
    return $stmt->rowCount();
}
PHP
);

file_put_contents($rgReg . '/prepend.php', <<<'PHP'
<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', getenv('RG_REG_ERRLOG'));
error_reporting(E_ALL);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/register.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/register.php';
session_save_path(getenv('RG_REG_TMP'));
session_id('rg' . bin2hex(random_bytes(6)));
session_start();
$_SESSION = json_decode((string)getenv('RG_REG_SESSION'), true);
if (!is_array($_SESSION)) {
    $_SESSION = array();
}
$_POST = json_decode((string)getenv('RG_REG_POST'), true);
if (!is_array($_POST)) {
    $_POST = array();
}
PHP
);

function rg_register_db($path)
{
    @unlink($path);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE collegiate (id INTEGER PRIMARY KEY, name TEXT, source_id TEXT)');
    $pdo->exec('CREATE TABLE schoolList (school_id INTEGER PRIMARY KEY AUTOINCREMENT, num TEXT, value TEXT, join_time TEXT, collegiate_id INTEGER, source_id TEXT)');
    $pdo->exec('CREATE TABLE users (user_id TEXT PRIMARY KEY, email TEXT, ip TEXT, password TEXT, reg_time TEXT, nick TEXT, school TEXT, defunct TEXT, xueYuan TEXT, qq TEXT, phone TEXT, register_num INTEGER)');
    $pdo->exec('CREATE TABLE loginlog (user_id TEXT, status TEXT, ip TEXT, time TEXT)');
    $pdo->exec('CREATE TABLE privilege (user_id TEXT, rightstr TEXT)');
    $pdo->exec("INSERT INTO collegiate (id,name,source_id) VALUES (1,'电气与信息工程学院',NULL),(2,'机械工程学院',NULL)");
    $pdo->exec("INSERT INTO schoolList (school_id,num,value,collegiate_id,source_id) VALUES
        (1,'2024010101','电2401',1,'r1'),
        (2,'2025010101','电2501',1,'r2'),
        (3,'2026010101','电2601',1,'r3'),
        (4,'2027010101','电2701',1,'r4'),
        (5,'2023010101','电2301',1,'r5')");
}

function rg_register_run($rgReg, $dbPath, $xueYuan, $school, $userId, $template = 'syzoj', $omitFields = array())
{
    rg_register_db($dbPath);
    $errlog = $rgReg . '/err-' . bin2hex(random_bytes(4)) . '.log';
    putenv('RG_REG_DSN=' . $dbPath);
    putenv('RG_REG_DATA=' . $rgReg . '/data');
    putenv('RG_REG_TMP=' . $rgReg . '/tmp');
    putenv('RG_REG_ERRLOG=' . $errlog);
    putenv('TMPDIR=' . $rgReg . '/tmp');
    putenv('RG_REG_TEMPLATE=' . $template);
    $post = array(
        'user_id' => $userId,
        'nick' => '测试用户',
        'password' => 'secret123',
        'rptpassword' => 'secret123',
        'phone' => '13800138000',
        'qq' => '123456',
        'vcode' => '',
        'code' => '123456',
        'email' => 'reg@example.com',
    );
    if (!in_array('xueYuan', $omitFields, true)) {
        $post['xueYuan'] = $xueYuan;
    }
    if (!in_array('school', $omitFields, true)) {
        $post['school'] = $school;
    }
    putenv('RG_REG_POST=' . json_encode($post));
    putenv('RG_REG_SESSION=' . json_encode(array(
        'email' => 'reg@example.com',
        'code' => '123456',
        'time' => time() + 300,
    )));
    $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $process = proc_open(
        array(PHP_BINARY, '-d', 'auto_prepend_file=' . $rgReg . '/prepend.php', $rgReg . '/register.php'),
        $descriptors,
        $pipes,
        $rgReg
    );
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $verify = new PDO('sqlite:' . $dbPath);
    $count = (int)$verify->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $row = $verify->query('SELECT user_id, xueYuan, school FROM users LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    return array(
        'stdout' => $stdout,
        'stderr' => $stderr,
        'exit' => $exit,
        'log' => is_file($errlog) ? (string)file_get_contents($errlog) : '',
        'count' => $count,
        'row' => $row,
    );
}

$validReg = rg_register_run($rgReg, $rgReg . '/valid.sqlite', '电气与信息工程学院', '电2601', 'regtest001');
rg_check($validReg['exit'] === 0 && $validReg['count'] === 1, '范围内合法组合：写入 1 个用户');
rg_check(is_array($validReg['row']) && $validReg['row']['xueYuan'] === '电气与信息工程学院' && $validReg['row']['school'] === '电2601', '写入的学院 + 班级与提交一致');
rg_check(strpos($validReg['stdout'], '注册成功') !== false, '合法注册给出成功提示');
rg_check(preg_match('/Warning|Notice|Deprecated|Fatal error|Undefined/i', $validReg['log']) === 0, '合法注册无 PHP 警告');

$graduated = rg_register_run($rgReg, $rgReg . '/graduated.sqlite', '电气与信息工程学院', '电2301', 'regtest002');
rg_check($graduated['count'] === 0, '已毕业班级被拒绝（未写 users）');
rg_check(strpos($graduated['stdout'], '学院与专业班级不匹配') !== false, '已毕业班级给出清晰中文错误');
rg_check(preg_match('/Warning|Notice|Deprecated|Fatal error|Undefined/i', $graduated['log']) === 0, '拒绝路径无 PHP 警告');

$future = rg_register_run($rgReg, $rgReg . '/future.sqlite', '电气与信息工程学院', '电2701', 'regtest003');
rg_check($future['count'] === 0, '未来年级被拒绝（未写 users）');

$mismatch = rg_register_run($rgReg, $rgReg . '/mismatch.sqlite', '机械工程学院', '电2601', 'regtest004');
rg_check($mismatch['count'] === 0, '学院与班级不匹配被拒绝（未写 users）');

$arbitrary = rg_register_run($rgReg, $rgReg . '/arbitrary.sqlite', '任意学院', '任意班级', 'regtest005');
rg_check($arbitrary['count'] === 0, '任意拼凑组合被拒绝（未写 users）');

// syzoj（使用学院 + 班级下拉）：空 / 缺失 / 数组学院都必须拒绝，不能凭请求字段绕过策略。
$emptyCollege = rg_register_run($rgReg, $rgReg . '/empty-college.sqlite', '', '电2601', 'regtest008');
rg_check($emptyCollege['count'] === 0, 'syzoj 空学院被拒绝（未写 users）');
rg_check(strpos($emptyCollege['stdout'], '学院与专业班级不匹配') !== false, 'syzoj 空学院给出清晰中文错误');
rg_check(preg_match('/Warning|Notice|Deprecated|Fatal error|Undefined/i', $emptyCollege['log']) === 0, 'syzoj 空学院拒绝路径无 PHP 警告');

$missingCollege = rg_register_run($rgReg, $rgReg . '/missing-college.sqlite', '', '电2601', 'regtest009', 'syzoj', array('xueYuan'));
rg_check($missingCollege['count'] === 0, 'syzoj 缺失学院字段被拒绝（未写 users）');
rg_check(preg_match('/Warning|Notice|Deprecated|Fatal error|Undefined/i', $missingCollege['log']) === 0, 'syzoj 缺失学院无 PHP 警告');

$arrayCollege = rg_register_run($rgReg, $rgReg . '/array-college.sqlite', array('电气与信息工程学院'), '电2601', 'regtest010');
rg_check($arrayCollege['count'] === 0, 'syzoj 数组学院被拒绝（未写 users）');
rg_check(preg_match('/Warning|Notice|Deprecated|Fatal error|Undefined/i', $arrayCollege['log']) === 0, 'syzoj 数组学院无 PHP 警告');

$emptySchool = rg_register_run($rgReg, $rgReg . '/empty-school.sqlite', '电气与信息工程学院', '', 'regtest011');
rg_check($emptySchool['count'] === 0, 'syzoj 空班级被拒绝（未写 users）');

$missingSchool = rg_register_run($rgReg, $rgReg . '/missing-school.sqlite', '电气与信息工程学院', '', 'regtest012', 'syzoj', array('school'));
rg_check($missingSchool['count'] === 0, 'syzoj 缺失班级字段被拒绝（未写 users）');
rg_check(preg_match('/Warning|Notice|Deprecated|Fatal error|Undefined/i', $missingSchool['log']) === 0, 'syzoj 缺失班级无 PHP 警告');

$arraySchool = rg_register_run($rgReg, $rgReg . '/array-school.sqlite', '电气与信息工程学院', array('电2601'), 'regtest013');
rg_check($arraySchool['count'] === 0, 'syzoj 数组班级被拒绝（未写 users）');
rg_check(preg_match('/Warning|Notice|Deprecated|Fatal error|Undefined/i', $arraySchool['log']) === 0, 'syzoj 数组班级无 PHP 警告');

$arbitrarySchool = rg_register_run($rgReg, $rgReg . '/arbitrary-school.sqlite', '电气与信息工程学院', '任意班级', 'regtest014');
rg_check($arbitrarySchool['count'] === 0, 'syzoj 任意班级被拒绝（未写 users）');

// sta_sty 同样使用学院 + 班级下拉，空学院拒绝、范围内合法组合通过。
$staStyEmpty = rg_register_run($rgReg, $rgReg . '/sta-sty-empty.sqlite', '', '自由填写班级', 'regtest015', 'sta_sty');
rg_check($staStyEmpty['count'] === 0, 'sta_sty 空学院被拒绝（未写 users）');
$staStyValid = rg_register_run($rgReg, $rgReg . '/sta-sty-valid.sqlite', '电气与信息工程学院', '电2601', 'regtest016', 'sta_sty');
rg_check($staStyValid['count'] === 1, 'sta_sty 范围内合法组合可注册');

// 旧模板（bs3 / sweet 自由填写班级）必须显式配置模板后才跳过校验。
$legacyFreeText = rg_register_run($rgReg, $rgReg . '/legacy.sqlite', '', '自由填写班级', 'regtest007', 'bs3');
rg_check($legacyFreeText['count'] === 1, '显式配置 bs3 旧模板（自由填写班级）注册不受影响');
rg_check(preg_match('/Warning|Notice|Deprecated|Fatal error|Undefined/i', $legacyFreeText['log']) === 0, 'bs3 旧模板路径无 PHP 警告');
$legacySweet = rg_register_run($rgReg, $rgReg . '/legacy-sweet.sqlite', '', '自由填写班级', 'regtest017', 'sweet');
rg_check($legacySweet['count'] === 1, '显式配置 sweet 旧模板注册不受影响');

file_put_contents($rgRegPolicy, '{ broken policy');
$brokenReg = rg_register_run($rgReg, $rgReg . '/broken.sqlite', '电气与信息工程学院', '电2601', 'regtest006');
rg_check($brokenReg['count'] === 0, '策略文件损坏时失败关闭（未写 users）');
rg_check(strpos($brokenReg['stdout'], '注册年级范围配置异常') !== false, '策略损坏给出可读错误');
$brokenEmpty = rg_register_run($rgReg, $rgReg . '/broken-empty.sqlite', '', '电2601', 'regtest018');
rg_check($brokenEmpty['count'] === 0, '策略损坏 + 空学院同样失败关闭（未写 users）');

rg_rmtree($rgReg);
rg_check(!is_dir($rgReg), 'register.php 夹具已清理');

// ---------------------------------------------------------------------------
// 清理
// ---------------------------------------------------------------------------
foreach ((array)glob($rgDir . '/*') as $f) {
    @unlink($f);
}
@rmdir($rgDir);

echo "\n";
echo "共 {$GLOBALS['RG_CHECKS']} 项检查，失败 {$GLOBALS['RG_FAIL']} 项\n";
exit($GLOBALS['RG_FAIL'] === 0 ? 0 : 1);
