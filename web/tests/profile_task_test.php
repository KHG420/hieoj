<?php
// Run only on a disposable local database; creates and cleans its own user/tasks.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(($argv[1]??'')!=='--fixtures'){fwrite(STDERR,"Use --fixtures on a local deployment.\n");exit(1);}
require __DIR__.'/../include/db_info.inc.php';session_write_close();
$uid='prtest_'.bin2hex(random_bytes(5));$failed=false;
try{
 if(pdo_query("INSERT INTO users(user_id,nick,school,defunct,register_num) VALUES(?,?,'test','N',1)",$uid,$uid)!==true)throw new RuntimeException("Create profile fixture failed");
 foreach(array(array(1000,4),array(-1000,6),array(-1001,4),array(0,4)) as $task)pdo_query('INSERT INTO solution(user_id,problem_id,result,in_date) VALUES(?,?,?,NOW())',$uid,$task[0],$task[1]);
 $ch=curl_init('http://127.0.0.1/userinfo.php?user='.$uid);curl_setopt_array($ch,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20));$body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($code!==200||preg_match('/Fatal error|SQLSTATE|Warning:/',$body))throw new RuntimeException('Profile HTTP failed');
 if(preg_match('/p\((?:-\d+|0),|problem.php\?id=-/',$body))throw new RuntimeException('Internal task displayed as a practice problem');
 $row=pdo_query('SELECT solved,submit FROM users WHERE user_id=?',$uid)[0];
 if((int)$row['solved']!==1||(int)$row['submit']!==1)throw new RuntimeException('Internal task changed practice statistics');
 echo "PASS: internal judge tasks excluded from profile links and practice totals\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n");}
finally{pdo_query('DELETE FROM solution WHERE user_id=?',$uid);pdo_query('DELETE FROM users WHERE user_id=?',$uid);}
exit($failed?1:0);
