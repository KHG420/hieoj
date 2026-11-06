<?php
// CLI-only: isolated HTTP server, SQLite data and private APCu/file caches.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Asia/Shanghai');
$root=sys_get_temp_dir().'/oj-rank-cache-'.bin2hex(random_bytes(6));
foreach (array('include','template/test','tmp') as $dir) mkdir($root.'/'.$dir,0700,true);
$checks=0;$server=null;
function rank_cache_check($ok,$message) {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    ++$checks;
}
try {
    foreach (array('ranklist.php','include/cache_start.php','include/cache_end.php','include/cache_layer.php','include/memcache.php','include/navigation.inc.php') as $file)
        copy(__DIR__.'/../'.$file,$root.'/'.$file);
    file_put_contents($root.'/include/setlang.php','<?php');
    file_put_contents($root.'/include/academic_directory.php','<?php function academic_directory_colleges(){return array();} function academic_directory_years($n){return array("26");}');
    file_put_contents($root.'/include/db_info.inc.php', <<<'PHP'
<?php
date_default_timezone_set('Asia/Shanghai');
$OJ_NAME='rank-cache';$OJ_TEMPLATE='test';$MSG_RANKLIST='排名';$OJ_MEMCACHE=false;
$db=new PDO('sqlite:'.__DIR__.'/../data.sqlite',null,null,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
$db->sqliteCreateFunction('NOW',function(){return date('Y-m-d H:i:s');});
function pdo_query($sql,...$args) {
    global $db;
    file_put_contents(__DIR__.'/../queries.log',"query\n",FILE_APPEND);
    $sql=str_replace('LIKE ?',"LIKE ? ESCAPE '\\'",$sql);
    $q=$db->prepare($sql);$q->execute($args);return $q->fetchAll();
}
PHP
    );
    file_put_contents($root.'/template/test/ranklist.php', <<<'PHP'
<?php
$state=json_decode(file_get_contents(__DIR__.'/../../state.json'),true);
echo json_encode(array('rows'=>$view_rank,'total'=>(int)$view_total,
    'viewer'=>$_SESSION['rank-cache_user_id']??null,'pending'=>$state['pending']));
PHP
    );
    file_put_contents($root.'/router.php', <<<'PHP'
<?php
session_start();
$_SESSION['rank-cache_user_id']=$_GET['viewer']??'alice';
$_SESSION['rank-cache_administrator']=true;
if (isset($_GET['expire'])) {
    // Shorten only the isolated test entries, then exercise real expiration.
    require 'include/cache_layer.php';$count=0;
    if ($OJ_APCU_OK) {
        foreach (apcu_cache_info()['cache_list'] as $entry) if (strpos($entry['info'],'ranklist:data:v1:')===0) {
            apcu_store($entry['info'],apcu_fetch($entry['info']),1);++$count;
        }
    } else foreach (glob(cache_root_dir().'/*.cache')?:array() as $file) {
        $data=unserialize(file_get_contents($file));
        if (isset($data['val']['rows'],$data['val']['total'])) {
            $data['exp']=time()+1;file_put_contents($file,serialize($data),LOCK_EX);++$count;
        }
    }
    echo json_encode(array('expired_entries'=>$count));exit;
}
require 'ranklist.php';
PHP
    );
    foreach (array('file'=>0,'apcu'=>1) as $backend=>$enabled) {
        $db=new PDO('sqlite:'.$root.'/data.sqlite',null,null,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
        $db->exec('DROP TABLE IF EXISTS users;DROP TABLE IF EXISTS solution;DROP TABLE IF EXISTS sim;
            CREATE TABLE users(user_id TEXT PRIMARY KEY,school TEXT,nick TEXT,solved INTEGER,submit INTEGER,reg_time TEXT,defunct TEXT,xueYuan TEXT);
            CREATE TABLE solution(solution_id INTEGER PRIMARY KEY,user_id TEXT,problem_id INTEGER,result INTEGER,in_date TEXT);
            CREATE TABLE sim(s_id INTEGER PRIMARY KEY,sim INTEGER);');
        $db->exec("INSERT INTO users VALUES('student','测试班2601','Example',1,1,'2024-01-01','N','测试学院')");
        $query=$db->prepare("INSERT INTO solution(user_id,problem_id,result,in_date) VALUES('student',?,4,?)");
        $query->execute(array(1001,date('Y-m-d H:i:s',time()-86400)));
        file_put_contents($root.'/state.json',json_encode(array('pending'=>1)));
        file_put_contents($root.'/queries.log','');
        foreach (glob($root.'/tmp/hustoj*/*')?:array() as $file) unlink($file);
        $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);rank_cache_check($socket!==false,'Private listener available');
        $address=stream_socket_get_name($socket,false);fclose($socket);
        $server=proc_open(array(PHP_BINARY,'-d','apc.enabled='.$enabled,'-d','apc.enable_cli='.$enabled,
            '-d','disable_functions='.($enabled?'':'apcu_fetch'),'-d','sys_temp_dir='.$root.'/tmp',
            '-d','session.save_path='.$root.'/tmp','-d','display_errors=1','-S',$address,'router.php'),
            array(0=>array('pipe','r'),1=>array('file',$root.'/server.log','a'),2=>array('file',$root.'/server.log','a')),$pipes,$root);
        fclose($pipes[0]);
        for ($attempt=0;$attempt<100;++$attempt) {
            $ready=@stream_socket_client('tcp://'.$address,$errno,$error,.1);
            if ($ready) {fclose($ready);break;}usleep(20000);
        }
        rank_cache_check((bool)$ready,'Private HTTP server started');
        $request=function($params=array())use($address){
            $body=file_get_contents('http://'.$address.'/?'.http_build_query($params),false,
                stream_context_create(array('http'=>array('timeout'=>3))));
            try {return json_decode($body,true,512,JSON_THROW_ON_ERROR);}
            catch (JsonException $error) {throw new RuntimeException('Invalid isolated response: '.substr($body,0,2000),0,$error);}
        };
        $queries=function()use($root){return count(file($root.'/queries.log',FILE_IGNORE_NEW_LINES));};
        $first=$request();$before=$queries();
        rank_cache_check(count($first['rows'])===1 && $first['total']===1,'Cold ranking and pagination total agree');
        rank_cache_check($before===1,'Populated annual ranking executes one SQL query');
        file_put_contents($root.'/state.json',json_encode(array('pending'=>2)));
        $second=$request(array('viewer'=>'bob'));
        rank_cache_check($second['rows']===$first['rows'] && $queries()===$before,'Public ranking data is reused across viewers');
        rank_cache_check($second['viewer']==='bob' && $second['pending']===2,'Viewer and pending-review state stay live');
        $request(array('prefix'=>'student'));rank_cache_check($queries()>$before,'Search filters use a different entry');
        $before=$queries();$request(array('prefix'=>'student'));rank_cache_check($queries()===$before,'Repeated search reuses its own entry');
        $before=$queries();$empty=$request(array('prefix'=>'missing'));
        rank_cache_check(!$empty['rows'] && $empty['total']===0 && $queries()===$before+2,'Empty results keep their true total');
        $before=$queries();$request(array('prefix'=>'missing'));rank_cache_check($queries()===$before,'Empty arrays are cached');
        $before=$queries();$past=$request(array('start'=>50));
        rank_cache_check(!$past['rows'] && $past['total']===1 && $queries()===$before+2,'Out-of-range page has an independent entry and count');
        $before=$queries();$request(array('start'=>50));rank_cache_check($queries()===$before,'Out-of-range page and count are reused together');
        $before=$queries();$request(array('range'=>'all'));rank_cache_check($queries()===$before+2,'All-time and annual rankings do not share data');
        $before=$queries();$query->execute(array(1002,date('Y-m-d H:i:s',time()-86400)));
        sleep(6);
        rank_cache_check($request()['rows']===$first['rows'] && $queries()===$before,'Ranking survives the old five-second query TTL');
        rank_cache_check($request(array('expire'=>1))['expired_entries']>=1,'Private entries shortened for the expiration check');
        sleep(2);$fresh=$request();
        rank_cache_check($queries()===$before+1 && strpos($fresh['rows'][0][4],"'>2</a>")!==false,'Expired ranking recomputes current scores');
        printf("PASS %s ranking cache, viewer isolation, filters, pagination and expiration\n",$backend);
        proc_terminate($server);proc_close($server);$server=null;$query=null;$db=null;
    }
    echo "PASS: $checks ranking cache checks\n";
} finally {
    if (is_resource($server)) {proc_terminate($server);proc_close($server);}
    $db=null;$query=null;
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}
    rmdir($root);
}
