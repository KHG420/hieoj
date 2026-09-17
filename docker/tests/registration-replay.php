<?php
// 注册重放幂等回归测试（无网络、无真实数据库 / 真实账号，仅 mock pdo_query 边界）。
//
// 运行方式（仓库根目录，使用本机已安装的 PHP 8.1 镜像）：
//   docker run --rm --network none -v "$PWD:/workspace:ro" --entrypoint php \
//     hnieoj-unified-web:latest /workspace/docker/tests/registration-replay.php
//
// 测试直接执行真实的 web/register.php：每个请求都是一个独立的 `php -n` 子进程，
// session 使用隔离目录下的真实文件 session（跨请求复用同一 session_id），数据库
// 边界用 userland pdo_query stub 记录调用并维护最小 users / loginlog 状态；学院
// 班级目录查询同样落在该边界内。不触网、不连生产库、不创建真实账号。
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

function rr_check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rr_rmtree($root)
{
    if (!is_dir($root)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}

function rr_run_php(array $command, $cwd, array $env, &$exit)
{
    $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $process = proc_open($command, $descriptors, $pipes, $cwd, $env);
    rr_check(is_resource($process), 'start PHP subprocess');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    return array($stdout, $stderr);
}

function rr_session_script($root, $script, array $argv)
{
    $exit = 0;
    list($stdout, $stderr) = rr_run_php(
        array_merge(array(
            PHP_BINARY, '-n',
            '-d', 'session.save_path=' . $root . '/sessions',
            '-d', 'session.gc_probability=0',
        ), array($root . '/' . $script), $argv),
        $root,
        getenv(),
        $exit
    );
    return array($exit, $stdout, $stderr);
}

function rr_session_dump($root, $id)
{
    list($exit, $stdout) = rr_session_script($root, 'session_dump.php', array($id));
    rr_check($exit === 0, 'dump session ' . $id);
    $data = json_decode(trim($stdout), true);
    return is_array($data) ? $data : array();
}

function rr_session_seed($root, $id, array $data)
{
    list($exit) = rr_session_script($root, 'session_seed.php', array($id, json_encode($data)));
    rr_check($exit === 0, 'seed session ' . $id);
    rr_check(rr_session_dump($root, $id) === $data, 'seeded session readable ' . $id);
}

function rr_session_patch($root, $id, array $patch)
{
    list($exit) = rr_session_script($root, 'session_patch.php', array($id, json_encode($patch)));
    rr_check($exit === 0, 'patch session ' . $id);
}

/**
 * 用真实端点跑一次注册请求。
 *
 * $config 可覆盖本次请求的边界配置：vcode / confirm / template / register /
 * login_mod。返回 stdout / stderr / 本次运行的 SQL 调用流水 / 持久 DB 状态 /
 * 请求结束后的 session 内容。
 */
function rr_run($root, $sessionId, array $post, array $config = array())
{
    static $sequence = 0;
    $sequence++;
    $env = getenv();
    $env['REG_DB_STATE'] = $root . '/state/db.json';
    $env['REG_DB_JOURNAL'] = $root . '/state/db-' . $sequence . '.log';
    $env['REG_OJ_NAME'] = 'regtest';
    $env['REG_OJ_DATA'] = $root . '/data';
    $env['REG_OJ_VCODE'] = (!empty($config['vcode'])) ? '1' : '0';
    $env['REG_OJ_LOGIN_MOD'] = isset($config['login_mod']) ? $config['login_mod'] : 'hustoj';
    $env['REG_OJ_REGISTER'] = (isset($config['register']) && !$config['register']) ? '0' : '1';
    $env['REG_OJ_REG_NEED_CONFIRM'] = (!empty($config['confirm'])) ? '1' : '0';
    $env['REG_OJ_TEMPLATE'] = isset($config['template']) ? $config['template'] : 'test';
    $env['REG_POST'] = json_encode($post);
    $env['REG_SESSION_ID'] = $sessionId;
    $env['REG_ERRLOG'] = $root . '/state/err-' . $sequence . '.log';
    $env['REG_REMOTE_ADDR'] = '203.0.113.7';

    $exit = 0;
    list($stdout, $stderr) = rr_run_php(
        array(
            PHP_BINARY, '-n',
            '-d', 'session.save_path=' . $root . '/sessions',
            '-d', 'session.gc_probability=0',
            '-d', 'error_reporting=E_ALL',
            '-d', 'auto_prepend_file=' . $root . '/prepend.php',
            $root . '/register.php',
        ),
        $root,
        $env,
        $exit
    );

    $dbCalls = array();
    if (is_file($env['REG_DB_JOURNAL'])) {
        foreach (file($env['REG_DB_JOURNAL'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $dbCalls[] = $decoded;
            }
        }
    }
    $db = array('users' => array(), 'loginlog' => array());
    if (is_file($env['REG_DB_STATE'])) {
        $decoded = json_decode((string)file_get_contents($env['REG_DB_STATE']), true);
        if (is_array($decoded)) {
            $db = $decoded;
        }
    }

    return array(
        'exit' => $exit,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'log' => is_file($env['REG_ERRLOG']) ? (string)file_get_contents($env['REG_ERRLOG']) : '',
        'db_calls' => $dbCalls,
        'db' => $db,
        'session' => rr_session_dump($root, $sessionId),
    );
}

function rr_seed_verification($root, $sessionId, $email, $code, array $extra = array())
{
    rr_session_seed($root, $sessionId, array_merge(array(
        'email' => $email,
        'time' => time() + 180,
        'code' => $code,
    ), $extra));
}

/** 某个用户累计写入的 loginlog 行数。 */
function rr_loginlog($result, $userId)
{
    return isset($result['db']['loginlog'][$userId]) ? (int)$result['db']['loginlog'][$userId] : 0;
}

function rr_setup($root)
{
    $repo = dirname(__DIR__, 2);
    mkdir($root . '/include', 0700, true);
    mkdir($root . '/sessions', 0700, true);
    mkdir($root . '/data', 0700, true);
    mkdir($root . '/state', 0700, true);
    copy($repo . '/web/register.php', $root . '/register.php');
    copy($repo . '/web/include/my_func.inc.php', $root . '/include/my_func.inc.php');
    copy($repo . '/web/include/academic_directory.php', $root . '/include/academic_directory.php');
    copy($repo . '/web/include/academic_registration.php', $root . '/include/academic_registration.php');

    // 数据库 / 学院目录边界：真实 register.php 调用 userland pdo_query，只维护
    // users / loginlog 两个最小状态并记录调用流水；绝不连接真实数据库。
    file_put_contents($root . '/include/db_info.inc.php', <<<'FIXTURE'
<?php @session_start();
$OJ_NAME = getenv('REG_OJ_NAME') ?: 'regtest';
$OJ_VCODE = getenv('REG_OJ_VCODE') === '1';
$OJ_LOGIN_MOD = getenv('REG_OJ_LOGIN_MOD') ?: 'hustoj';
$OJ_REGISTER = getenv('REG_OJ_REGISTER') !== '0';
$OJ_REG_NEED_CONFIRM = getenv('REG_OJ_REG_NEED_CONFIRM') === '1';
$OJ_TEMPLATE = getenv('REG_OJ_TEMPLATE') ?: 'test';
$OJ_DATA = getenv('REG_OJ_DATA');

function rr_db_query($sql, array $args)
{
    $statePath = getenv('REG_DB_STATE');
    $journal = getenv('REG_DB_JOURNAL');
    if (is_string($journal) && $journal !== '') {
        file_put_contents($journal, json_encode(array($sql, $args)) . "\n", FILE_APPEND);
    }
    $state = array('users' => array(), 'loginlog' => array());
    if (is_file($statePath)) {
        $decoded = json_decode((string)file_get_contents($statePath), true);
        if (is_array($decoded)) {
            $state = array_merge($state, $decoded);
        }
    }
    $save = function () use (&$state, $statePath) {
        file_put_contents($statePath, json_encode($state));
    };

    if (strpos($sql, 'SELECT COUNT(*) as num FROM `users` WHERE email=?') !== false) {
        $email = $args[0];
        $count = 0;
        foreach ($state['users'] as $user) {
            if ($user['email'] === $email) {
                $count++;
            }
        }
        return array(array('num' => $count, 0 => $count));
    }
    if (strpos($sql, 'SELECT `user_id` FROM `users` WHERE `users`.`user_id` = ?') !== false) {
        $uid = $args[0];
        return isset($state['users'][$uid]) ? array(array('user_id' => $uid, 0 => $uid)) : array();
    }
    if (strpos($sql, 'INSERT INTO `users`') === 0) {
        $uid = $args[0];
        $state['users'][$uid] = array(
            'user_id' => $args[0], 'email' => $args[1], 'ip' => $args[2],
            'password' => $args[3], 'nick' => $args[4], 'school' => $args[5],
            'defunct' => $args[6], 'xueYuan' => $args[7], 'qq' => $args[8],
            'phone' => $args[9], 'register_num' => $args[10],
        );
        $save();
        return 1;
    }
    if (strpos($sql, 'INSERT INTO `loginlog`') === 0) {
        $uid = $args[0];
        $state['loginlog'][$uid] = isset($state['loginlog'][$uid]) ? (int)$state['loginlog'][$uid] + 1 : 1;
        $save();
        return 1;
    }
    if (strpos($sql, 'SELECT `rightstr` FROM `privilege`') !== false) {
        return array();
    }
    if (strpos($sql, 'FROM `collegiate`') !== false) {
        return array();
    }
    if (strpos($sql, 'FROM `schoolList`') !== false) {
        return array();
    }
    throw new RuntimeException('Unexpected SQL in registration-replay test: ' . $sql);
}

function pdo_query($sql)
{
    return rr_db_query($sql, array_slice(func_get_args(), 1));
}
FIXTURE
    );

    file_put_contents($root . '/prepend.php', <<<'FIXTURE'
<?php
// `php -n` 边界 stub：真实端点代码；session 使用真实文件实现。
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$__rrErrlog = getenv('REG_ERRLOG');
if (is_string($__rrErrlog) && $__rrErrlog !== '') {
    ini_set('error_log', $__rrErrlog);
}
error_reporting(E_ALL);

$__rrSession = getenv('REG_SESSION_ID');
if (is_string($__rrSession) && $__rrSession !== '') {
    session_id($__rrSession);
}
$__rrPost = getenv('REG_POST');
$_POST = (is_string($__rrPost) && $__rrPost !== '') ? json_decode($__rrPost, true) : array();
if (!is_array($_POST)) {
    $_POST = array();
}
$__rrRemote = getenv('REG_REMOTE_ADDR');
$_SERVER['REMOTE_ADDR'] = (is_string($__rrRemote) && $__rrRemote !== '') ? $__rrRemote : '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';
unset($_SERVER['HTTP_X_FORWARDED_FOR']);
FIXTURE
    );

    file_put_contents($root . '/session_dump.php', <<<'FIXTURE'
<?php
session_id($argv[1]);
session_start();
echo json_encode($_SESSION);
session_write_close();
FIXTURE
    );
    file_put_contents($root . '/session_seed.php', <<<'FIXTURE'
<?php
session_id($argv[1]);
session_start();
$_SESSION = json_decode($argv[2], true);
session_write_close();
FIXTURE
    );
    file_put_contents($root . '/session_patch.php', <<<'FIXTURE'
<?php
session_id($argv[1]);
session_start();
foreach (json_decode($argv[2], true) as $key => $value) {
    if ($value === null) {
        unset($_SESSION[$key]);
    } else {
        $_SESSION[$key] = $value;
    }
}
session_write_close();
FIXTURE
    );
}

$repo = dirname(__DIR__, 2);
$root = sys_get_temp_dir() . '/oj-registration-replay-' . bin2hex(random_bytes(6));
$autoScript = "<script>\r\n    alert('注册成功，已自动登录。');\r\n        window.location.href='index.php';\r\n    </script>\r\n";
$pendingScript = "<script>\r\n    alert('提交成功，等待管理员审核通过邮箱通知。');\r\n        history.go(-2);\r\n    </script>\r\n";
$receiptKey = 'regtest_register_success_receipt';
$userKey = 'regtest_user_id';

try {
    mkdir($root, 0700, true);
    rr_setup($root);

    // ------------------------------------------------------------------
    // AC1：首次合法注册 = 一个用户 + 一条 loginlog + 回执 + 免审成功响应
    // ------------------------------------------------------------------
    $aliceSid = 'alice-session';
    rr_seed_verification($root, $aliceSid, 'alice@example.test', '123456');
    $alicePost = array(
        'user_id' => 'alice2026',
        'email' => 'alice@example.test',
        'password' => 'secret-pass-1',
        'rptpassword' => 'secret-pass-1',
        'nick' => 'Alice',
        'school' => 'Class 1',
        'xueYuan' => 'CS',
        'phone' => '13800000000',
        'qq' => '123456',
        'code' => '123456',
    );
    $first = rr_run($root, $aliceSid, $alicePost);
    rr_check($first['exit'] === 0, 'AC1 first request exits 0');
    rr_check($first['stdout'] === $autoScript, 'AC1 first response is the existing auto-login script');
    rr_check(count($first['db_calls']) === 5, 'AC1 first request issues 5 SQL calls; got ' . count($first['db_calls']));
    rr_check(count($first['db']['users']) === 1 && isset($first['db']['users']['alice2026']), 'AC1 exactly one user created');
    rr_check(rr_loginlog($first, 'alice2026') === 1, 'AC1 exactly one loginlog row');
    rr_check(($first['session'][$userKey] ?? null) === 'alice2026', 'AC1 auto-login session user set');
    $receipt = isset($first['session'][$receiptKey]) ? $first['session'][$receiptKey] : null;
    rr_check(is_array($receipt), 'AC1 success receipt stored in session');
    rr_check(preg_match('/^[0-9a-f]{64}$/', (string)$receipt['hash']) === 1, 'AC1 receipt stores a sha256 payload hash');
    rr_check($receipt['user_id'] === 'alice2026', 'AC1 receipt records the successful user');
    rr_check((int)$receipt['confirm'] === 0, 'AC1 receipt records auto-login (no-confirm) mode');
    $receiptKeys = array_keys($receipt);
    sort($receiptKeys);
    rr_check($receiptKeys === array('confirm', 'hash', 'user_id'), 'AC1 receipt holds only hash/user_id/confirm');
    $receiptJson = json_encode($receipt);
    rr_check(strpos($receiptJson, 'secret-pass-1') === false, 'AC1 receipt stores no plaintext password');
    rr_check(strpos($receiptJson, '123456') === false, 'AC1 receipt stores no plaintext verification code');
    rr_check(stripos($first['stderr'] . $first['log'], 'Undefined array key') === false, 'AC1 missing vcode emits no undefined-key warning');
    rr_check(stripos($first['stderr'] . $first['log'], 'Deprecated') === false, 'AC1 missing vcode emits no deprecation');
    printf("PASS AC1 first: 1 user, 1 loginlog, receipt hash-only, auto-login script\n");

    // EXACT retry（同样字段、乱序 key）：同一会话返回相同成功响应、零额外 DB 调用
    $reordered = array();
    foreach (array_reverse(array_keys($alicePost)) as $key) {
        $reordered[$key] = $alicePost[$key];
    }
    $replay = rr_run($root, $aliceSid, $reordered);
    rr_check($replay['stdout'] === $first['stdout'], 'AC1 exact retry returns identical success script');
    rr_check(count($replay['db_calls']) === 0, 'AC1 exact retry performs zero DB calls; got ' . count($replay['db_calls']));
    rr_check(count($replay['db']['users']) === 1 && rr_loginlog($replay, 'alice2026') === 1, 'AC1 exact retry writes nothing');
    rr_check(strpos($replay['stdout'], 'User Existed') === false, 'AC1 exact retry never reports UserExisted');
    printf("PASS AC1 replay: identical script, 0 SQL, no duplicate writes, key order independent\n");

    // 任意字段变化 => 不重放，仍走既有重复用户拒绝
    $changed = $alicePost;
    $changed['nick'] = 'Alice Changed';
    $changedRun = rr_run($root, $aliceSid, $changed);
    rr_check(strpos($changedRun['stdout'], 'User Existed') !== false, 'AC1 changed payload is not replayed (User Existed)');
    rr_check(count($changedRun['db_calls']) === 2, 'AC1 changed payload only reads existence; got ' . count($changedRun['db_calls']));
    rr_check(count($changedRun['db']['users']) === 1 && rr_loginlog($changedRun, 'alice2026') === 1, 'AC1 changed payload writes nothing');
    printf("PASS AC1 changed payload: existing duplicate rejection preserved\n");

    // 新会话 + 相同请求 => 不回执，命中既有重复用户拒绝
    $otherSid = 'other-session';
    rr_seed_verification($root, $otherSid, 'alice@example.test', '123456');
    $otherSession = rr_run($root, $otherSid, $alicePost);
    rr_check(strpos($otherSession['stdout'], 'User Existed') !== false, 'AC1 new session with same payload is not replayed');
    rr_check(!isset($otherSession['session'][$receiptKey]), 'AC1 new session never receives a receipt');
    rr_check(count($otherSession['db_calls']) === 2, 'AC1 new session only reads existence; got ' . count($otherSession['db_calls']));
    printf("PASS AC1 new session: no receipt, User Existed\n");

    // 重放必须绕过验证码失效 / 邮箱不一致 / 学院班级策略变化
    rr_session_patch($root, $aliceSid, array(
        'time' => time() - 10,
        'code' => '000000',
        'email' => 'someone-else@example.test',
    ));
    $expired = rr_run($root, $aliceSid, $alicePost);
    rr_check($expired['stdout'] === $autoScript, 'AC1 replay bypasses expired code / email mismatch');
    rr_check(count($expired['db_calls']) === 0, 'AC1 expired-code replay performs zero DB calls');
    $academic = rr_run($root, $aliceSid, $alicePost, array('template' => 'syzoj'));
    rr_check($academic['stdout'] === $autoScript, 'AC1 replay bypasses academic policy/config change');
    rr_check(count($academic['db_calls']) === 0, 'AC1 academic replay performs zero DB calls');
    printf("PASS AC1 replay bypass: expired code, email mismatch, academic config; 0 SQL\n");

    // OJ_REGISTER 关闭时连重放也必须停止（保留既有策略）
    $disabled = rr_run($root, $aliceSid, $alicePost, array('register' => false));
    rr_check(trim($disabled['stdout']) === '', 'AC1 disabled OJ_REGISTER still exits silently');
    rr_check(count($disabled['db_calls']) === 0, 'AC1 disabled OJ_REGISTER performs zero DB calls');
    printf("PASS AC1 OJ_REGISTER policy preserved on replay\n");

    // ------------------------------------------------------------------
    // AC2：校验失败不落回执、不写库
    // ------------------------------------------------------------------
    $daveSid = 'dave-session';
    rr_seed_verification($root, $daveSid, 'dave@example.test', '654321');
    $invalidPost = array(
        'user_id' => 'dave2026',
        'email' => 'dave@example.test',
        'password' => 'secret-pass-1',
        'rptpassword' => 'different-pass',
        'nick' => 'Dave',
        'school' => 'Class 2',
        'xueYuan' => 'CS',
        'phone' => '13800000001',
        'qq' => '111111',
        'code' => '654321',
    );
    $invalid = rr_run($root, $daveSid, $invalidPost);
    rr_check(strpos($invalid['stdout'], '两次密码不相同') !== false, 'AC2 validation failure keeps existing message');
    rr_check(isset($invalid['db']['users']['dave2026']) === false, 'AC2 validation failure creates no user');
    rr_check(rr_loginlog($invalid, 'dave2026') === 0, 'AC2 validation failure creates no loginlog');
    rr_check(!isset($invalid['session'][$receiptKey]), 'AC2 validation failure stores no receipt');
    rr_check(count($invalid['db_calls']) === 1, 'AC2 validation failure only reads the email count');
    printf("PASS AC2 validation failure: no receipt, no user, no loginlog\n");

    // ------------------------------------------------------------------
    // AC2：需审核模式首次 / 重放提示一致，均不自动登录、无额外写入（含 syzoj 回放）
    // ------------------------------------------------------------------
    $carolSid = 'carol-session';
    rr_seed_verification($root, $carolSid, 'carol@example.test', '222333');
    $carolPost = array(
        'user_id' => 'carol2026',
        'email' => 'carol@example.test',
        'password' => 'secret-pass-2',
        'rptpassword' => 'secret-pass-2',
        'nick' => 'Carol',
        'school' => 'Class 3',
        'xueYuan' => 'CS',
        'phone' => '13800000002',
        'qq' => '222222',
        'code' => '222333',
    );
    $pendingFirst = rr_run($root, $carolSid, $carolPost, array('confirm' => true));
    rr_check($pendingFirst['stdout'] === $pendingScript, 'AC2 pending first returns the pending script');
    rr_check(count($pendingFirst['db_calls']) === 4, 'AC2 pending first issues 4 SQL calls; got ' . count($pendingFirst['db_calls']));
    rr_check(rr_loginlog($pendingFirst, 'carol2026') === 1, 'AC2 pending first writes one loginlog');
    rr_check(!isset($pendingFirst['session'][$userKey]), 'AC2 pending first performs no auto-login');
    $pendingReceipt = isset($pendingFirst['session'][$receiptKey]) ? $pendingFirst['session'][$receiptKey] : null;
    rr_check(is_array($pendingReceipt) && (int)$pendingReceipt['confirm'] === 1, 'AC2 pending receipt records confirm mode');
    $pendingReplay = rr_run($root, $carolSid, $carolPost, array('confirm' => true, 'template' => 'syzoj'));
    rr_check($pendingReplay['stdout'] === $pendingScript, 'AC2 pending replay returns identical pending script');
    rr_check(count($pendingReplay['db_calls']) === 0, 'AC2 pending replay performs zero DB calls');
    rr_check(count($pendingReplay['db']['users']) === 2 && rr_loginlog($pendingReplay, 'carol2026') === 1, 'AC2 pending replay writes nothing');
    rr_check(!isset($pendingReplay['session'][$userKey]), 'AC2 pending replay never auto-logs-in');
    printf("PASS AC2 pending: identical message, no auto-login, no extra writes\n");

    // ------------------------------------------------------------------
    // AC2：登出后同会话重放不得登录 / 不得误判成功
    // ------------------------------------------------------------------
    rr_session_patch($root, $aliceSid, array(
        $userKey => null,
        'email' => 'alice@example.test',
        'time' => time() + 180,
        'code' => '123456',
    ));
    $afterLogout = rr_run($root, $aliceSid, $alicePost);
    rr_check(strpos($afterLogout['stdout'], 'User Existed') !== false, 'AC2 replay after logout falls through to duplicate rejection');
    rr_check(strpos($afterLogout['stdout'], '注册成功') === false, 'AC2 replay after logout does not report success');
    rr_check(!isset($afterLogout['session'][$userKey]), 'AC2 replay after logout does not re-login');
    rr_check(count($afterLogout['db_calls']) === 2, 'AC2 replay after logout only reads existence; got ' . count($afterLogout['db_calls']));
    printf("PASS AC2 logout: receipt alone never grants login\n");

    // ------------------------------------------------------------------
    // AC2：会话已切换为其他登录用户时，不得被回执改写成原注册用户
    // ------------------------------------------------------------------
    rr_session_patch($root, $aliceSid, array(
        $userKey => 'someoneelse',
        'email' => 'alice@example.test',
        'time' => time() + 180,
        'code' => '123456',
    ));
    $afterSwitch = rr_run($root, $aliceSid, $alicePost);
    rr_check(strpos($afterSwitch['stdout'], 'User Existed') !== false, 'AC2 replay under another login falls through to duplicate rejection');
    rr_check(($afterSwitch['session'][$userKey] ?? null) === 'someoneelse', 'AC2 replay under another login never switches the session user');
    rr_check(count($afterSwitch['db_calls']) === 2, 'AC2 replay under another login only reads existence');
    printf("PASS AC2 account switch: session user untouched\n");

    // ------------------------------------------------------------------
    // AC2：vcode 校验行为保持，且缺失 vcode 不再产生 PHP 警告
    // ------------------------------------------------------------------
    $erinSid = 'erin-session';
    rr_seed_verification($root, $erinSid, 'erin@example.test', '777888');
    $noVcodePost = array(
        'user_id' => 'erin2026',
        'email' => 'erin@example.test',
        'password' => 'secret-pass-3',
        'rptpassword' => 'secret-pass-3',
        'nick' => 'Erin',
        'school' => 'Class 4',
        'xueYuan' => 'CS',
        'phone' => '13800000003',
        'qq' => '333333',
        'code' => '777888',
    );
    $vcodeWrong = rr_run($root, $erinSid, $noVcodePost, array('vcode' => true));
    rr_check(strpos($vcodeWrong['stdout'], 'Verification Code Wrong!') !== false, 'AC2 vcode required path still rejects missing code');
    rr_check(isset($vcodeWrong['db']['users']['erin2026']) === false, 'AC2 vcode failure creates no user');
    rr_check(!isset($vcodeWrong['session'][$receiptKey]), 'AC2 vcode failure stores no receipt');
    rr_check($vcodeWrong['stderr'] === '' && trim($vcodeWrong['log']) === '', 'AC2 vcode-required missing field emits no warning');
    printf("PASS AC2 vcode: required path rejects, missing field warning-free\n");

    // ------------------------------------------------------------------
    // AC1：被忽略字段 submit 的“字符串 vs 数组”是不同 POST 形状，指纹必须不同，
    // 不能被折叠成同一个值（否则不同形状会被误重放）；同时保持既有重复用户拒绝。
    // ------------------------------------------------------------------
    $frankSid = 'frank-session';
    $frankUsersBefore = count($pendingFirst['db']['users']);
    rr_seed_verification($root, $frankSid, 'frank@example.test', '321321');
    $frankPost = array(
        'user_id' => 'frank2026',
        'email' => 'frank@example.test',
        'password' => 'secret-pass-4',
        'rptpassword' => 'secret-pass-4',
        'nick' => 'Frank',
        'school' => 'Class 5',
        'xueYuan' => 'CS',
        'phone' => '13800000004',
        'qq' => '444444',
        'code' => '321321',
        'submit' => '["x"]',
    );
    $frankFirst = rr_run($root, $frankSid, $frankPost);
    rr_check($frankFirst['stdout'] === $autoScript, 'AC1 string submit first request returns the success script');
    rr_check(isset($frankFirst['db']['users']['frank2026']), 'AC1 string submit creates the user');
    rr_check(count($frankFirst['db']['users']) === $frankUsersBefore + 1, 'AC1 string submit creates exactly one new user');
    rr_check(rr_loginlog($frankFirst, 'frank2026') === 1, 'AC1 string submit writes exactly one loginlog');
    // 完全相同的字符串 submit => 同会话重放成功、零 DB 调用
    $frankRetry = rr_run($root, $frankSid, $frankPost);
    rr_check($frankRetry['stdout'] === $autoScript, 'AC1 identical string submit retry replays the success script');
    rr_check(count($frankRetry['db_calls']) === 0, 'AC1 identical string submit retry performs zero DB calls; got ' . count($frankRetry['db_calls']));
    // submit 换成数组 => 不同 POST 形状，指纹必须不同：不得重放，走既有重复用户拒绝且零写入
    $frankArrayPost = $frankPost;
    $frankArrayPost['submit'] = array('x');
    $frankArray = rr_run($root, $frankSid, $frankArrayPost);
    rr_check(strpos($frankArray['stdout'], 'User Existed') !== false, 'AC1 array submit is not replayed (User Existed)');
    rr_check(strpos($frankArray['stdout'], '注册成功') === false, 'AC1 array submit never reports success');
    rr_check(count($frankArray['db_calls']) === 2, 'AC1 array submit only reads existence; got ' . count($frankArray['db_calls']));
    rr_check(count($frankArray['db']['users']) === $frankUsersBefore + 1 && rr_loginlog($frankArray, 'frank2026') === 1, 'AC1 array submit writes nothing');
    printf("PASS AC1 submit shape: string vs array fingerprint differs, duplicate rejection preserved\n");

    printf("ALL PASS registration-replay\n");
} finally {
    rr_rmtree($root);
}
