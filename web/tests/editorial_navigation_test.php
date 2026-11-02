<?php
// CLI-only. Real navigation/profile templates and page cache, with private
// sessions and SQLite. An optional output directory keeps synthetic UI snapshots.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function en_check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function en_remove($root) {
    if (!is_dir($root)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
}
function en_render($root, $role, $target = '') {
    $env = getenv(); $env['TMPDIR'] = $root.'/tmp';
    $process = proc_open(array(PHP_BINARY, $root.'/render.php', $role, $target),
        array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')), $pipes, $root, $env);
    en_check(is_resource($process), 'start template render'); fclose($pipes[0]);
    $body=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process);
    en_check($exit===0 && !preg_match('/Warning:|Fatal error:|Uncaught /', $body.$errors), 'render without PHP errors: '.$errors);
    return $body;
}
$repo=dirname(__DIR__,2); $root=sys_get_temp_dir().'/oj-editorial-navigation-'.bin2hex(random_bytes(6));
$snapshots=$argv[1] ?? null; $db=null;
try {
    foreach (array('include','template/syzoj','template/sta_sty','sessions','tmp') as $path) mkdir($root.'/'.$path,0700,true);
    foreach (array('include/cache_start.php','include/cache_end.php','include/cache_layer.php','include/pdo.php',
        'include/set_post_key.php','include/navigation.inc.php','template/syzoj/header.php','template/syzoj/css.php','template/sta_sty/userinfo.php') as $path) copy($repo.'/web/'.$path,$root.'/'.$path);
    file_put_contents($root.'/template/syzoj/footer.php','</div></div></body></html>');
    file_put_contents($root.'/render.php', <<<'PHP'
<?php
chdir(__DIR__);
$OJ_NAME='算法设计在线评测系统'; $OJ_TEMPLATE='syzoj'; $OJ_CDN_URL='';
$OJ_ONLINE=false; $OJ_WHITE_BLACK=false; $OJ_BBS=true; $OJ_SaaS_ENABLE=false; $OJ_REGISTER=true; $OJ_MEMCACHE=false;
$_SERVER['HTTP_HOST']='editorial-fixture.local'; $_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
$role=$argv[1]; $viewer=$role==='admin' ? 'fixture-admin' : 'fixture-student';
$user=$argv[2] ?: $viewer;
$_SERVER['REQUEST_URI']='/userinfo.php?user='.$user;
ini_set('session.save_path',__DIR__.'/sessions'); session_id('editorial-nav-'.$role); session_start();
if ($role!=='anonymous') $_SESSION[$OJ_NAME.'_user_id']=$viewer;
if ($role==='admin') $_SESSION[$OJ_NAME.'_administrator']=true;
session_write_close();
$cache_time=60; $OJ_CACHE_SHARE=false;
require './include/cache_start.php';
echo '<!-- fixture render '.bin2hex(random_bytes(4)).' -->';
$dbh=new PDO('sqlite:'.__DIR__.'/fixture.sqlite');
$dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$dbh->sqliteCreateFunction('date_format',fn($time,$format)=>substr($time,0,10));
$dbh->sqliteCreateFunction('UNIX_TIMESTAMP',fn($time)=>strtotime($time));
require './include/pdo.php';
$MSG_HOME='主页'; $MSG_PROBLEMS='问题'; $MSG_SOURCE='来源/分类'; $MSG_CONTEST='竞赛&作业'; $MSG_STATUS='状态';
$MSG_RANKLIST='排名'; $MSG_FAQ='常见问答'; $MSG_BBS='讨论版'; $MSG_REG_INFO='注册信息'; $MSG_ADMIN='管理';
$MSG_LOGOUT='注销'; $MSG_LOGIN='登录'; $MSG_REGISTER='注册'; $MSG_Number='名次'; $MSG_SOVLED='解决'; $MSG_SUBMIT='提交';
$nick='隔离测试账号'; $school='测试班级'; $email=''; $qq_num=''; $Rank=1; $AC=0; $Submit=0; $chongFu='0.00%';
$view_userstat=array(); $chart_data_ac=array();
require './template/sta_sty/userinfo.php';
require './include/cache_end.php';
PHP
    );
    $db=new PDO('sqlite:'.$root.'/fixture.sqlite'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    foreach (array(
        'CREATE TABLE problem_editorial(id INTEGER PRIMARY KEY,status TEXT)',
        'CREATE TABLE `modify`(id INTEGER PRIMARY KEY,status INTEGER)',
        'CREATE TABLE users(user_id TEXT,register_num INTEGER)',
        'CREATE TABLE loginlog(user_id TEXT,time TEXT)',
        'CREATE TABLE acmer(user_id TEXT,status INTEGER,weight INTEGER,join_time TEXT)',
        'CREATE TABLE achieve(user_id TEXT,status INTEGER,year TEXT,content TEXT,award TEXT,time TEXT)',
        'CREATE TABLE solution(user_id TEXT,problem_id INTEGER,result INTEGER)',
    ) as $sql) $db->exec($sql);
    $db->exec("INSERT INTO users VALUES('fixture-admin',1),('fixture-student',1)");

    $empty=en_render($root,'admin');
    en_check(substr_count($empty,'oj-editorial-review-link')===2,'administrator gets both direct review links');
    en_check(strpos($empty,'oj-editorial-dot')===false && strpos($empty,'oj-account-attention')===false,'no pending items, no red dots');
    $studentBefore=en_render($root,'student');
    $db->exec("INSERT INTO problem_editorial VALUES(1,'pending'),(2,'pending'),(3,'approved'),(4,'rejected')");
    $pending=en_render($root,'admin');
    en_check(substr_count($pending,'oj-editorial-dot')===2 && substr_count($pending,'oj-account-attention')===1,'new pending editorials mark menu trigger and both links');
    en_check(substr_count($pending,'href="solutions.php?tab=review"')===2 && strpos($pending,'2篇待审核')!==false,'links go directly to the full review list with accurate accessible count');
    $other=en_render($root,'admin','fixture-student');
    en_check(substr_count($other,'oj-editorial-review-link')===1,'admin viewing another user only gets the account menu link');
    $student=en_render($root,'student');
    en_check($student===$studentBefore,'ordinary student page cache remains enabled');
    en_check(strpos($student,'oj-editorial-review-link')===false && strpos($student,'oj-account-attention')===false,'students receive no review shortcut or pending indicator');
    $anonymous=en_render($root,'anonymous');
    en_check(strpos($anonymous,'oj-editorial-review-link')===false && strpos($anonymous,'oj-account-attention')===false,'anonymous viewers receive no review metadata');

    $db->exec("UPDATE problem_editorial SET status='approved' WHERE id=1");
    $remaining=en_render($root,'admin');
    en_check(strpos($remaining,'1篇待审核')!==false && substr_count($remaining,'oj-editorial-dot')===2,'partial review keeps the pending indicator');
    $db->exec("UPDATE problem_editorial SET status='rejected' WHERE id=2");
    $cleared=en_render($root,'admin');
    en_check(strpos($cleared,'oj-editorial-dot')===false && strpos($cleared,'oj-account-attention')===false,'all approved or rejected clears indicators on the next page load');
    en_check(substr_count($cleared,'oj-editorial-review-link')===2,'empty review queue keeps both shortcuts available');
    en_check($empty!==$cleared,'administrator pages do not reuse stale whole-page HTML');
    printf("PASS editorial navigation: both shortcuts, pending/partial/cleared, role and profile ownership, cache freshness\n");

    $db->exec('DROP TABLE problem_editorial');
    $unavailable=en_render($root,'admin');
    en_check(strpos($unavailable,'oj-editorial-dot')===false,'optional editorial table missing does not break navigation');
    printf("PASS optional module: navigation renders without editorial tables\n");
    if ($snapshots!==null) {
        if (!is_dir($snapshots)) mkdir($snapshots,0755,true);
        foreach (array('pending'=>$pending,'empty'=>$cleared,'student'=>$student) as $name=>$body) file_put_contents($snapshots.'/'.$name.'.html',$body);
    }
    printf("ALL PASS editorial-navigation\n");
} finally {
    $db=null; en_remove($root);
}
