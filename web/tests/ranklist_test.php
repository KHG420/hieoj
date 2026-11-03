<?php
// CLI-only: real SQL against a private temporary SQLite database, no OJ database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Asia/Shanghai');
$root = sys_get_temp_dir().'/oj-ranklist-test-'.bin2hex(random_bytes(6));
mkdir($root.'/include', 0700, true);
mkdir($root.'/template/test', 0700, true);
$checks = 0;
function rank_check($ok, $message) {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function rank_page($query = array()) {
    global $root;
    $process = proc_open(array(PHP_BINARY, $root.'/run.php', base64_encode(json_encode($query))), array(0=>array('pipe','r'), 1=>array('pipe','w'), 2=>array('pipe','w')), $pipes, $root);
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    rank_check(proc_close($process) === 0 && $error === '', 'Controller runs without PHP or SQL errors');
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}
try {
    copy(__DIR__.'/../ranklist.php', $root.'/ranklist.php');
    copy(__DIR__.'/../include/navigation.inc.php', $root.'/include/navigation.inc.php');
    foreach (array('cache_start.php','cache_end.php','setlang.php') as $file) file_put_contents($root.'/include/'.$file, '<?php');
    file_put_contents($root.'/include/db_info.inc.php', <<<'PHP'
<?php
date_default_timezone_set('Asia/Shanghai');
$OJ_NAME='rank-test'; $OJ_TEMPLATE='test'; $MSG_RANKLIST='排名';
$db=new PDO('sqlite:'.__DIR__.'/../db.sqlite',null,null,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
$db->sqliteCreateFunction('NOW',function(){return date('Y-m-d H:i:s');});
$queries=0;
PHP
    );
    file_put_contents($root.'/include/memcache.php', <<<'PHP'
<?php
function mysql_query_cache($sql,...$args) {
    global $db,$queries;
    $queries++;
    // Match MySQL's default backslash escaping for bound LIKE patterns.
    $sql=str_replace('LIKE ?', "LIKE ? ESCAPE '\\'", $sql);
    $q=$db->prepare($sql);$q->execute($args);return $q->fetchAll();
}
PHP
    );
    file_put_contents($root.'/include/academic_directory.php', '<?php function academic_directory_colleges(){return array();} function academic_directory_years($n){return array("26");}');
    file_put_contents($root.'/template/test/ranklist.php', '<?php echo json_encode(array("rows"=>$view_rank,"total"=>(int)$view_total,"queries"=>$queries));');
    file_put_contents($root.'/run.php', '<?php $_GET=json_decode(base64_decode($argv[1]),true);require "ranklist.php";');
    $db = new PDO('sqlite:'.$root.'/db.sqlite', null, null, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
    $db->exec('CREATE TABLE users(user_id TEXT PRIMARY KEY,school TEXT,nick TEXT,solved INTEGER,submit INTEGER,reg_time TEXT,defunct TEXT,xueYuan TEXT);
        CREATE TABLE solution(solution_id INTEGER PRIMARY KEY,user_id TEXT,problem_id INTEGER,result INTEGER,in_date TEXT);
        CREATE TABLE sim(s_id INTEGER PRIMARY KEY,sim INTEGER);');
    $user = $db->prepare("INSERT INTO users VALUES(?,?,?,?,?,?,'N','测试学院')");
    $submission = $db->prepare('INSERT INTO solution(user_id,problem_id,result,in_date) VALUES(?,?,?,?)');
    $recent = date('Y-m-d H:i:s', strtotime('-1 day'));
    $old = date('Y-m-d H:i:s', strtotime('-2 years'));
    $future = date('Y-m-d H:i:s', strtotime('+1 day'));
    // Reverse insertion makes a missing final ordering key observable.
    for ($i=55;$i>=0;$i--) {
        $id=sprintf('student_%02d',$i);
        $user->execute(array($id,'测试班2601',$id,2,4,'2024-01-01 00:00:00'));
        $submission->execute(array($id,1001,4,$recent));
        $db->exec('INSERT INTO sim VALUES('.$db->lastInsertId().',20)');
        $submission->execute(array($id,1001,4,$recent));
        $submission->execute(array($id,1002,6,$recent));
        $submission->execute(array($id,1002,4,$old));
        $db->exec('INSERT INTO sim VALUES('.$db->lastInsertId().',80)');
    }
    foreach (array('historical','future','custom','inactive','disabled') as $id) {
        $user->execute(array($id,'其他班2602',$id,1,$id==='inactive'?0:1,'2024-01-01 00:00:00'));
        if ($id==='inactive') continue;
        $submission->execute(array($id,$id==='custom'?0:1001,4,$id==='historical'?$old:($id==='future'?$future:$recent)));
    }
    $db->exec("UPDATE users SET defunct='Y' WHERE user_id='disabled'");
    $first=rank_page();$second=rank_page(array('start'=>50));
    rank_check($first['total']===56 && $second['total']===56, 'Window total includes eligible users across both pages');
    rank_check(count($first['rows'])===50 && count($second['rows'])===6, 'Pagination keeps every eligible user');
    rank_check($first['queries']===1 && $second['queries']===1, 'Populated annual pages execute one ranking query');
    $rows=array_merge($first['rows'],$second['rows']);
    foreach ($rows as $i=>$row) rank_check(str_contains($row[1],sprintf('student_%02d',$i)), 'Exact ties use a stable user order without page overlap');
    rank_check(str_contains($first['rows'][0][4],"jresult=4'>1</a>") && str_contains($first['rows'][0][5],"'>3</a>"), 'Annual statistics count distinct accepted problems and all eligible submissions');
    rank_check(str_contains($first['rows'][0][7],'20.00%'), 'Annual similarity excludes historical submissions');
    $filtered=rank_page(array('prefix'=>'student_0','school'=>'测试班2601','nj'=>'26','xy'=>'测试学院'));
    rank_check($filtered['total']===10 && count($filtered['rows'])===10 && $filtered['queries']===1, 'Window total follows every active filter');
    $missing=rank_page(array('prefix'=>'not_a_student'));
    rank_check($missing['total']===0 && !$missing['rows'] && $missing['queries']===2, 'Empty results retain the fallback count');
    $past=rank_page(array('start'=>10000));
    rank_check($past['total']===56 && !$past['rows'] && $past['queries']===2, 'Offsets beyond the last page retain the actual total');
    $all=rank_page(array('range'=>'all','prefix'=>'student_'));
    rank_check($all['total']===56 && $all['queries']===2, 'All-time ranking retains its users-only count');
    rank_check(str_contains($all['rows'][0][4],"jresult=4'>2</a>") && str_contains($all['rows'][0][5],"'>4</a>") && str_contains($all['rows'][0][7],'50.00%'), 'All-time scores and similarity remain unchanged');
    rank_check(rank_page(array('range'=>'all'))['total']===59, 'All-time ranking uses stored counters and excludes disabled/empty accounts');
    $user->execute(array('boundary','边界班2603','boundary',4,4,'2024-01-01 00:00:00'));
    $since=date('Y-m-d',strtotime('-1 year')).' 00:00:00';
    $submission->execute(array('boundary',1001,4,$since));
    $submission->execute(array('boundary',1002,4,date('Y-m-d H:i:s',strtotime($since)-1)));
    $submission->execute(array('boundary',0,4,$recent));
    $submission->execute(array('boundary',1003,4,$future));
    $boundary=rank_page(array('prefix'=>'boundary'));
    rank_check($boundary['total']===1 && str_contains($boundary['rows'][0][4],"jresult=4'>1</a>") && str_contains($boundary['rows'][0][5],"'>1</a>"), 'Annual boundaries include the cutoff and exclude future/custom submissions');
    echo "PASS: $checks ranking checks\n";
} finally {
    $db=null;$user=null;$submission=null;
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) { if($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
