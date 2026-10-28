<?php
// CLI-only, no network or production data. Real profile/review controllers and
// templates run with private file sessions and SQLite using the existing PDO helper.
// docker run --rm --network none -v "$PWD:/workspace:ro" --entrypoint php \
//   hnieoj-unified-web /workspace/web/tests/profile_change_test.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function pc_check($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
}
function pc_remove($root) {
    if (!is_dir($root)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
}
function pc_run($root, $endpoint, $post = array(), $get = array(), $role = 'student', $method = 'POST') {
    $process = proc_open(array(PHP_BINARY, $root.'/runner.php', $endpoint, json_encode($post), json_encode($get), $role, $method),
        array(0=>array('pipe','r'), 1=>array('pipe','w'), 2=>array('pipe','w')), $pipes, $root);
    pc_check(is_resource($process), 'start fixture request');
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
    pc_check(!preg_match('/Warning:|Fatal error:|Parse error:|Uncaught /', $body.$errors), 'no PHP runtime error: '.$errors);
    $result = json_decode(file_get_contents($root.'/response.json'), true);
    return array_merge($result, array('body'=>$body, 'exit'=>$exit));
}

$repo = dirname(__DIR__, 2);
$root = sys_get_temp_dir().'/oj-profile-change-'.bin2hex(random_bytes(6));
$db = null;
try {
    foreach (array('include','template/syzoj','admin','sessions') as $directory) mkdir($root.'/'.$directory, 0700, true);
    foreach (array('modify2.php','template/syzoj/modifypage2.php','template/syzoj/error.php',
        'admin/user_modify.php','admin/user_modify_change.php','include/pdo.php','include/my_func.inc.php',
        'include/set_post_key.php','include/set_get_key.php','include/check_get_key.php') as $file) {
        copy($repo.'/web/'.$file, $root.'/'.$file);
    }
    foreach (array('include/cache_start.php','include/setlang.php','template/syzoj/header.php',
        'template/syzoj/footer.php','admin/navbar.php') as $file) file_put_contents($root.'/'.$file, '<?php');
    file_put_contents($root.'/admin/admin-header.php', "<?php require_once '../include/db_info.inc.php';");
    file_put_contents($root.'/include/db_info.inc.php', <<<'PHP'
<?php
$OJ_NAME = 'ProfileFixture'; $OJ_TEMPLATE = 'syzoj';
ini_set('session.save_path', dirname(__DIR__).'/sessions');
session_id('profile-fixture-'.$fixtureRole); session_start();
if ($fixtureRole !== 'anonymous') $_SESSION[$OJ_NAME.'_user_id'] = 'profile-fixture';
if ($fixtureRole === 'admin') $_SESSION[$OJ_NAME.'_administrator'] = true;
$dbh = new PDO('sqlite:'.dirname(__DIR__).'/fixture.sqlite');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbh->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
require_once __DIR__.'/pdo.php';
PHP
    );
    file_put_contents($root.'/runner.php', <<<'PHP'
<?php
$endpoint=$argv[1]; $_POST=json_decode($argv[2],true); $_GET=json_decode($argv[3],true);
$fixtureRole=$argv[4]; $_SERVER['REQUEST_METHOD']=$argv[5];
register_shutdown_function(function () {
    file_put_contents(__DIR__.'/response.json', json_encode(array('status'=>http_response_code() ?: 200, 'session'=>$_SESSION ?? array())));
});
chdir(__DIR__.'/'.dirname($endpoint)); require basename($endpoint);
PHP
    );
    file_put_contents($root.'/render.php', <<<'PHP'
<?php
require './include/db_info.inc.php';
$row=pdo_query('SELECT * FROM users WHERE user_id=?','profile-fixture')[0];
$xueYuan=array(array('测试学院','03')); $classes=array();
require './template/syzoj/modifypage2.php';
PHP
    );
    $db = new PDO('sqlite:'.$root.'/fixture.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE users(user_id TEXT PRIMARY KEY,nick TEXT,school TEXT,xueYuan TEXT,email TEXT,phone TEXT,qq TEXT)');
    $db->exec('CREATE TABLE `modify`(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id TEXT,content TEXT,thing TEXT,reason TEXT,time TEXT,status INTEGER DEFAULT 0)');
    $db->prepare('INSERT INTO users VALUES(?,?,?,?,?,?,?)')->execute(array('profile-fixture','隔离测试','测试旧班级','测试学院','fixture@example.invalid','',''));
    $count = fn() => (int)$db->query('SELECT COUNT(*) FROM `modify`')->fetchColumn();
    $school = fn() => $db->query('SELECT school FROM users')->fetchColumn();

    $page = pc_run($root, 'render.php', array(), array(), 'student', 'GET');
    preg_match_all('/<form\b[^>]*>(.*?)<\/form>/s', $page['body'], $forms);
    pc_check(count($forms[1]) === 5, 'all five profile forms rendered');
    foreach ($forms[1] as $form) {
        pc_check(preg_match('/name="postkey" value="([^"]+)"/', $form, $key) === 1, 'each form carries its own CSRF field');
        pc_check($key[1] === $page['session']['ProfileFixture_postkey'], 'all forms use the same stable session token');
    }
    $post = array('type'=>'class','xueYuan'=>'测试学院','school'=>'测试新班级','reason'=>'班级填写有误','postkey'=>$page['session']['ProfileFixture_postkey']);
    $missing = $post; unset($missing['postkey']);
    $bad = pc_run($root, 'modify2.php', $missing);
    pc_check($bad['status'] === 403 && strpos($bad['body'], '页面凭证已失效') !== false && $count() === 0, 'missing token gives a visible error and writes nothing');
    $wrong = $post; $wrong['postkey'] = 'expired-token';
    $bad = pc_run($root, 'modify2.php', $wrong);
    pc_check($bad['status'] === 403 && $count() === 0, 'incorrect token cannot create an application');
    $bad = pc_run($root, 'modify2.php', $post, array(), 'anonymous');
    pc_check($bad['status'] === 401 && strpos($bad['body'], '登录已过期') !== false && $count() === 0, 'expired login has a visible error');
    $bad = pc_run($root, 'modify2.php', array(), array(), 'student', 'GET');
    pc_check($bad['status'] === 405 && $count() === 0, 'GET cannot submit an application');
    $noChange = $post; $noChange['school'] = '测试旧班级';
    $bad = pc_run($root, 'modify2.php', $noChange);
    pc_check($bad['status'] === 400 && strpos($bad['body'], '没有需要修改') !== false && $count() === 0, 'unchanged fields are not reported as a successful submission');
    printf("PASS profile forms and errors: five CSRF fields, session expiry, invalid token, no-op\n");

    $saved = pc_run($root, 'modify2.php', $post);
    pc_check($saved['exit'] === 0 && strpos($saved['body'], '提交成功，等待审核通过') !== false, 'student sees success after saving');
    pc_check($count() === 1 && $school() === '测试旧班级', 'application is stored without changing the class before review');
    $application = $db->query('SELECT * FROM `modify`')->fetch(PDO::FETCH_ASSOC);
    pc_check($application['content'] === '测试新班级' && $application['thing'] === 'school' && (int)$application['status'] === 0, 'pending class request contains the selected change');
    $list = pc_run($root, 'admin/user_modify.php', array(), array(), 'admin', 'GET');
    pc_check(strpos($list['body'], '测试新班级') !== false && strpos($list['body'], 'user_modify_change.php?cid='.$application['id']) !== false, 'administrator sees the stored request in the real review page');
    $review = pc_run($root, 'admin/user_modify_change.php', array(), array('cid'=>$application['id'],'getkey'=>$list['session']['ProfileFixture_getkey']), 'admin', 'GET');
    pc_check($review['exit'] === 0 && $school() === '测试新班级', 'real administrator review applies the new class');
    pc_check((int)$db->query('SELECT status FROM `modify`')->fetchColumn() === 1, 'reviewed request remains recorded as approved');
    printf("PASS profile flow: student submit -> pending row -> administrator list -> approval -> class updated\n");

    $db->exec("CREATE TRIGGER reject_school BEFORE INSERT ON `modify` WHEN NEW.thing='school' BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
    $both = $post; $both['xueYuan'] = '另一测试学院'; $both['school'] = '另一测试班级';
    $failed = pc_run($root, 'modify2.php', $both);
    pc_check($failed['status'] === 503 && strpos($failed['body'], '申请保存失败') !== false, 'database failure is visible and is not reported as success');
    pc_check($count() === 1 && $school() === '测试新班级', 'second insert failure rolls back the whole combined college/class request');
    printf("PASS database failure: explicit error, atomic application writes\nALL PASS profile-change\n");
} finally {
    $db = null;
    pc_remove($root);
}
