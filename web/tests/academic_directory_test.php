<?php
/**
 * 学院班级目录离线测试。
 *
 * 使用 SQLite 内存库 + pdo_query 桩验证查询语义与纯逻辑（校验/计划/幂等）。
 * 不连接任何真实 MySQL，也不检验 MySQL DDL/锁/事务（那需要真库，见集成测试说明）。
 *
 * 运行：php web/tests/academic_directory_test.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$GLOBALS['FAILURES'] = 0;
$GLOBALS['CHECKS'] = 0;

function check($condition, $message)
{
    $GLOBALS['CHECKS']++;
    if ($condition) {
        echo "PASS: $message\n";
    } else {
        $GLOBALS['FAILURES']++;
        echo "FAIL: $message\n";
    }
}

function check_throws($callable, $message)
{
    try {
        $callable();
        check(false, $message . '（未抛异常）');
    } catch (Throwable $e) {
        check(true, $message);
    }
}

// ---------------------------------------------------------------------------
// SQLite 桩
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
require_once __DIR__ . '/../include/academic_directory_sync.php';

$pdo = $GLOBALS['TEST_PDO'];
$pdo->exec('CREATE TABLE collegiate (id INTEGER PRIMARY KEY, name TEXT, source_id TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uniq_collegiate_source_id ON collegiate(source_id)');
$pdo->exec('CREATE TABLE schoolList (school_id INTEGER PRIMARY KEY AUTOINCREMENT, num TEXT, value TEXT, join_time TEXT, collegiate_id INTEGER, source_id TEXT)');

$pdo->exec("INSERT INTO collegiate (id,name,source_id) VALUES
    (1,'电气与信息工程学院',NULL),
    (2,'机械工程学院',NULL),
    (5,'经济学院',NULL),
    (80,'研究生院（研究生工作部）','36')");

// 历史旧行 / 权威源行 / 32 位源ID / 11 位编号 / 8 位旧编号
$pdo->exec("INSERT INTO schoolList (school_id,num,value,collegiate_id,source_id) VALUES
    (1,'2004010101','电气工程0401',2,NULL),
    (2,'2004010101','电气工程0401',1,'2004010101'),
    (3,'02011202','电气工程0202',1,NULL),
    (4,'2014360101','动力工程2014',80,NULL),
    (5,'w2025010101','网络工程2501',1,'w2025010101'),
    (6,'2026010101','电气工程2601',1,'2026010101'),
    (7,'2024010101','电气工程2401',1,NULL),
    (8,'03010101','经济工程0301',5,NULL),
    (9,'2004010163','电气工程0401',1,NULL),
    (10,'03010201','机械工程2401',1,'0123456789abcdef0123456789abcdef'),
    (11,'03010202','机械工程2401',2,'w2025010102')");

// ---------------------------------------------------------------------------
// 查询测试
// ---------------------------------------------------------------------------
$colleges = academic_directory_colleges();
check(count($colleges) === 4, 'academic_directory_colleges 读取 4 个学院');
check($colleges[0] === array('电气与信息工程学院', '01'), '学院返回 [名称, 两位代码]');
check($colleges[3] === array('研究生院（研究生工作部）', '80'), 'id=80 仍以两位代码返回');

check(academic_directory_latest_year() === '2026', '最新年份来自 10/11 位编号前 4 位');
check(academic_directory_years(8) === array('26', '24', '14', '04'), '年级选项来自编号且倒序（8 位旧编号不参与）');

$jixie = array_column(academic_directory_classes('', '机械工程学院'), 'value');
sort($jixie);
check(!in_array('电气工程0401', $jixie, true), '同名旧错配行不再出现在错误学院');
check(in_array('机械工程2401', $jixie, true), '同名多条源记录按各自学院正确查询');

$dianqi = array_column(academic_directory_classes('', '电气与信息工程学院'), 'value');
sort($dianqi);
check(count($dianqi) === count(array_unique($dianqi)), 'getClass 结果按名称去重');
check(count($dianqi) === 6, '电气学院返回 6 个去重班级');

$jingji = array_column(academic_directory_classes('', '经济学院'), 'value');
check($jingji === array('经济工程0301'), '没有源行时保留旧行行为（经济学院）');

$yanjiusheng = array_column(academic_directory_classes('', '80'), 'value');
check($yanjiusheng === array('动力工程2014'), '支持用代码定位学院');
$yanjiusheng2 = array_column(academic_directory_classes('', '研究生院（研究生工作部）'), 'value');
check($yanjiusheng2 === array('动力工程2014'), '支持用名称定位学院');

$y2024 = array_column(academic_directory_classes('2024', '电气与信息工程学院'), 'value');
check($y2024 === array('电气工程2401'), '按派生年份过滤');
$y2004 = array_column(academic_directory_classes('2004', '电气与信息工程学院'), 'value');
check($y2004 === array('电气工程0401'), '8 位旧编号不被当作年份');
check(academic_directory_classes('', '不存在的学院') === array(), '未知学院返回空');
check(academic_directory_classes('', '') === array(), '无筛选参数返回空数组（原 getClass 契约）');

// endpoint 回归：无筛选参数时 getClass.php 直接返回 []（不依赖数据库）
$endpointDescriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
$endpointProc = proc_open(
    array(PHP_BINARY, __DIR__ . '/../getClass.php'),
    $endpointDescriptors,
    $endpointPipes,
    __DIR__ . '/..'
);
fclose($endpointPipes[0]);
$endpointOut = stream_get_contents($endpointPipes[1]);
$endpointErr = stream_get_contents($endpointPipes[2]);
fclose($endpointPipes[1]);
fclose($endpointPipes[2]);
proc_close($endpointProc);
check(trim($endpointOut) === '[]', 'getClass.php 无参数 endpoint 返回 []');

$recent = academic_directory_recent_classes_by_college(2023);
check(count($recent) === 2 && $recent[0]['xy_num'] === '01', '修改资料分组查询返回两位学院代码');

// ---------------------------------------------------------------------------
// 快照校验测试
// ---------------------------------------------------------------------------
function valid_snapshot()
{
    return array(
        'colleges' => array(
            array('source_id' => '01', 'code' => '01', 'name' => '电气与信息工程学院'),
            array('source_id' => '36', 'code' => '80', 'name' => '研究生院（研究生工作部）'),
        ),
        'total' => 2,
        'classes' => array(
            array('field0' => '0123456789abcdef0123456789abcdef', 'bh' => '2024010101', 'bj' => '电气工程2401', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
            array('field0' => 'w2025010101', 'bh' => 'w2025010101', 'bj' => '网络工程2501', 'field6' => '36', 'xx0301$dwmc' => '研究生院（研究生工作部）'),
        ),
    );
}

$norm = academic_directory_snapshot_validate(valid_snapshot());
check($norm['total'] === 2, '合法快照校验通过');
check($norm['colleges'][1]['code'] === 80, '学院 code 解析为本地数字 id');
check(strlen($norm['classes'][0]['source_id']) === 32, '支持 32 位稳定源ID');
check(strlen($norm['classes'][1]['bh']) === 11, '支持 11 位显示编号');

check_throws(function () {
    $s = valid_snapshot();
    $s['classes'][0]['field6'] = '99';
    academic_directory_snapshot_validate($s);
}, '未知学院拒绝');

check_throws(function () {
    $s = valid_snapshot();
    $s['colleges'][] = array('source_id' => '01', 'code' => '02', 'name' => '重复');
    academic_directory_snapshot_validate($s);
}, '重复学院 source_id 拒绝');

check_throws(function () {
    $s = valid_snapshot();
    $s['total'] = 5;
    academic_directory_snapshot_validate($s);
}, 'total 与条数不一致拒绝');

check_throws(function () {
    $s = valid_snapshot();
    unset($s['total']);
    academic_directory_snapshot_validate($s);
}, '缺少 total 拒绝');

check_throws(function () {
    $s = valid_snapshot();
    $s['total'] = null;
    academic_directory_snapshot_validate($s);
}, 'total 为 null 拒绝');

check_throws(function () {
    $s = valid_snapshot();
    $s['total'] = '2';
    academic_directory_snapshot_validate($s);
}, 'total 非法类型（字符串）拒绝');

check_throws(function () {
    $s = valid_snapshot();
    $s['total'] = 0;
    academic_directory_snapshot_validate($s);
}, 'total 非正数拒绝');

check_throws(function () {
    $s = valid_snapshot();
    $s['classes'] = array();
    academic_directory_snapshot_validate($s);
}, '空快照拒绝');

check_throws(function () {
    $s = valid_snapshot();
    $s['classes'][1]['field0'] = $s['classes'][0]['field0'];
    academic_directory_snapshot_validate($s);
}, '重复班级稳定源ID拒绝');

check_throws(function () {
    academic_directory_snapshot_validate(array('colleges' => array(), 'classes' => array()));
}, '坏结构拒绝');

// ---------------------------------------------------------------------------
// 同步范围：仅 2024 级及以后（含 2024）
// ---------------------------------------------------------------------------
check(academic_directory_sync_year_in_scope('2024010101') === true, '2024 边界编号在范围内');
check(academic_directory_sync_year_in_scope('2025010101') === true, '2025 编号在范围内');
check(academic_directory_sync_year_in_scope('2026010101') === true, '2026 编号在范围内');
check(academic_directory_sync_year_in_scope('20240101012') === true, '11 位纯数字编号按前 4 位判断');
check(academic_directory_sync_year_in_scope('2023123101') === false, '2023 编号不在范围内');
check(academic_directory_sync_year_in_scope('2023010101') === false, '2023 学年编号不在范围内');
check(academic_directory_sync_year_in_scope('2004010101') === false, '更早年级编号不在范围内');
check(academic_directory_sync_year_in_scope('02011202') === false, '8 位旧编号不参与（不猜年级）');
check(academic_directory_sync_year_in_scope('w2025010101') === false, '含字母编号不参与');
check(academic_directory_sync_year_in_scope('202401010') === false, '9 位编号不参与');
check(academic_directory_sync_year_in_scope('0123456789abcdef0123456789abcdef') === false, '32 位稳定源 ID 不被当作编号');
check(academic_directory_sync_year_in_scope(null) === false && academic_directory_sync_year_in_scope('') === false, '空编号不参与');

function scope_snapshot_fixture()
{
    return array(
        'colleges' => array(
            array('source_id' => '01', 'code' => '01', 'name' => '电气与信息工程学院'),
            array('source_id' => '36', 'code' => '80', 'name' => '研究生院（研究生工作部）'),
            array('source_id' => '99', 'code' => '99', 'name' => '仅旧年级引用学院'),
        ),
        'total' => 9,
        'classes' => array(
            array('field0' => 'k2024', 'bh' => '2024010101', 'bj' => '电气2401', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
            array('field0' => 'k2025', 'bh' => '2025010101', 'bj' => '研究生2501', 'field6' => '36', 'xx0301$dwmc' => '研究生院（研究生工作部）'),
            array('field0' => 'k2026', 'bh' => '2026010101', 'bj' => '电气2601', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
            array('field0' => 'k2023', 'bh' => '2023010101', 'bj' => '电气2301', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
            array('field0' => 'k2004', 'bh' => '2004010101', 'bj' => '电气0401', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
            array('field0' => 'k8', 'bh' => '02011202', 'bj' => '旧八位', 'field6' => '99', 'xx0301$dwmc' => '仅旧年级引用学院'),
            array('field0' => 'kw', 'bh' => 'w2025010101', 'bj' => '字母编号', 'field6' => '36', 'xx0301$dwmc' => '研究生院（研究生工作部）'),
            array('field0' => 'k9', 'bh' => '202401010', 'bj' => '九位编号', 'field6' => '99', 'xx0301$dwmc' => '仅旧年级引用学院'),
            array('field0' => '0123456789abcdef0123456789abcdef', 'bh' => '2024010102', 'bj' => '三十二位源ID', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
        ),
    );
}

$scoped = academic_directory_sync_scope_snapshot(scope_snapshot_fixture());
$scopedBh = array_column($scoped['classes'], 'bh');
sort($scopedBh);
check($scoped['total'] === 4 && count($scoped['classes']) === 4, '范围筛选只保留 2024 级及以后班级');
check(
    $scopedBh === array('2024010101', '2024010102', '2025010101', '2026010101'),
    '2024 边界与 2025/2026 保留，2023 及更早被排除'
);
check(array_column($scoped['colleges'], 'source_id') === array('01', '36'), '学院只保留被范围内班级引用者');
check($scoped['classes'][0]['field0'] === 'k2024', '32 位稳定源 ID 的班级按其显示编号正常保留');
check(academic_directory_snapshot_validate($scoped)['total'] === 4, '筛选后的快照仍通过完整校验');
check(academic_directory_sync_scope_snapshot($scoped) === $scoped, '范围筛选幂等（重复调用结果不变）');

$oldOnly = array(
    'colleges' => array(array('source_id' => '01', 'code' => '01', 'name' => '电气与信息工程学院')),
    'total' => 1,
    'classes' => array(
        array('field0' => 'old', 'bh' => '2004010101', 'bj' => '电气0401', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
    ),
);
check_throws(function () use ($oldOnly) {
    academic_directory_sync_scope_snapshot($oldOnly);
}, '没有 2024 级及以后班级时拒绝（不产生空预览）');
try {
    academic_directory_sync_scope_snapshot($oldOnly);
    check(false, '零匹配异常包含清晰提示');
} catch (Throwable $e) {
    check(strpos($e->getMessage(), '没有2024级及以后的班级') !== false, '零匹配异常包含“没有2024级及以后的班级”');
}

// 完整校验必须在筛选之前：坏数据即使只出现在旧年级行也必须拒绝。
check_throws(function () {
    $bad = scope_snapshot_fixture();
    $bad['total'] = 100; // total 与条数不一致
    academic_directory_sync_scope_snapshot($bad);
}, '筛选前仍拒绝无效 total（旧年级行不能被过滤掩盖）');
check_throws(function () {
    $bad = scope_snapshot_fixture();
    $bad['classes'][4]['field6'] = '不存在'; // 仅旧年级行引用未知学院
    academic_directory_sync_scope_snapshot($bad);
}, '筛选前仍拒绝旧年级行的未知学院归属');

// ---------------------------------------------------------------------------
// 计划 / 幂等 / 冲突测试
// ---------------------------------------------------------------------------
$norm = academic_directory_snapshot_validate(valid_snapshot());
$syncedColleges = array(
    array('id' => 1, 'name' => '电气与信息工程学院', 'source_id' => '01'),
    array('id' => 80, 'name' => '研究生院（研究生工作部）', 'source_id' => '36'),
);
$syncedClasses = array(
    array('school_id' => 10, 'num' => '2024010101', 'value' => '电气工程2401', 'collegiate_id' => 1, 'source_id' => '0123456789abcdef0123456789abcdef'),
    array('school_id' => 11, 'num' => 'w2025010101', 'value' => '网络工程2501', 'collegiate_id' => 80, 'source_id' => 'w2025010101'),
);
$idem = academic_directory_plan($norm, $syncedColleges, $syncedClasses);
$s = $idem['stats'];
check($s['college_insert'] === 0 && $s['college_update'] === 0 && $s['class_insert'] === 0 && $s['class_update'] === 0, '同一快照第二次 apply 新增更新均为 0');
check($idem['class_keeps'] === 2, '已同步且无变化记录计入保留');

$plan = academic_directory_plan($norm, array(array('id' => 1, 'name' => '电气与信息工程学院', 'source_id' => '01')), array(
    array('school_id' => 20, 'num' => '2024010101', 'value' => '电气工程2401', 'collegiate_id' => null, 'source_id' => null),
));
$s = $plan['stats'];
check($s['class_update'] === 1 && $s['class_adopted'] === 1, '旧班级按唯一 num+name 建立映射（不删除）');
check($s['class_insert'] === 1, '无法映射的源班级新增为独立源记录');

$plan = academic_directory_plan($norm, $syncedColleges, array(
    array('school_id' => 30, 'num' => '2004010199', 'value' => '电气工程2401', 'collegiate_id' => 1, 'source_id' => null),
    array('school_id' => 31, 'num' => '2004010188', 'value' => '电气工程2401', 'collegiate_id' => 1, 'source_id' => null),
));
check($plan['stats']['class_conflict'] === 1 && $plan['stats']['class_insert'] === 2, '多个候选不合并，新增并报告冲突');

$preserve = academic_directory_plan($norm, $syncedColleges, array_merge($syncedClasses, array(
    array('school_id' => 99, 'num' => '03010101', 'value' => '经济工程0301', 'collegiate_id' => 5, 'source_id' => null),
)));
$referenced = array();
foreach (array('class_inserts', 'class_updates') as $key) {
    foreach ($preserve[$key] as $op) {
        if (isset($op['school_id'])) {
            $referenced[] = $op['school_id'];
        }
    }
}
check(!in_array(99, $referenced, true), '快照中缺失的旧行保留且不被改动');

check_throws(function () use ($norm) {
    academic_directory_plan($norm, array(array('id' => 1, 'name' => '别的学院', 'source_id' => '99')), array());
}, '学院 source_id/code 映射冲突拒绝整次写');

check_throws(function () {
    // 已同步 source_id=36 的本地 id 是 80；快照把它的 code 改成 81 必须拒绝，
    // 不能隐式改写本地 id（反向映射冲突）。
    $s = valid_snapshot();
    $s['colleges'][1]['code'] = '81';
    $normalized = academic_directory_snapshot_validate($s);
    academic_directory_plan($normalized, array(
        array('id' => 1, 'name' => '电气与信息工程学院', 'source_id' => '01'),
        array('id' => 80, 'name' => '研究生院（研究生工作部）', 'source_id' => '36'),
    ), array());
}, '已同步 source_id 的 code 与本地 id 不一致拒绝');

$renamed = valid_snapshot();
$renamed['colleges'][1]['name'] = '研究生院';
foreach ($renamed['classes'] as $i => $c) {
    if ($c['field6'] === '36') {
        $renamed['classes'][$i]['xx0301$dwmc'] = '研究生院';
    }
}
$renamePlan = academic_directory_plan(academic_directory_snapshot_validate($renamed), $syncedColleges, $syncedClasses);
check(
    $renamePlan['stats']['college_update'] === 1 && $renamePlan['stats']['college_insert'] === 0,
    '同一 source_id 仅改名仍允许更新（不受反向映射冲突影响）'
);

check_throws(function () use ($norm) {
    academic_directory_plan($norm, array(
        array('id' => 1, 'name' => 'A', 'source_id' => null),
        array('id' => 1, 'name' => 'B', 'source_id' => null),
    ), array());
}, 'collegiate 重复 id 拒绝');

// 代码层约束：不写 users、getClass 不再按编号猜学院、页面不再硬编码学院
$syncSrc = file_get_contents(__DIR__ . '/../include/academic_directory_sync.php');
check(preg_match('/(INSERT|UPDATE|DELETE)[^;]*\busers\b/i', $syncSrc) === 0, '同步逻辑不涉及 users');

$importSrc = file_get_contents(__DIR__ . '/../cli/academic_directory_import.php');
check(preg_match('/(INSERT|UPDATE|DELETE)[^;]*\busers\b/i', $importSrc) === 0, 'importer 不写 users');

$getClassSrc = file_get_contents(__DIR__ . '/../getClass.php');
check(strpos($getClassSrc, 'SUBSTR(num, 5, 2)') === false, 'getClass 不再按编号第 5-6 位猜学院');

foreach (array('registerpage.php', 'loginpage.php', 'modifypage.php', 'ranklist.php') as $page) {
    $src = file_get_contents(__DIR__ . '/../' . $page);
    check(strpos($src, '电气与信息工程学院", "01"') === false, "$page 不再硬编码学院数组");
}

// collegiate 为空时回退到页面既有 17 学院
$pdo->exec('DELETE FROM collegiate');
check(count(academic_directory_colleges()) === 17, 'collegiate 为空时回退 17 学院不丢下拉');

echo "\n";
echo "共 {$GLOBALS['CHECKS']} 项检查，失败 {$GLOBALS['FAILURES']} 项\n";
exit($GLOBALS['FAILURES'] === 0 ? 0 : 1);
