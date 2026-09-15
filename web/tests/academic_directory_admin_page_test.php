<?php
/**
 * 管理员手动同步学院班级：页面级回归（离线、无网络、无浏览器、无真实数据库）。
 *
 * 目标是补齐“真实执行 web/admin/academic_directory.php（含 navbar.php）”这一层，
 * 复现并锁定本轮修复：
 *   R1 db_info 的 $dbh 是惰性初始化：页面必须在判断 PDO 前触发 pdo_query；
 *   R2 页面结果变量改名 $syncResult，不能被 navbar 里查询 modify/users 的 $result 覆盖；
 *   R3 只有“预览确实成功生成计划”的 nonce 才渲染 / 接受确认同步，伪造 POST 拒绝；
 *   R4 初始页不得出现同步结果，结果/错误具备角色与可读状态。
 *
 * 隔离方式：在临时目录里复制当前产品页面 / navbar / include，并写入测试专用
 * db_info.inc.php 夹具（$dbh 初始为 null，首次 pdo_query 才连接 SQLite，符合生产契约）。
 * 用 SQLite 的 information_schema 附接库模拟只读的 migration 元数据，从而让真实
 * academic_directory_migration_gaps() / academic_directory_sync_execute() 在离线环境跑通。
 * 不连接、不读写任何真实数据库，也不访问网络。
 *
 * 运行：php web/tests/academic_directory_admin_page_test.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// ---------------------------------------------------------------------------
// worker：在一个全新 PHP 进程里像 Web 请求一样执行产品页面
// ---------------------------------------------------------------------------
if (($argv[1] ?? '') === 'worker') {
    $root = $argv[2];
    $case = json_decode(file_get_contents($argv[3]), true);

    // PHP 警告/通知写独立日志，便于断言“无 warning”。
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $case['errlog']);
    error_reporting(E_ALL);

    putenv('AD_TEST_DSN=' . $case['dsn']);
    putenv('AD_TEST_INFO=' . $case['info']);

    session_save_path($root . '/sessions');
    session_id($case['sid']);
    session_start();
    $_SESSION = $case['session'];
    $_SERVER['REQUEST_METHOD'] = $case['method'];
    $_SERVER['PHP_SELF'] = '/admin/academic_directory.php';
    $_POST = $case['post'];

    chdir($root . '/admin');
    ob_start();
    require $root . '/admin/academic_directory.php';
    $body = ob_get_clean();
    file_put_contents($case['out'], $body);

    $state = isset($_SESSION['test_academic_directory_sync']) ? $_SESSION['test_academic_directory_sync'] : null;
    file_put_contents($case['state_out'], json_encode(array(
        'has_state' => is_array($state),
        'preview_ok' => is_array($state) && !empty($state['preview_ok']),
        'preview_nonce' => is_array($state) && isset($state['preview_nonce']) ? $state['preview_nonce'] : null,
    )));
    session_write_close();
    exit(0);
}

// ---------------------------------------------------------------------------
// 断言工具
// ---------------------------------------------------------------------------
$GLOBALS['ADP_CHECKS'] = 0;
$GLOBALS['ADP_FAIL'] = 0;

function adp_check($condition, $message)
{
    $GLOBALS['ADP_CHECKS']++;
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $message . "\n";
    if (!$condition) {
        $GLOBALS['ADP_FAIL']++;
    }
}

function adp_has($haystack, $needle, $message)
{
    adp_check(strpos($haystack, $needle) !== false, $message);
}

function adp_lacks($haystack, $needle, $message)
{
    adp_check(strpos($haystack, $needle) === false, $message);
}

function adp_no_php_errors($logFile, $message)
{
    $log = is_file($logFile) ? (string)file_get_contents($logFile) : '';
    adp_check(preg_match('/Warning|Notice|Deprecated|Fatal error|Parse error|Uncaught|Undefined/i', $log) === 0, $message);
    if ($log !== '') {
        fwrite(STDERR, "PHP errors logged:\n$log\n");
    }
}

// 父进程也需要策略模块，用于计算期望的有效范围文案。
require_once dirname(__DIR__) . '/include/academic_registration.php';

// ---------------------------------------------------------------------------
// 临时隔离目录
// ---------------------------------------------------------------------------
$root = sys_get_temp_dir() . '/oj-ad-page-' . bin2hex(random_bytes(8));
mkdir($root, 0700, true);
mkdir($root . '/admin', 0700);
mkdir($root . '/include', 0700);
mkdir($root . '/sessions', 0700);
mkdir($root . '/data', 0700);
putenv('AD_TEST_DATA=' . $root . '/data');
$policyFile = academic_registration_policy_path($root . '/data');

foreach (array(
    '/admin/academic_directory.php',
    '/admin/navbar.php',
    '/include/academic_directory.php',
    '/include/academic_directory_sync.php',
    '/include/academic_directory_source.php',
    '/include/academic_registration.php',
    '/include/set_post_key.php',
) as $relative) {
    copy(dirname(__DIR__) . $relative, $root . $relative);
}

// 页面会 require admin-header.php；夹具只保留权限闸门，视觉由产品 navbar 提供。
file_put_contents($root . '/admin/admin-header.php', <<<'PHP'
<?php
require_once dirname(__FILE__) . '/../include/db_info.inc.php';
if (!isset($_SESSION[$OJ_NAME . '_administrator'])) {
    echo 'Please Login First!';
    exit(1);
}
PHP
);

// 测试夹具：模拟生产 db_info.inc.php 的契约——$dbh 直到首次 pdo_query 才建立。
file_put_contents($root . '/include/db_info.inc.php', <<<'PHP'
<?php
// 测试夹具，绝不包含真实凭证，也不连接生产数据库。
$OJ_NAME = 'test';
$OJ_TEMPLATE = 'bs3';
$OJ_LANG = 'cn';
$OJ_CSRF = false;
$OJ_ONLINE = false;
$dbh = null;
$AD_TEST_DSN = getenv('AD_TEST_DSN');
$AD_TEST_INFO = getenv('AD_TEST_INFO');
$OJ_DATA = getenv('AD_TEST_DATA');
if (!is_string($OJ_DATA) || $OJ_DATA === '') {
    $OJ_DATA = '/home/judge/data';
}

foreach (array(
    'MSG_HELP_SEEOJ', 'MSG_SEEOJ', 'MSG_HELP_SETMESSAGE', 'MSG_HELP_SETPASSWORD', 'MSG_SETPASSWORD',
    'MSG_HELP_GIVESOURCE', 'MSG_GIVESOURCE', 'MSG_HELP_PRIVILEGE_LIST', 'MSG_PRIVILEGE', 'MSG_LIST',
    'MSG_HELP_ADD_PRIVILEGE', 'MSG_ADD', 'MSG_HELP_PROBLEM_LIST', 'MSG_PROBLEM', 'MSG_HELP_ADD_PROBLEM',
    'MSG_HELP_IMPORT_PROBLEM', 'MSG_IMPORT', 'MSG_HELP_EXPORT_PROBLEM', 'MSG_EXPORT', 'MSG_HELP_REJUDGE',
    'MSG_REJUDGE', 'MSG_HELP_CONTEST_LIST', 'MSG_CONTEST', 'MSG_HELP_ADD_CONTEST', 'MSG_HELP_TEAMGENERATOR',
    'MSG_TEAMGENERATOR', 'MSG_HELP_UPDATE_DATABASE', 'MSG_UPDATE_DATABASE', 'MSG_ONLINE',
) as $__adpMsgVar) {
    $$__adpMsgVar = '';
}
unset($__adpMsgVar);

function pdo_query($sql)
{
    global $dbh, $AD_TEST_DSN, $AD_TEST_INFO;
    $args = func_get_args();
    array_shift($args);
    if (!($dbh instanceof PDO)) {
        $dbh = new PDO('sqlite:' . $AD_TEST_DSN);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $dbh->sqliteCreateFunction('NOW', function () {
            return date('Y-m-d H:i:s');
        });
        $dbh->sqliteCreateFunction('DATABASE', function () {
            return 'main';
        });
        if (is_string($AD_TEST_INFO) && $AD_TEST_INFO !== '') {
            $dbh->exec('ATTACH DATABASE ' . $dbh->quote($AD_TEST_INFO) . ' AS information_schema');
        }
    }
    $stmt = $dbh->prepare($sql);
    $stmt->execute($args);
    if (stripos(ltrim($sql), 'select') === 0) {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $dbh->lastInsertId();
}
PHP
);

// ---------------------------------------------------------------------------
// SQLite 夹具：真实主库 + 模拟 information_schema（只读元数据）
// ---------------------------------------------------------------------------
function adp_info_db($path)
{
    @unlink($path);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT, CHARACTER_MAXIMUM_LENGTH INTEGER)');
    $pdo->exec('CREATE TABLE TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, ENGINE TEXT)');
    $pdo->exec('CREATE TABLE STATISTICS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, INDEX_NAME TEXT, COLUMN_NAME TEXT, NON_UNIQUE INTEGER, SEQ_IN_INDEX INTEGER)');
    $columns = array(
        array('collegiate', 'id', null), array('collegiate', 'name', null), array('collegiate', 'source_id', 64),
        array('schoolList', 'school_id', null), array('schoolList', 'num', 64), array('schoolList', 'value', null),
        array('schoolList', 'collegiate_id', null), array('schoolList', 'source_id', 64),
    );
    $stmt = $pdo->prepare('INSERT INTO COLUMNS VALUES (?,?,?,?)');
    foreach ($columns as $column) {
        $stmt->execute(array('main', $column[0], $column[1], $column[2]));
    }
    $stmt = $pdo->prepare('INSERT INTO TABLES VALUES (?,?,?)');
    foreach (array('collegiate', 'schoolList') as $table) {
        $stmt->execute(array('main', $table, 'InnoDB'));
    }
    $stmt = $pdo->prepare('INSERT INTO STATISTICS VALUES (?,?,?,?,?,?)');
    foreach (array('collegiate', 'schoolList') as $table) {
        $stmt->execute(array('main', $table, 'uniq_source', 'source_id', 0, 1));
    }
}

function adp_info_empty_db($path)
{
    @unlink($path);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT, CHARACTER_MAXIMUM_LENGTH INTEGER)');
    $pdo->exec('CREATE TABLE TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, ENGINE TEXT)');
    $pdo->exec('CREATE TABLE STATISTICS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, INDEX_NAME TEXT, COLUMN_NAME TEXT, NON_UNIQUE INTEGER, SEQ_IN_INDEX INTEGER)');
}

function adp_oj_db($path, $scenario)
{
    @unlink($path);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE collegiate (id INTEGER PRIMARY KEY, name TEXT, source_id TEXT)');
    $pdo->exec('CREATE TABLE schoolList (school_id INTEGER PRIMARY KEY, num TEXT, value TEXT, collegiate_id INTEGER, source_id TEXT, join_time TEXT)');
    $pdo->exec('CREATE TABLE modify (id INTEGER PRIMARY KEY, status INTEGER)');
    $pdo->exec('CREATE TABLE users (user_id TEXT PRIMARY KEY, register_num INTEGER)');
    $pdo->exec('INSERT INTO modify VALUES (1,1)');
    $pdo->exec("INSERT INTO users VALUES ('u1',1)");
    if ($scenario === 'legacy') {
        $pdo->exec("INSERT INTO collegiate VALUES (99,'旧学院',NULL)");
    } elseif ($scenario === 'matching') {
        $pdo->exec("INSERT INTO collegiate VALUES (1,'电气与信息工程学院','01')");
        $pdo->exec("INSERT INTO collegiate VALUES (80,'研究生院（研究生工作部）','36')");
        $pdo->exec("INSERT INTO schoolList VALUES (1,'2024010101','电气工程0401',1,'c001','2020-01-01')");
        $pdo->exec("INSERT INTO schoolList VALUES (2,'2025010101','动力工程2014',80,'c002','2020-01-01')");
        $pdo->exec("INSERT INTO schoolList VALUES (3,'2024010102','电气工程0402',1,'c003','2020-01-01')");
    }
}

function adp_run($root, $case)
{
    $caseFile = $root . '/case.json';
    file_put_contents($caseFile, json_encode($case));
    $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $process = proc_open(array(PHP_BINARY, __FILE__, 'worker', $root, $caseFile), $descriptors, $pipes);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    return array(
        'body' => is_file($case['out']) ? (string)file_get_contents($case['out']) : '',
        'stdout' => $stdout,
        'stderr' => $stderr,
        'exit' => $exit,
        'state' => is_file($case['state_out']) ? json_decode((string)file_get_contents($case['state_out']), true) : null,
    );
}

$infoPath = $root . '/include/info.sqlite';
adp_info_db($infoPath);
$infoEmptyPath = $root . '/include/info-empty.sqlite';
adp_info_empty_db($infoEmptyPath);

$snapshot = array(
    'colleges' => array(
        array('source_id' => '01', 'code' => '01', 'name' => '电气与信息工程学院'),
        array('source_id' => '36', 'code' => '80', 'name' => '研究生院（研究生工作部）'),
    ),
    'classes' => array(
        array('field0' => 'c001', 'bh' => '2024010101', 'bj' => '电气工程0401', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
        array('field0' => 'c002', 'bh' => '2025010101', 'bj' => '动力工程2014', 'field6' => '36', 'xx0301$dwmc' => '研究生院（研究生工作部）'),
        array('field0' => 'c003', 'bh' => '2024010102', 'bj' => '电气工程0402', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
    ),
    'total' => 3,
);

$previewNonce = 'p' . bin2hex(random_bytes(8));
$postkey = 'k' . bin2hex(random_bytes(8));
$previewState = array(
    'challenge_nonce' => null,
    'challenge_expiry' => null,
    'cookies' => array(),
    'captcha_uri' => '',
    'preview_nonce' => $previewNonce,
    'preview_expiry' => time() + 600,
    'snapshot' => $snapshot,
);

$failed = false;
try {
    // ---------------- A. 初始 GET：不得出现同步结果，也不得有 PHP 警告 ----------------
    $legacyDb = $root . '/oj-legacy.sqlite';
    adp_oj_db($legacyDb, 'legacy');
    $caseA = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $legacyDb,
        'info' => $infoPath,
        'method' => 'GET',
        'post' => array(),
        'session' => array('test_administrator' => true, 'test_postkey' => $postkey),
        'out' => $root . '/out-a.html',
        'state_out' => $root . '/state-a.json',
        'errlog' => $root . '/err-a.log',
    );
    $a = adp_run($root, $caseA);
    adp_check($a['exit'] === 0, 'A 初始 GET 正常结束');
    adp_has($a['body'], '第一步：获取验证码', 'A 初始 GET 渲染页面主体');
    adp_lacks($a['body'], '同步结果', 'A 初始 GET 不显示同步结果（R2：navbar 不再覆盖页面结果变量）');
    adp_lacks($a['body'], 'name="action" value="apply"', 'A 无预览时不渲染确认同步表单');
    adp_no_php_errors($caseA['errlog'], 'A 初始 GET 没有 PHP 警告/未定义变量');

    // ---------------- B. 合法预览：正确计数、无警告、产生 apply 表单 ----------------
    $caseB = $caseA;
    $caseB['sid'] = 'adp' . bin2hex(random_bytes(6));
    $caseB['session'] = array(
        'test_administrator' => true,
        'test_postkey' => $postkey,
        'test_academic_directory_sync' => $previewState,
    );
    $caseB['out'] = $root . '/out-b.html';
    $caseB['state_out'] = $root . '/state-b.json';
    $caseB['errlog'] = $root . '/err-b.log';
    $b = adp_run($root, $caseB);
    adp_check($b['exit'] === 0, 'B 合法预览请求正常结束');
    adp_has($b['body'], '变更预览（尚未写入）', 'B 渲染变更预览卡片');
    adp_has($b['body'], '<b>2</b>源学院', 'B 预览源学院数为 2（R1：惰性连接后计划可生成）');
    adp_has($b['body'], '<b>3</b>源班级', 'B 预览源班级数为 3');
    adp_has($b['body'], '<b>2</b>学院新增', 'B 预览学院新增数为 2');
    adp_has($b['body'], '<b>3</b>班级新增', 'B 预览班级新增数为 3');
    adp_has($b['body'], 'name="action" value="apply"', 'B 成功预览后才渲染确认同步表单');
    adp_lacks($b['body'], '无法生成变更预览', 'B 预览成功时不显示失败兜底');
    adp_check(is_array($b['state']) && $b['state']['preview_ok'] === true, 'B 会话记录该 nonce 的预览已成功生成计划');
    adp_no_php_errors($caseB['errlog'], 'B 合法预览没有 PHP 警告');

    // ---------------- C. 确认同步：结果显示且不被 navbar 覆盖 ----------------
    $matchDb = $root . '/oj-match.sqlite';
    adp_oj_db($matchDb, 'matching');
    $caseC = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $matchDb,
        'info' => $infoPath,
        'method' => 'POST',
        'post' => array('action' => 'apply', 'preview_nonce' => $previewNonce, 'postkey' => $postkey),
        'session' => array(
            'test_administrator' => true,
            'test_postkey' => $postkey,
            'test_academic_directory_sync' => array_merge($previewState, array('preview_ok' => true)),
        ),
        'out' => $root . '/out-c.html',
        'state_out' => $root . '/state-c.json',
        'errlog' => $root . '/err-c.log',
    );
    $c = adp_run($root, $caseC);
    adp_check($c['exit'] === 0, 'C 确认同步请求正常结束');
    adp_has($c['body'], '同步结果', 'C 显示同步结果');
    adp_has($c['body'], '本次密码未保存', 'C 结果提示本次密码未保存');
    adp_has($c['body'], '本次无需更新', 'C 无变化时明确提示无需更新');
    adp_has($c['body'], '<b>2</b>源学院', 'C 结果显示真实源学院数（结果变量未被 navbar 覆盖）');
    adp_has($c['body'], '<b>3</b>源班级', 'C 结果显示真实源班级数');
    adp_has($c['body'], '<b>3</b>班级保留', 'C 结果显示真实班级保留数');
    adp_lacks($c['body'], '无法生成变更预览', 'C 成功同步不出现预览失败兜底');
    adp_check(is_array($c['state']) && $c['state']['has_state'] === false, 'C 确认后一次性消费会话快照');
    adp_no_php_errors($caseC['errlog'], 'C 确认同步没有 PHP 警告');
    $verify = new PDO('sqlite:' . $matchDb);
    adp_check((int)$verify->query('SELECT COUNT(*) FROM collegiate')->fetchColumn() === 2, 'C 同步后学院数一致（未误写）');
    adp_check((int)$verify->query('SELECT COUNT(*) FROM schoolList')->fetchColumn() === 3, 'C 同步后班级数一致（未误写）');

    // ---------------- D1. 预览生成失败（migration 不完整）：无确认按钮、清理状态、给出指引 ----------------
    $caseD1 = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $legacyDb,
        'info' => $infoEmptyPath, // information_schema 为空 => migration 检查发现缺列
        'method' => 'GET',
        'post' => array(),
        'session' => array(
            'test_administrator' => true,
            'test_postkey' => $postkey,
            'test_academic_directory_sync' => $previewState,
        ),
        'out' => $root . '/out-d1.html',
        'state_out' => $root . '/state-d1.json',
        'errlog' => $root . '/err-d1.log',
    );
    $d1 = adp_run($root, $caseD1);
    adp_check($d1['exit'] === 0, 'D1 migration 不完整时页面正常结束');
    adp_has($d1['body'], '数据库尚未完成必要升级', 'D1 预览失败给出可读提示');
    adp_has($d1['body'], '请联系系统管理员', 'D1 指明联系维护人员（可操作）');
    adp_lacks($d1['body'], 'name="action" value="apply"', 'D1 预览失败时没有确认同步按钮（R3）');
    adp_lacks($d1['body'], '同步结果', 'D1 预览失败不显示同步结果（不误报无变化）');
    adp_check(is_array($d1['state']) && $d1['state']['has_state'] === false, 'D1 预览失败清理不可用快照/nonce');
    adp_no_php_errors($caseD1['errlog'], 'D1 预览失败没有 PHP 警告');

    // ---------------- D2. 伪造确认：预览从未成功仍提交 apply => 拒绝且零写 ----------------
    $bypassDb = $root . '/oj-bypass.sqlite';
    adp_oj_db($bypassDb, 'matching');
    $caseD2 = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $bypassDb,
        'info' => $infoPath,
        'method' => 'POST',
        'post' => array('action' => 'apply', 'preview_nonce' => $previewNonce, 'postkey' => $postkey),
        // 注意：没有 preview_ok —— 模拟旧标签页 / 伪造 POST 绕过失败预览
        'session' => array(
            'test_administrator' => true,
            'test_postkey' => $postkey,
            'test_academic_directory_sync' => $previewState,
        ),
        'out' => $root . '/out-d2.html',
        'state_out' => $root . '/state-d2.json',
        'errlog' => $root . '/err-d2.log',
    );
    $d2 = adp_run($root, $caseD2);
    adp_check($d2['exit'] === 0, 'D2 伪造确认请求正常结束');
    adp_has($d2['body'], '预览已失效', 'D2 未成功预览的确认被拒绝（R3）');
    adp_lacks($d2['body'], '同步结果', 'D2 伪造确认不产生同步结果');
    adp_check(is_array($d2['state']) && $d2['state']['has_state'] === false, 'D2 拒绝后清理会话状态');
    $verify = new PDO('sqlite:' . $bypassDb);
    adp_check((int)$verify->query('SELECT COUNT(*) FROM collegiate')->fetchColumn() === 2, 'D2 伪造确认零写入（学院数不变）');
    adp_check((int)$verify->query('SELECT COUNT(*) FROM schoolList')->fetchColumn() === 3, 'D2 伪造确认零写入（班级数不变）');
    adp_no_php_errors($caseD2['errlog'], 'D2 伪造确认没有 PHP 警告');

    // ---------------- E. R5：仅学院更名（班级零变更）不得声称“无需更新” ----------------
    $collegeOnlySnapshot = $snapshot;
    $collegeOnlySnapshot['colleges'][0]['name'] = '电气与信息工程学院（更名后）';
    // 真实源站在学院更名时也会同步班级行内的学院名；班级本身（编号/名称/归属）不变。
    foreach ($collegeOnlySnapshot['classes'] as $index => $class) {
        if ($class['field6'] === '01') {
            $collegeOnlySnapshot['classes'][$index]['xx0301$dwmc'] = '电气与信息工程学院（更名后）';
        }
    }
    $caseE = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $matchDb,
        'info' => $infoPath,
        'method' => 'GET',
        'post' => array(),
        'session' => array(
            'test_administrator' => true,
            'test_postkey' => $postkey,
            'test_academic_directory_sync' => array_merge($previewState, array('snapshot' => $collegeOnlySnapshot)),
        ),
        'out' => $root . '/out-e.html',
        'state_out' => $root . '/state-e.json',
        'errlog' => $root . '/err-e.log',
    );
    $e = adp_run($root, $caseE);
    adp_check($e['exit'] === 0, 'E 仅学院更名预览正常结束');
    adp_has($e['body'], '<b>1</b>学院更新', 'E 仅学院更名时预览显示学院更新数为 1');
    adp_has($e['body'], '电气与信息工程学院（更名后）', 'E 仅学院更名时预览展示更名后的学院');
    adp_lacks($e['body'], '无需更新', 'E 仅学院更名时不得声称无需更新（R5）');
    adp_has($e['body'], 'name="action" value="apply"', 'E 仅学院更名时仍可确认同步');
    adp_no_php_errors($caseE['errlog'], 'E 仅学院更名预览没有 PHP 警告');

    // ---------------- F. R5 对照：学院/班级全一致时仍保留“无需更新” ----------------
    $caseF = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $matchDb,
        'info' => $infoPath,
        'method' => 'GET',
        'post' => array(),
        'session' => array(
            'test_administrator' => true,
            'test_postkey' => $postkey,
            'test_academic_directory_sync' => array_merge($previewState, array('snapshot' => $snapshot)),
        ),
        'out' => $root . '/out-f.html',
        'state_out' => $root . '/state-f.json',
        'errlog' => $root . '/err-f.log',
    );
    $f = adp_run($root, $caseF);
    adp_check($f['exit'] === 0, 'F 全一致预览正常结束');
    adp_has($f['body'], '<b>0</b>学院更新', 'F 全一致时学院更新数为 0');
    adp_has($f['body'], '无需更新', 'F 全一致时预览明确无需更新（R5 对照）');
    adp_lacks($f['body'], '变更样例', 'F 全一致时不渲染班级变更样例');
    adp_no_php_errors($caseF['errlog'], 'F 全一致预览没有 PHP 警告');

    // ---------------- G. 范围一致性：session 里的全量快照，预览与确认都只处理 2024 级及以后 ----------------
    $mixedSnapshot = array(
        'colleges' => array(
            array('source_id' => '01', 'code' => '01', 'name' => '电气与信息工程学院'),
            array('source_id' => '36', 'code' => '80', 'name' => '研究生院（研究生工作部）'),
        ),
        'classes' => array(
            array('field0' => 'g1', 'bh' => '2024010101', 'bj' => '电气2401', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
            array('field0' => 'g2', 'bh' => '2004010101', 'bj' => '电气0401', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
            array('field0' => 'g3', 'bh' => '2026010101', 'bj' => '动力2601', 'field6' => '36', 'xx0301$dwmc' => '研究生院（研究生工作部）'),
        ),
        'total' => 3,
    );
    $caseG1 = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $legacyDb,
        'info' => $infoPath,
        'method' => 'GET',
        'post' => array(),
        'session' => array(
            'test_administrator' => true,
            'test_postkey' => $postkey,
            'test_academic_directory_sync' => array_merge($previewState, array('snapshot' => $mixedSnapshot)),
        ),
        'out' => $root . '/out-g1.html',
        'state_out' => $root . '/state-g1.json',
        'errlog' => $root . '/err-g1.log',
    );
    $g1 = adp_run($root, $caseG1);
    adp_check($g1['exit'] === 0, 'G1 含旧年级的 session 快照预览正常结束');
    adp_has($g1['body'], '<b>2</b>源班级', 'G1 预览只统计 2024 级及以后（旧年级不进入统计）');
    adp_has($g1['body'], '<b>2</b>班级新增', 'G1 预览新增按范围内计算');
    adp_lacks($g1['body'], '<b>3</b>源班级', 'G1 预览不把旧年级算入');
    adp_has($g1['body'], 'name="action" value="apply"', 'G1 范围内预览仍可确认同步');
    adp_no_php_errors($caseG1['errlog'], 'G1 没有 PHP 警告');

    $scopeDb = $root . '/oj-scope.sqlite';
    adp_oj_db($scopeDb, 'legacy');
    $caseG2 = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $scopeDb,
        'info' => $infoPath,
        'method' => 'POST',
        'post' => array('action' => 'apply', 'preview_nonce' => $previewNonce, 'postkey' => $postkey),
        'session' => array(
            'test_administrator' => true,
            'test_postkey' => $postkey,
            'test_academic_directory_sync' => array_merge($previewState, array(
                'snapshot' => $mixedSnapshot,
                'preview_ok' => true,
            )),
        ),
        'out' => $root . '/out-g2.html',
        'state_out' => $root . '/state-g2.json',
        'errlog' => $root . '/err-g2.log',
    );
    $g2 = adp_run($root, $caseG2);
    adp_check($g2['exit'] === 0, 'G2 含旧年级的 session 快照确认同步正常结束');
    adp_has($g2['body'], '同步结果', 'G2 显示同步结果');
    adp_has($g2['body'], '<b>2</b>源班级', 'G2 确认同步统计与预览一致（范围内 2 班）');
    $verify = new PDO('sqlite:' . $scopeDb);
    adp_check((int)$verify->query('SELECT COUNT(*) FROM schoolList')->fetchColumn() === 2, 'G2 只写入范围内班级');
    adp_check((int)$verify->query("SELECT COUNT(*) FROM schoolList WHERE num LIKE '2004%'")->fetchColumn() === 0, 'G2 旧年级行未落库');
    adp_check((int)$verify->query('SELECT COUNT(*) FROM collegiate')->fetchColumn() === 3, 'G2 只新增被范围内班级引用的学院');
    adp_no_php_errors($caseG2['errlog'], 'G2 没有 PHP 警告');

    // ---------------- H. 零匹配：仅旧年级的 session 快照确认同步被拒绝且零写入 ----------------
    $oldOnlySnapshot = array(
        'colleges' => array(array('source_id' => '01', 'code' => '01', 'name' => '电气与信息工程学院')),
        'classes' => array(
            array('field0' => 'h1', 'bh' => '2004010101', 'bj' => '电气0401', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
        ),
        'total' => 1,
    );
    $zeroDb = $root . '/oj-zero.sqlite';
    adp_oj_db($zeroDb, 'legacy');
    $caseH = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $zeroDb,
        'info' => $infoPath,
        'method' => 'POST',
        'post' => array('action' => 'apply', 'preview_nonce' => $previewNonce, 'postkey' => $postkey),
        'session' => array(
            'test_administrator' => true,
            'test_postkey' => $postkey,
            'test_academic_directory_sync' => array_merge($previewState, array(
                'snapshot' => $oldOnlySnapshot,
                'preview_ok' => true,
            )),
        ),
        'out' => $root . '/out-h.html',
        'state_out' => $root . '/state-h.json',
        'errlog' => $root . '/err-h.log',
    );
    $h = adp_run($root, $caseH);
    adp_check($h['exit'] === 0, 'H 零匹配确认同步正常结束');
    adp_has($h['body'], '没有2024级及以后的班级', 'H 零匹配给出清晰提示');
    adp_lacks($h['body'], '同步结果', 'H 零匹配不显示同步结果');
    $verify = new PDO('sqlite:' . $zeroDb);
    adp_check((int)$verify->query('SELECT COUNT(*) FROM schoolList')->fetchColumn() === 0, 'H 零匹配零写入（schoolList 无变化）');
    adp_check((int)$verify->query('SELECT COUNT(*) FROM collegiate')->fetchColumn() === 1, 'H 零匹配零写入（学院无变化）');
    adp_no_php_errors($caseH['errlog'], 'H 没有 PHP 警告');

    // ---------------- I..P. 注册开放年级策略：GET 只读、CSRF、保存 / 拒绝、状态保留 ----------------
    $policyDb = $root . '/oj-policy.sqlite';
    adp_oj_db($policyDb, 'legacy');
    $policyCounts = function () use ($policyDb) {
        $pdo = new PDO('sqlite:' . $policyDb);
        return array(
            (int)$pdo->query('SELECT COUNT(*) FROM collegiate')->fetchColumn(),
            (int)$pdo->query('SELECT COUNT(*) FROM schoolList')->fetchColumn(),
        );
    };
    // 非预览的同步会话状态：策略保存不应清空它。
    $preservedSyncState = array(
        'challenge_nonce' => null,
        'challenge_expiry' => null,
        'cookies' => array(),
        'captcha_uri' => '',
        'preview_nonce' => '',
        'preview_expiry' => 0,
        'snapshot' => null,
    );

    // I. GET 只读：渲染策略卡片但不创建 / 修改文件
    $caseI = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $policyDb,
        'info' => $infoPath,
        'method' => 'GET',
        'post' => array(),
        'session' => array('test_administrator' => true, 'test_postkey' => $postkey),
        'out' => $root . '/out-i.html',
        'state_out' => $root . '/state-i.json',
        'errlog' => $root . '/err-i.log',
    );
    $i = adp_run($root, $caseI);
    adp_check($i['exit'] === 0, 'I 策略 GET 正常结束');
    adp_has($i['body'], '注册开放年级范围', 'I 渲染注册年级策略卡片');
    adp_has($i['body'], 'name="action" value="save_registration_policy"', 'I 提供保存策略表单');
    adp_has($i['body'], '自动更新', 'I 说明自动更新规则');
    adp_check(!file_exists($policyFile), 'I GET 只读，不创建策略文件');
    adp_no_php_errors($caseI['errlog'], 'I 策略 GET 没有 PHP 警告');

    // J. 合法自定义保存：成功、落盘、同步状态保留、数据库零写入
    $caseJ = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $policyDb,
        'info' => $infoPath,
        'method' => 'POST',
        'post' => array('action' => 'save_registration_policy', 'mode' => 'custom', 'min' => '2025', 'max' => '2026', 'postkey' => $postkey),
        'session' => array(
            'test_administrator' => true,
            'test_postkey' => $postkey,
            'test_academic_directory_sync' => $preservedSyncState,
        ),
        'out' => $root . '/out-j.html',
        'state_out' => $root . '/state-j.json',
        'errlog' => $root . '/err-j.log',
    );
    $j = adp_run($root, $caseJ);
    adp_check($j['exit'] === 0, 'J 自定义范围保存正常结束');
    adp_has($j['body'], '已保存', 'J 显示保存成功');
    adp_has($j['body'], '2025 - 2026', 'J 显示新的生效范围');
    adp_check(is_array($j['state']) && $j['state']['has_state'] === true, 'J 策略保存不清空同步 session 状态');
    $rawPolicy = json_decode((string)file_get_contents($policyFile), true);
    adp_check($rawPolicy['mode'] === 'custom' && $rawPolicy['min'] === 2025 && $rawPolicy['max'] === 2026, 'J 落盘为自定义 2025..2026');
    adp_check($policyCounts() === array(1, 0), 'J 策略保存零数据库写入');
    adp_no_php_errors($caseJ['errlog'], 'J 自定义保存没有 PHP 警告');

    // K. 非法范围：显式报错且旧文件原样
    $beforeK = (string)file_get_contents($policyFile);
    $caseK = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $policyDb,
        'info' => $infoPath,
        'method' => 'POST',
        'post' => array('action' => 'save_registration_policy', 'mode' => 'custom', 'min' => '2026', 'max' => '2025', 'postkey' => $postkey),
        'session' => array(
            'test_administrator' => true,
            'test_postkey' => $postkey,
            'test_academic_directory_sync' => $preservedSyncState,
        ),
        'out' => $root . '/out-k.html',
        'state_out' => $root . '/state-k.json',
        'errlog' => $root . '/err-k.log',
    );
    $k = adp_run($root, $caseK);
    adp_check($k['exit'] === 0, 'K 非法范围请求正常结束');
    adp_has($k['body'], '保存失败', 'K 非法范围显式报错');
    adp_check((string)file_get_contents($policyFile) === $beforeK, 'K 非法范围不修改旧文件');
    adp_check(is_array($k['state']) && $k['state']['has_state'] === true, 'K 拒绝后同步 session 状态仍在');
    adp_no_php_errors($caseK['errlog'], 'K 非法范围没有 PHP 警告');

    // L. CSRF 失败：403 且零写入
    $caseL = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $policyDb,
        'info' => $infoPath,
        'method' => 'POST',
        'post' => array('action' => 'save_registration_policy', 'mode' => 'auto', 'postkey' => 'wrong-key'),
        'session' => array('test_administrator' => true, 'test_postkey' => $postkey),
        'out' => $root . '/out-l.html',
        'state_out' => $root . '/state-l.json',
        'errlog' => $root . '/err-l.log',
    );
    $l = adp_run($root, $caseL);
    adp_has($l['stdout'], '403', 'L 错误 postkey 返回 403');
    adp_check((string)file_get_contents($policyFile) === $beforeK, 'L CSRF 失败零写入');
    adp_no_php_errors($caseL['errlog'], 'L CSRF 失败没有 PHP 警告');

    // M. 切回自动：落盘 auto，重新读取的有效范围与页面一致
    $caseM = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $policyDb,
        'info' => $infoPath,
        'method' => 'POST',
        'post' => array('action' => 'save_registration_policy', 'mode' => 'auto', 'postkey' => $postkey),
        'session' => array(
            'test_administrator' => true,
            'test_postkey' => $postkey,
            'test_academic_directory_sync' => $preservedSyncState,
        ),
        'out' => $root . '/out-m.html',
        'state_out' => $root . '/state-m.json',
        'errlog' => $root . '/err-m.log',
    );
    $m = adp_run($root, $caseM);
    adp_check($m['exit'] === 0, 'M 切回自动正常结束');
    adp_has($m['body'], '已保存', 'M 切回自动保存成功');
    $autoPolicy = academic_registration_policy_load($policyFile);
    adp_check($autoPolicy['mode'] === 'auto', 'M 落盘为自动模式');
    adp_has($m['body'], academic_registration_policy_effective_label($autoPolicy), 'M 页面显示重新读取后的自动有效范围');
    adp_check(is_array($m['state']) && $m['state']['has_state'] === true, 'M 切回自动不清空同步会话');
    adp_no_php_errors($caseM['errlog'], 'M 切回自动没有 PHP 警告');

    // N. 非管理员：403 且零写入
    $beforeN = (string)file_get_contents($policyFile);
    $caseN = array(
        'sid' => 'adp' . bin2hex(random_bytes(6)),
        'dsn' => $policyDb,
        'info' => $infoPath,
        'method' => 'POST',
        'post' => array('action' => 'save_registration_policy', 'mode' => 'custom', 'min' => '2024', 'max' => '2024', 'postkey' => $postkey),
        'session' => array('test_postkey' => $postkey),
        'out' => $root . '/out-n.html',
        'state_out' => $root . '/state-n.json',
        'errlog' => $root . '/err-n.log',
    );
    $n = adp_run($root, $caseN);
    adp_has($n['stdout'], '403', 'N 非管理员被拒绝');
    adp_check((string)file_get_contents($policyFile) === $beforeN, 'N 非管理员零写入');
    adp_no_php_errors($caseN['errlog'], 'N 非管理员没有 PHP 警告');

    // O. 损坏文件：显式提示，不伪装成成功
    file_put_contents($policyFile, '{ broken json');
    $caseO = $caseI;
    $caseO['sid'] = 'adp' . bin2hex(random_bytes(6));
    $caseO['out'] = $root . '/out-o.html';
    $caseO['state_out'] = $root . '/state-o.json';
    $caseO['errlog'] = $root . '/err-o.log';
    $o = adp_run($root, $caseO);
    adp_has($o['body'], '无法解析', 'O 损坏配置被显式提示');
    adp_lacks($o['body'], '已保存', 'O 损坏配置不会被当作保存成功');
    adp_no_php_errors($caseO['errlog'], 'O 损坏配置没有 PHP 警告');

    // P. 用合法配置修复损坏文件：说明已替换
    $caseP = $caseJ;
    $caseP['sid'] = 'adp' . bin2hex(random_bytes(6));
    $caseP['post'] = array('action' => 'save_registration_policy', 'mode' => 'custom', 'min' => '2024', 'max' => '2026', 'postkey' => $postkey);
    $caseP['out'] = $root . '/out-p.html';
    $caseP['state_out'] = $root . '/state-p.json';
    $caseP['errlog'] = $root . '/err-p.log';
    $p = adp_run($root, $caseP);
    adp_has($p['body'], '已用本次有效配置替换', 'P 修复损坏文件时显式说明');
    adp_check(academic_registration_policy_load($policyFile)['ok'] === true, 'P 修复后配置可正常读取');
    adp_no_php_errors($caseP['errlog'], 'P 修复保存没有 PHP 警告');

    // ---------------- 源码级负向对照：旧实现必须无法通过这些断言 ----------------
    $pageSrc = (string)file_get_contents($root . '/admin/academic_directory.php');
    adp_check(strpos($pageSrc, '$syncResult') !== false, 'R2 页面使用 $syncResult 作为结果变量');
    adp_check(preg_match('/\$result\s*=\s*academic_directory_sync_execute/', $pageSrc) === 0, 'R2 页面不再用 $result 接收执行结果（旧代码会失败）');
    adp_check(strpos($pageSrc, "pdo_query('SELECT 1')") !== false, 'R1 页面在判断 PDO 前触发惰性连接（旧代码会失败）');
    adp_check(strpos($pageSrc, '仍可直接确认同步') === false, 'R3 预览失败不再提供直接确认兜底（旧代码会失败）');
    adp_check(strpos($pageSrc, 'empty($state[\'preview_ok\'])') !== false, 'R3 确认处理校验服务端 preview_ok 标记');
    adp_check(strpos($pageSrc, 'role="alert"') !== false && strpos($pageSrc, 'role="status"') !== false, 'R4 结果/错误具备 role=status/alert');
    adp_check(preg_match('/elseif \(is_array\(\$planView\) && empty\(\$planView\[\'has_changes\'\]\)\)/', $pageSrc) === 1, 'R5 预览无变化文案仅在 has_changes 为假时显示（旧代码会失败）');
    adp_check(strpos($pageSrc, 'academic_directory_sync_scope_snapshot') !== false, '页面预览与确认统一应用到 2024 级及以后范围');
} catch (Throwable $e) {
    $failed = true;
    fwrite(STDERR, "FAIL: 未捕获异常：" . $e->getMessage() . "\n");
} finally {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    @rmdir($root);
}

echo "\n";
echo "共 {$GLOBALS['ADP_CHECKS']} 项检查，失败 {$GLOBALS['ADP_FAIL']} 项\n";
exit(($GLOBALS['ADP_FAIL'] === 0 && !$failed) ? 0 : 1);
