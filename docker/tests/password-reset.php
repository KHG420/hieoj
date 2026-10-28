<?php
// Isolated recovery flow: real controllers/templates, file sessions, SQLite,
// production password hashing/queries, and the existing no-network SES stub.
// Run with the Ubuntu Web image (curl must be a shared extension for php -n).
require __DIR__.'/sendemail-registration.php';
require_once $repo.'/web/include/my_func.inc.php';

$root = sys_get_temp_dir().'/oj-password-reset-'.bin2hex(random_bytes(6));
try {
    mkdir($root, 0700, true);
    se_setup($root);
    foreach (array('lostpassword.php', 'lostpassword2.php') as $file) copy($repo.'/web/'.$file, $root.'/'.$file);
    foreach (array('my_func.inc.php', 'pdo.php', 'set_post_key.php') as $file) copy($repo.'/web/include/'.$file, $root.'/include/'.$file);
    file_put_contents($root.'/include/setlang.php', '<?php');
    file_put_contents($root.'/include/const.inc.php', '<?php');
    file_put_contents($root.'/include/db_info.inc.php', <<<'PHP'
<?php
session_start();
$OJ_NAME = 'ResetFixture';
$OJ_TEMPLATE = 'syzoj';
$OJ_VCODE = getenv('SE_VCODE') === '1';
$dbh = new PDO('sqlite:'.getenv('SE_TEST_DB'));
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once __DIR__.'/pdo.php';
PHP
    );
    mkdir($root.'/template/syzoj', 0700, true);
    foreach (array('lostpassword.php', 'lostpassword2.php', 'error.php') as $file) copy($repo.'/web/template/syzoj/'.$file, $root.'/template/syzoj/'.$file);
    file_put_contents($root.'/template/syzoj/header.php', '');
    file_put_contents($root.'/template/syzoj/footer.php', '');
    $db = new PDO('sqlite:'.$root.'/users.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE users(user_id TEXT PRIMARY KEY, email TEXT, password TEXT)');
    $db->prepare('INSERT INTO users VALUES(?,?,?)')->execute(array('student-fixture', 'reset@example.test', pwGen('original-password')));
    $password = fn() => $db->query('SELECT password FROM users')->fetchColumn();
    $post = array('user_id'=>'student-fixture', 'email'=>'reset@example.test');

    $page = se_run($root, array('endpoint'=>'lostpassword.php'));
    se_check($page['status'] === 200 && strpos($page['stdout'], 'name="vcode"') === false, 'captcha-off form omits captcha');
    $csrf = $page['session']['ResetFixture_postkey'];
    $post['postkey'] = $csrf;
    $session = $page['session_id'];
    $sent = se_run($root, array('endpoint'=>'lostpassword.php', 'post'=>$post, 'session'=>$session, 'ip'=>'192.0.2.80'));
    se_check($sent['exit'] === 0 && $sent['status'] === 200, 'captcha-off request reaches confirmation');
    se_check(count($sent['curl']) === 1 && strpos($sent['stdout'], 'lostpassword2.php') !== false, 'reset uses SES and renders confirmation');
    $key = $sent['session']['ResetFixture_lost_key'];
    se_check(preg_match('/^[0-9a-f]{16}$/', $key) === 1, 'reset key has 64 random bits');
    se_check($sent['session']['ResetFixture_lost_expires'] > time() && $sent['session']['ResetFixture_lost_expires'] <= time()+180, 'reset expires in three minutes');
    $payload = json_decode($sent['curl'][0]['body'], true);
    se_check($payload['Destination'] === array('reset@example.test'), 'reset goes only to the bound mailbox');
    se_check(json_decode($payload['Template']['TemplateData'], true)['code'] === $key, 'SES template contains the issued reset key');
    se_check(strpos($sent['stdout'], $key) === false, 'reset key is not exposed in the page');
    se_check(pwCheck('original-password', $password()), 'sending email does not change password');

    $confirm = array('user_id'=>'student-fixture', 'lost_key'=>$key, 'postkey'=>$csrf);
    foreach (array('lost_key'=>'wrong-key', 'user_id'=>'another-user', 'postkey'=>'expired') as $field=>$value) {
        $bad = $confirm; $bad[$field] = $value;
        $result = se_run($root, array('endpoint'=>'lostpassword2.php', 'post'=>$bad, 'session'=>$session));
        se_check($result['status'] === ($field === 'postkey' ? 403 : 400), 'confirmation rejects wrong '.$field);
        se_check(pwCheck('original-password', $password()), 'invalid confirmation keeps password');
    }
    $changed = se_run($root, array('endpoint'=>'lostpassword2.php', 'post'=>$confirm, 'session'=>$session));
    se_check($changed['status'] === 200 && pwCheck($key, $password()), 'captcha-off flow stores a usable login password');
    se_check(!isset($changed['session']['ResetFixture_lost_key']) && count($changed['curl']) === 0, 'successful reset consumes the key without sending another email');
    $replay = se_run($root, array('endpoint'=>'lostpassword2.php', 'post'=>$confirm, 'session'=>$session));
    se_check($replay['status'] === 400 && pwCheck($key, $password()), 'used reset key cannot be replayed');
    printf("PASS recovery captcha-off: SES capture, confirmation, password verification, one-time key\n");

    $onPage = se_run($root, array('endpoint'=>'lostpassword.php', 'vcode'=>true));
    se_check(strpos($onPage['stdout'], 'name="vcode"') !== false, 'captcha-on form displays captcha');
    $onSession = $onPage['session_id'];
    $onState = $onPage['session']; $onState['ResetFixture_vcode'] = 'ABCD';
    se_seed_session($root, $onSession, $onState);
    $onPost = $post; $onPost['postkey'] = $onState['ResetFixture_postkey'];
    foreach (array('', 'WRONG') as $vcode) {
        $onPost['vcode'] = $vcode;
        $blocked = se_run($root, array('endpoint'=>'lostpassword.php', 'vcode'=>true, 'post'=>$onPost, 'session'=>$onSession));
        se_check($blocked['status'] === 400 && count($blocked['curl']) === 0, 'captcha-on request rejects missing/incorrect captcha before sending');
    }
    $onPost['vcode'] = 'ABCD';
    $onSent = se_run($root, array('endpoint'=>'lostpassword.php', 'vcode'=>true, 'post'=>$onPost, 'session'=>$onSession, 'ip'=>'192.0.2.81'));
    se_check($onSent['status'] === 200 && count($onSent['curl']) === 1, 'correct captcha permits SES delivery');
    $onConfirm = array('user_id'=>'student-fixture', 'lost_key'=>$onSent['session']['ResetFixture_lost_key'], 'postkey'=>$onPost['postkey']);
    $blocked = se_run($root, array('endpoint'=>'lostpassword2.php', 'vcode'=>true, 'post'=>$onConfirm, 'session'=>$onSession));
    se_check($blocked['status'] === 400 && pwCheck($key, $password()), 'confirmation also enforces enabled captcha');
    $onConfirm['vcode'] = 'ABCD';
    $changed = se_run($root, array('endpoint'=>'lostpassword2.php', 'vcode'=>true, 'post'=>$onConfirm, 'session'=>$onSession));
    se_check($changed['status'] === 200 && pwCheck($onConfirm['lost_key'], $password()), 'captcha-on flow completes with valid captcha');
    printf("PASS recovery captcha-on: both stages enforce captcha, valid flow completes\n");

    $validState = $onSent['session'];
    $expiredState = $validState; $expiredState['ResetFixture_lost_expires'] = time()-1;
    se_seed_session($root, 'reset-expired', $expiredState);
    $before = $password();
    $expired = se_run($root, array('endpoint'=>'lostpassword2.php', 'post'=>$onConfirm, 'session'=>'reset-expired'));
    se_check($expired['status'] === 400 && $password() === $before, 'expired reset cannot update password');
    $empty = se_run($root, array('endpoint'=>'lostpassword2.php', 'post'=>array('postkey'=>'fixture'), 'session'=>'reset-empty'));
    se_check($empty['status'] === 403 && $password() === $before, 'missing session cannot authorize reset');

    foreach (array('no-creds', 'provider_error', 'network') as $index=>$mode) {
        $sid = 'reset-failure-'.$index;
        se_seed_session($root, $sid, array('ResetFixture_postkey'=>$csrf));
        $failed = se_run($root, array('endpoint'=>'lostpassword.php', 'post'=>$post, 'session'=>$sid, 'ip'=>'192.0.2.'.(90+$index), 'mode'=>$mode, 'creds'=>$mode !== 'no-creds'));
        se_check($failed['status'] === 503 && !isset($failed['session']['ResetFixture_lost_key']), 'failed '.$mode.' send creates no usable reset');
        se_check(count(se_cache_sets($failed)) === 0 && $password() === $before, 'failed send keeps cooldown/password unchanged');
        se_check(strpos($failed['stdout'], 'lostpassword2.php') === false, 'failed send does not display confirmation');
        se_assert_no_secret_leak($failed, 'reset '.$mode);
    }
    se_seed_session($root, 'reset-mismatch', array('ResetFixture_postkey'=>$csrf));
    $mismatch = $post; $mismatch['email'] = 'other@example.test';
    $bad = se_run($root, array('endpoint'=>'lostpassword.php', 'post'=>$mismatch, 'session'=>'reset-mismatch'));
    se_check($bad['status'] === 400 && count($bad['curl']) === 0, 'wrong bound email cannot trigger delivery');
    $noCsrf = $post; unset($noCsrf['postkey']);
    $bad = se_run($root, array('endpoint'=>'lostpassword.php', 'post'=>$noCsrf));
    se_check($bad['status'] === 403 && count($bad['curl']) === 0, 'CSRF failure cannot trigger delivery');
    se_seed_session($root, 'reset-db-failure', $validState);
    $db->exec("CREATE TRIGGER fail_update BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
    $failed = se_run($root, array('endpoint'=>'lostpassword2.php', 'post'=>$onConfirm, 'session'=>'reset-db-failure'));
    se_check($failed['status'] === 503 && $password() === $before, 'database failure is not reported as reset success');
    se_check(isset($failed['session']['ResetFixture_lost_key']), 'failed update does not consume a valid key');
    printf("PASS recovery failures: expiry, CSRF, account binding, mail rejection/network, database failure\n");
    printf("ALL PASS password-reset (isolated SQLite and captured SES; no real emails)\n");
} finally {
    $db = null;
    se_rmtree($root);
}
