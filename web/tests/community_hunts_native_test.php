<?php
// Explicitly authorized native Linux sandbox acceptance; use an isolated stack first.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') !== '--fixtures') { fwrite(STDERR,"Usage: php community_hunts_native_test.php --fixtures [--smoke]\n"); exit(1); }
ob_start(); // Session fixtures must be created before response output is flushed.
require __DIR__.'/../include/db_info.inc.php';
require __DIR__.'/../include/community_hunts.inc.php';
session_write_close();
$prefix='nativehunt_'.bin2hex(random_bytes(4)); $users=[]; $sessions=[]; $pids=[]; $failed=false;
function native_check($ok,$message) { if(!$ok) throw new RuntimeException($message); }
function native_http($path,$post,$sid) {
    $c=curl_init('http://127.0.0.1'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIE=>'PHPSESSID='.$sid]);
    if($post!==null) curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $body=curl_exec($c); $code=curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
    native_check($body!==false && $code<400,'HTTP '.$path.' status '.$code);
    native_check(!preg_match('/Fatal error|Parse error|Warning:|Uncaught /',$body),'PHP error '.$path);
    return [$body,$code];
}
function native_user($suffix) {
    global $prefix,$users,$sessions,$OJ_NAME;
    $user=$prefix.'_'.$suffix; $users[]=$user;
    hunt_query("INSERT INTO users(user_id,nick,password,defunct,register_num,reg_time) VALUES(?,'原生验收','unusable-test-password','N',1,NOW())",$user);
    $sid='nativehunt'.bin2hex(random_bytes(16)); $sessions[]=$sid; $key=bin2hex(random_bytes(16));
    session_id($sid); session_start(); $_SESSION=[$OJ_NAME.'_user_id'=>$user,$OJ_NAME.'_postkey'=>$key]; session_write_close();
    return [$user,$sid,$key];
}
function native_wait($ids) {
    $deadline=time()+100;
    do {
        $rows=hunt_query('SELECT solution_id,result FROM solution WHERE solution_id IN ('.implode(',',array_map('intval',$ids)).') ORDER BY solution_id');
        $pending=array_filter($rows,function($r){return in_array((int)$r['result'],[0,1,2,3,14],true);});
        if(count($rows)===count($ids) && !$pending) return $rows;
        usleep(500000);
    } while(time()<$deadline);
    throw new RuntimeException('Native queue timeout: '.json_encode($rows));
}
$cpp=function($body) { return "#include <iostream>\n#include <string>\nint main(){".$body.'}'; };
$valid=$cpp('std::cout<<"VALID";'); $one=$cpp('std::cout<<1;'); $zero=$cpp('std::cout<<0;');
$cases=[
 ['cpp_hit',1,$valid,$one,$zero,'hit',[13,13,13]],
 ['cpp_same',1,$valid,$one,$one,'miss',[13,13,13]],
 ['cpp_invalid',1,$cpp('std::cout<<"INVALID";'),$one,$zero,'invalid',[13,13,13]],
 ['cpp_re',1,$valid,$one,$cpp('return 7;'),'hit',[13,13,10]],
 ['cpp_tle',1,$valid,$one,$cpp('for(;;){}'),'hit',[13,13,7]],
 ['cpp_ole',1,$valid,$one,$cpp('std::cout<<std::string(17000,\'x\');'),'error',[13,13,9]],
 ['cpp_ce',1,$valid,$one,'this is not valid C++','error',[13,13,11]],
 ['reference_re',1,$valid,$cpp('return 2;'),$zero,'error',[13,10,13]],
 ['validator_re',1,$cpp('return 2;'),$one,$zero,'error',[10,13,13]],
 ['empty_output',1,$valid,$cpp(''),$one,'hit',[13,13,13]],
 ['python_hit',6,'print("VALID")','print(1)','print(0)','hit',[13,13,13]],
];
if(in_array('--smoke',$argv,true)) $cases=[$cases[0],$cases[3],$cases[10]];
try {
    foreach($cases as $i=>$case) {
        list($name,$lang,$validator,$reference,$buggy,$expected,$codes)=$case;
        list($user,$sid,$key)=native_user((string)$i);
        list($body,$code)=native_http('/hunt.php?mode=new',['action'=>'save','postkey'=>$key,'title'=>'临时原生验收 '.$name,'statement'=>'自动验收用临时题目，输入 1，完成后删除。','language'=>(string)$lang,'validator_source'=>$validator,'reference_source'=>$reference,'buggy_source'=>$buggy],$sid);
        native_check($code===303,'Native publish redirect');
        $c=hunt_query('SELECT id,version FROM hunt_challenge WHERE author=? ORDER BY id DESC LIMIT 1',$user)[0];
        list($body,$code)=native_http('/hunt.php?id='.$c['id'],['action'=>'attempt','postkey'=>$key,'version'=>(string)$c['version'],'input'=>'1'],$sid);
        native_check($code===303,'Native submit redirect');
        $a=hunt_query('SELECT * FROM hunt_attempt WHERE challenge_id=? AND user_id=? ORDER BY id DESC LIMIT 1',$c['id'],$user)[0];
        $ids=[$a['validator_sid'],$a['reference_sid'],$a['buggy_sid']];
        $rows=native_wait($ids); $actual=array_map('intval',array_column($rows,'result'));
        $result=hunt_attempt_result($a);
        echo json_encode(['case'=>$name,'attempt'=>(int)$a['id'],'solutions'=>$ids,'results'=>$actual,'verdict'=>$result[0][0]],JSON_UNESCAPED_UNICODE)."\n";
        native_check($actual===$codes && $result[0][0]===$expected,'Unexpected native result '.$name.': '.json_encode($result));
        native_check(!hunt_query('SELECT user_id FROM coin_wallet WHERE user_id=?',$user),'Custom runs must not award coins');
    }
    // Real standard submissions through the existing form endpoint and judge queue.
    list($user,$sid,$key)=native_user('normal');
    $pid=(int)hunt_query("INSERT INTO problem(title,defunct,time_limit,memory_limit,spj,in_date) VALUES('临时原生加法验收','N',1,128,'0',NOW())"); $pids[]=$pid;
    $dir='/home/judge/data/'.$pid;
    native_check(mkdir($dir,0755),'Create native fixture data directory');
    file_put_contents($dir.'/case.in',"2 3\n"); file_put_contents($dir.'/case.out',"5\n");
    foreach([1=>$cpp('int a,b;std::cin>>a>>b;std::cout<<a+b;'),6=>'print(sum(map(int,input().split())))'] as $lang=>$source) {
        if($lang===6) sleep(11); // Respect the existing ten-second standard submission limit.
        $before=hunt_query('SELECT COALESCE(MAX(solution_id),0) n FROM solution WHERE user_id=?',$user)[0]['n'];
        native_http('/submit.php',['id'=>$pid,'language'=>$lang,'source'=>$source,'postkey'=>$key,'vcode'=>''],$sid);
        $rows=hunt_query('SELECT solution_id FROM solution WHERE user_id=? AND problem_id=? ORDER BY solution_id DESC LIMIT 1',$user,$pid);
        native_check((bool)$rows && $rows[0]['solution_id']>$before,'Standard submission enqueued'); $id=$rows[0]['solution_id'];
        $result=native_wait([$id]);
        echo json_encode(['case'=>'standard_'.$lang,'solution'=>(int)$id,'result'=>(int)$result[0]['result']])."\n";
        native_check((int)$result[0]['result']===4,'Standard submission must AC');
    }
    native_check((int)hunt_query('SELECT balance FROM coin_wallet WHERE user_id=?',$user)[0]['balance']===2,'Real AC awards exactly 2 coins once');
    echo 'PASS: '.count($cases).' native challenge cases, standard C++/Python AC, real first-AC award and custom-run exclusion.'."\n";
} catch(Throwable $e) { $failed=true; fwrite(STDERR,'FAIL: '.$e->getMessage()."\n"); }
finally {
    // Never remove payloads while a sandbox may still be using them.
    $pending=0;
    foreach($users as $user) $pending+=(int)hunt_query('SELECT COUNT(*) n FROM solution WHERE user_id=? AND result IN (0,1,2,3,14)',$user)[0]['n'];
    if($pending) { fwrite(STDERR,'Preserved pending fixtures for investigation: '.$prefix."\n"); $failed=true; }
    else {
        foreach($users as $user) {
            foreach(hunt_query('SELECT solution_id FROM solution WHERE user_id=?',$user) as $r) {
                pdo_query('DELETE FROM sim WHERE s_id=?',$r['solution_id']);
                foreach(['runtimeinfo','compileinfo','custominput','source_code','source_code_user'] as $table) pdo_query("DELETE FROM $table WHERE solution_id=?",$r['solution_id']);
            }
            foreach(['solution','hunt_attempt','coin_first_ac','coin_ledger','coin_wallet','problem_unlock','problem_editorial','users'] as $table) pdo_query("DELETE FROM $table WHERE user_id=?",$user);
            pdo_query('DELETE FROM hunt_challenge WHERE author=?',$user);
        }
        foreach($pids as $pid) {
            pdo_query('DELETE FROM problem WHERE problem_id=?',$pid);
            $dir='/home/judge/data/'.$pid;
            if(is_dir($dir)) {
                $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
                foreach($files as $f) { if($f->isDir() && !$f->isLink()) rmdir($f->getPathname()); else unlink($f->getPathname()); }
                rmdir($dir);
            }
        }
    }
    foreach($sessions as $sid) { $path=ini_get('session.save_path').'/sess_'.$sid; if(is_file($path)) unlink($path); }
}
exit($failed?1:0);
