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
    copy($repo.'/web/template/syzoj/solutions.php',$root.'/template/syzoj/solutions.php');
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
if (str_starts_with($argv[2], 'solutions-')) {
    function editorial_escape($value) { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    function editorial_min_prose() { return 120; }
    $case=$argv[2]; $user=$role==='anonymous' ? null : $viewer; $admin=$role==='admin';
    $tab=str_contains($case,'mine') ? 'mine' : 'published';
    $navProblemId=str_contains($case,'mine') ? 0 : 1000; $problemId=$navProblemId;
    $contextProblemId=$navProblemId; $navQuery=$navProblemId ? '&amp;from_problem=1000' : '';
    $ready=true; $error=null; $writing=false; $showComposer=false; $loadMarkdown=false;
    $canWriteEditorial=$user && $navProblemId && $case!=='solutions-unpassed'; $passed=$canWriteEditorial;
    $heading=$tab==='mine' ? '我的题解' : 'P1000 · 测试题目 · 题解';
    $show_title=editorial_escape($heading); $OJ_EDITORIAL_VIEWPORT=true;
    $id=0; $page=1; $returnProblemId=0; $hasNext=false; $balance=0; $reference=null;
    $article=null; $problem=$navProblemId ? array('title'=>'测试题目') : null;
    $canRead=true; $submitted=false; $rows=array(); $statusNames=array('approved'=>'审核通过');
    if ($case==='solutions-problem' || $case==='solutions-mine-populated') {
        for ($i=1;$i<=20;$i++) $rows[]=array('id'=>$i,'problem_id'=>1000,'title'=>'已有题解 '.$i,'problem_title'=>'测试题目','user_id'=>'fixture-author','created_at'=>'2026-11-04','status'=>'approved');
    }
    if ($case==='solutions-reference' || $case==='solutions-detail') {
        $reference=$case==='solutions-reference' ? array('solution_id'=>1) : null; $id=$reference ? 0 : 1;
        $article=array('title'=>'长篇题解或参考代码','content'=>str_repeat("    int example;\n",200),'content_format'=>'plain','status'=>'approved','user_id'=>'fixture-author','created_at'=>'2026-11-04','review_note'=>'');
    }
    $_GET['from_adventure']='route';
    require './template/syzoj/solutions.php';
    exit;
}
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

    foreach (array('problem','reference','detail') as $case) {
        $body=en_render($root,'student','solutions-'.$case);
        preg_match('/<nav class="ed-tabs"[^>]*>(.*?)<\/nav>/s',$body,$tabs);
        en_check(str_contains($tabs[1] ?? '', 'problem_id=1000&amp;from_adventure=route&amp;write=1'),'eligible student has a top writing entry with adventure context: '.$case);
        en_check(strpos($body,'>编写题解</a>')<strpos($body,'class="ed-panel"'),'writing entry precedes long content: '.$case);
        en_check(!str_contains($body,'ed-write-invite'),'no duplicate writing panel below content: '.$case);
    }
    foreach (array('student'=>'solutions-unpassed','anonymous'=>'solutions-problem') as $role=>$case) {
        $body=en_render($root,$role,$case);
        en_check(!str_contains($body,'&amp;write=1'),'unpassed and anonymous readers have no qualified writing shortcut');
    }
    foreach (array('solutions-mine-empty','solutions-mine-populated') as $case) {
        $body=en_render($root,'student',$case);
        preg_match('/<nav class="ed-tabs"[^>]*>(.*?)<\/nav>/s',$body,$tabs);
        en_check(str_contains($tabs[1] ?? '', 'href="problemset.php">选题写题解'),'my editorials keeps a visible start action as records grow');
        en_check(!str_contains($tabs[1] ?? '', 'aria-current="page">编写题解'),'reading or listing never marks writing as current');
    }
    en_check(str_contains(en_render($root,'student','solutions-mine-empty'),'class="ui primary button" href="problemset.php">选题写题解'),'empty my editorials has an actionable start button');
    printf("PASS editorial writing entry: top navigation, long content, role eligibility, source context, empty and populated lists\n");

    $db->exec('DROP TABLE problem_editorial');
    $unavailable=en_render($root,'admin');
    en_check(strpos($unavailable,'oj-editorial-dot')===false,'optional editorial table missing does not break navigation');
    printf("PASS optional module: navigation renders without editorial tables\n");
    if ($snapshots!==null) {
        if (!is_dir($snapshots)) mkdir($snapshots,0755,true);
        foreach (array('pending'=>$pending,'empty'=>$cleared,'student'=>$student) as $name=>$body) file_put_contents($snapshots.'/'.$name.'.html',$body);
        foreach (array('problem','reference','detail','mine-empty','mine-populated','unpassed') as $case) {
            file_put_contents($snapshots.'/'.$case.'.html',en_render($root,'student','solutions-'.$case));
        }
    }
    printf("ALL PASS editorial-navigation\n");
} finally {
    $db=null; en_remove($root);
}
