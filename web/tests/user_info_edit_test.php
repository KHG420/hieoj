<?php
// CLI-only controller regression using private sessions and SQLite.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/include/my_func.inc.php';

$checks = 0;
function user_edit_check($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function user_edit_remove_fixture($root) {
    if (!is_dir($root)) return;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
function user_edit_request($root, $endpoint, $post = array(), $get = array(), $role = 'administrator', $method = 'POST') {
    $process = proc_open(array(PHP_BINARY, $root.'/runner.php', $endpoint, json_encode($post), json_encode($get), $role, $method),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('Cannot start controller fixture');
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if (preg_match('/Warning:|Fatal error:|Parse error:|Uncaught /', $body.$errors)) throw new RuntimeException('Controller runtime error: '.$errors);
    return array_merge(json_decode(file_get_contents($root.'/response.json'), true), array('body' => $body, 'exit' => $exit));
}

$root = sys_get_temp_dir().'/oj-user-info-edit-'.bin2hex(random_bytes(6));
$db = null;
try {
    foreach (array('admin', 'include', 'sessions') as $directory) mkdir($root.'/'.$directory, 0700, true);
    foreach (array('admin/user_info_edit.php', 'admin/changepass.php', 'include/my_func.inc.php', 'include/pdo.php',
        'include/set_post_key.php', 'include/check_post_key.php') as $file) {
        copy(dirname(__DIR__).'/'.$file, $root.'/'.$file);
    }
    file_put_contents($root.'/admin/admin-header.php', '<?php');
    file_put_contents($root.'/admin/navbar.php', '<?php');
    file_put_contents($root.'/include/db_info.inc.php', <<<'PHP'
<?php
$OJ_NAME = 'UserEditFixture';
ini_set('session.save_path', dirname(__DIR__).'/sessions');
session_id('user-edit-'.str_replace('_', '-', $fixtureRole)); session_start();
$_SESSION = array($OJ_NAME.'_postkey' => 'fixture-token');
if ($fixtureRole !== 'anonymous') $_SESSION[$OJ_NAME.'_user_id'] = 'fixture-operator';
if ($fixtureRole === 'administrator') $_SESSION[$OJ_NAME.'_administrator'] = true;
if ($fixtureRole === 'password_setter') $_SESSION[$OJ_NAME.'_password_setter'] = true;
$dbh = new PDO('sqlite:'.dirname(__DIR__).'/fixture.sqlite');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once __DIR__.'/pdo.php';
PHP
    );
    file_put_contents($root.'/runner.php', <<<'PHP'
<?php
$endpoint = $argv[1]; $_POST = json_decode($argv[2], true); $_GET = json_decode($argv[3], true);
$fixtureRole = $argv[4]; $_SERVER['REQUEST_METHOD'] = $argv[5];
register_shutdown_function(function () {
    file_put_contents(__DIR__.'/response.json', json_encode(array('status' => http_response_code() ?: 200)));
});
chdir(__DIR__.'/'.dirname($endpoint)); require basename($endpoint);
PHP
    );
    $db = new PDO('sqlite:'.$root.'/fixture.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE users(user_id TEXT PRIMARY KEY,nick TEXT,school TEXT,xueYuan TEXT,email TEXT,reg_time TEXT,accesstime TEXT,defunct TEXT,register_num INTEGER,password TEXT)');
    $db->exec('CREATE TABLE privilege(user_id TEXT,rightstr TEXT)');
    $db->exec('CREATE TABLE coin_wallet(user_id TEXT,balance INTEGER)');
    $insert = $db->prepare("INSERT INTO users VALUES(?,?,?,?,?,'2026-11-01','2026-11-07','N',1,?)");
    foreach (array('student-fixture', 'admin-fixture') as $id) {
        $insert->execute(array($id, '原姓名', '原班级', '测试学院', 'fixture@example.invalid', md5('old-password')));
    }
    $db->exec("INSERT INTO privilege VALUES('admin-fixture','administrator')");
    $snapshot = fn() => $db->query('SELECT * FROM users ORDER BY user_id')->fetchAll(PDO::FETCH_ASSOC);
    $student = fn() => $db->query("SELECT * FROM users WHERE user_id='student-fixture'")->fetch(PDO::FETCH_ASSOC);
    $post = array('user_id' => 'student-fixture', 'nick' => '新姓名', 'school' => '新班级', 'xueYuan' => '测试学院',
        'email' => 'new@example.invalid', 'postkey' => 'fixture-token', 'passwd' => '', 'confirm_password' => '');

    $page = user_edit_request($root, 'admin/user_info_edit.php', array(), array('user_id' => 'student-fixture'), 'administrator', 'GET');
    foreach (array('passwd', 'confirm_password') as $field) {
        user_edit_check(preg_match('/<input\b[^>]*type="password"[^>]*name="'.$field.'"[^>]*autocomplete="new-password"/', $page['body']) === 1, 'Both password fields are masked and use new-password autocomplete');
    }
    user_edit_check(strpos($page['body'], '两项留空则保留原密码') !== false, 'Blank-password behavior is visible');
    user_edit_check(strpos($page['body'], '修改密码请用') === false, 'Merged form has no separate password link');

    $before = $snapshot();
    foreach (array(
        array('passwd' => 'new-password', 'confirm_password' => 'different-password'),
        array('passwd' => 'new-password', 'confirm_password' => ''),
        array('passwd' => '', 'confirm_password' => 'new-password'),
        array('passwd' => '12345', 'confirm_password' => '12345'),
        array('passwd' => array('new-password'), 'confirm_password' => 'new-password'),
        array('passwd' => 'new-password', 'confirm_password' => array('new-password')),
        array('passwd' => 'new-password', 'confirm_password' => 'new-password', 'email' => 'invalid-email')
    ) as $invalid) {
        $response = user_edit_request($root, 'admin/user_info_edit.php', array_replace($post, $invalid));
        user_edit_check($response['status'] !== 303 && strpos($response['body'], 'role="alert"') !== false, 'Invalid password or profile input displays an error');
        user_edit_check($snapshot() === $before, 'Validation failure saves neither profile nor password');
        user_edit_check(strpos($response['body'], 'value="new-password"') === false && strpos($response['body'], 'different-password') === false, 'Failed form never echoes passwords');
        user_edit_check(strpos($response['body'], 'value="新姓名"') !== false, 'Failed form retains profile input');
    }
    foreach (array('student', 'password_setter', 'anonymous') as $role) {
        $response = user_edit_request($root, 'admin/user_info_edit.php', $post, array(), $role);
        user_edit_check($response['exit'] !== 0 && $snapshot() === $before, 'Non-administrator cannot update user information');
    }
    foreach (array('', 'incorrect-token') as $token) {
        $response = user_edit_request($root, 'admin/user_info_edit.php', array_replace($post, array('postkey' => $token)));
        user_edit_check($response['exit'] !== 0 && $snapshot() === $before, 'Invalid CSRF token cannot save changes');
    }
    $missing = user_edit_request($root, 'admin/user_info_edit.php', array_replace($post, array('user_id' => 'missing-user')));
    user_edit_check(strpos($missing['body'], '用户不存在') !== false && $snapshot() === $before, 'Unknown account cannot report success');

    $saved = user_edit_request($root, 'admin/user_info_edit.php', $post);
    user_edit_check($saved['status'] === 303 && $student()['school'] === '新班级', 'Profile-only save redirects after updating');
    user_edit_check($student()['password'] === md5('old-password'), 'Blank password preserves legacy password bytes');
    $withPassword = array_replace($post, array('passwd' => ' new-password ', 'confirm_password' => ' new-password ', 'school' => '密码同存班级'));
    $saved = user_edit_request($root, 'admin/user_info_edit.php', $withPassword);
    user_edit_check($saved['status'] === 303 && $student()['school'] === '密码同存班级', 'Profile and password are saved together');
    user_edit_check(pwCheck(' new-password ', $student()['password']) && !pwCheck('old-password', $student()['password']), 'New password works through the production verifier and preserves spaces');
    user_edit_check($student()['password'] !== ' new-password ', 'Password is hashed before storage');

    $protectedPage = user_edit_request($root, 'admin/user_info_edit.php', array(), array('user_id' => 'admin-fixture'), 'administrator', 'GET');
    preg_match_all('/<input\b[^>]*type="password"[^>]* disabled/', $protectedPage['body'], $disabled);
    user_edit_check(count($disabled[0]) === 2, 'Administrator password fields show the existing protection');
    $before = $snapshot();
    $blocked = user_edit_request($root, 'admin/user_info_edit.php', array_replace($withPassword, array('user_id' => 'admin-fixture')));
    user_edit_check(strpos($blocked['body'], '管理员账号的密码') !== false && $snapshot() === $before, 'Direct POST cannot bypass administrator password protection');
    $saved = user_edit_request($root, 'admin/user_info_edit.php', array_replace($post, array('user_id' => 'admin-fixture')));
    user_edit_check($saved['status'] === 303 && $db->query("SELECT password FROM users WHERE user_id='admin-fixture'")->fetchColumn() === md5('old-password'), 'Administrator profile remains editable with blank passwords');

    foreach (array('GET', 'POST') as $method) {
        $before = $snapshot();
        $redirect = user_edit_request($root, 'admin/changepass.php', $withPassword, array(), 'administrator', $method);
        user_edit_check($redirect['status'] === 303 && $snapshot() === $before, 'Legacy administrator password route redirects without a partial update');
    }
    $db->exec("CREATE TRIGGER reject_user_update BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
    $before = $snapshot();
    $failed = user_edit_request($root, 'admin/user_info_edit.php', $withPassword);
    user_edit_check(strpos($failed['body'], '用户资料保存失败') !== false && $snapshot() === $before, 'Database rejection preserves both profile and password');
    printf("PASS: %d user information and password checks\n", $checks);
} finally {
    $db = null;
    user_edit_remove_fixture($root);
}
