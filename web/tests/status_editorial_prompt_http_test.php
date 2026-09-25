<?php
// Run only against a disposable deployment; creates and cleans temporary fixtures.
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--fixtures') { exit(1); }
chdir(dirname(__DIR__));
require 'include/db_info.inc.php'; require 'include/editorial.inc.php';
$initial=session_id(); session_write_close(); if($initial) @unlink(ini_get('session.save_path').'/sess_'.$initial);
$uid='acprompt'.bin2hex(random_bytes(4)); $other=$uid.'x';$pids=[];$sids=[];$sessions=[];$cid=null;
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);}
function getpage($sid,$path='status.php', $post=null){
 usleep(200000);$c=curl_init('http://127.0.0.1/'.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIE=>'PHPSESSID='.$sid]);if ($post!==null) curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);$s=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);verify($code===($post===null?200:302),'HTTP '.$code);verify(!preg_match('/Fatal error|Warning:|Uncaught /',$s),'PHP error');return $s;
}
try{
 foreach([$uid,$other] as $u) editorial_query("INSERT INTO users(user_id,nick,defunct,register_num) VALUES(?,?,'N',1)",[$u,'测试用户']);
 foreach(['N','N','Y','N'] as $hidden){editorial_query('INSERT INTO problem(title,source,defunct,in_date) VALUES(?,?,?,NOW())',['A + B','test',$hidden]);$pids[]=(int)editorial_db()->lastInsertId();}
 editorial_query("INSERT INTO contest(title,start_time,end_time,private,defunct) VALUES('test',NOW(),DATE_ADD(NOW(),INTERVAL 1 DAY),1,'N')");$cid=(int)editorial_db()->lastInsertId();
 editorial_query('INSERT INTO contest_problem(contest_id,problem_id,num) VALUES(?,?,0)',[$cid,$pids[3]]);
 $specs=[[$uid,$pids[0],4,null],[$uid,$pids[1],0,null],[$other,$pids[0],4,null],[$uid,$pids[2],4,null],[$uid,$pids[3],4,null],[$uid,$pids[0],4,$cid],[$uid,$pids[1],6,null]];
 foreach($specs as $sp){editorial_query('INSERT INTO solution(user_id,problem_id,result,contest_id,in_date,language) VALUES(?,?,?,?,NOW(),0)',$sp);$sids[]=(int)editorial_db()->lastInsertId();}
 foreach([$uid,null] as $u){$sid='acprompt'.bin2hex(random_bytes(12));$sessions[]=$sid;session_id($sid);session_start();$_SESSION=$u?[$OJ_NAME.'_user_id'=>$u]:[];session_write_close();}
 $hintKey=$OJ_NAME.'_editorial_prompt_'.$uid;
 $mapOf=function($html){preg_match('/var editorial_prompt_problems = (.*);/',$html,$m);return json_decode($m[1],true);};
 $hint=function($solution,$age=0)use($sessions,$hintKey){session_id($sessions[0]);session_start();$_SESSION[$hintKey]=['sid'=>$solution,'created'=>time()-$age];session_write_close();};
 verify($mapOf(getpage($sessions[0]))===[], 'History alone never triggers a success hint');
 $hint($sids[1]);
 verify($mapOf(getpage($sessions[0]))===[$sids[1]=>$pids[1]], 'Only the current pending submission is watched despite history AC');
 verify($mapOf(getpage($sessions[0]))===[], 'Refresh cannot replay a consumed hint from cache');
 $hint($sids[0]);
 verify($mapOf(getpage($sessions[0]))===[$sids[0]=>$pids[0]], 'Fast AC is still eligible on first redirect');
 foreach([$sids[2],$sids[3],$sids[4],$sids[5],$sids[6]] as $excluded){$hint($excluded);verify($mapOf(getpage($sessions[0]))===[], 'Other users, hidden/private, contest and failed submissions excluded');}
 $hint($sids[0],301);verify($mapOf(getpage($sessions[0]))===[], 'Abandoned old hint expires');
 // Submit through the real handler. No judge runs in this disposable stack.
 editorial_query('UPDATE solution SET in_date=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE user_id=?',[$uid]);
 editorial_query('UPDATE problem SET spj=0 WHERE problem_id=?',[$pids[0]]);
 getpage($sessions[0],'submit.php',['id'=>$pids[0],'language'=>0,'source'=>'int main(){return 0;}']);
 $submitted=(int)editorial_query('SELECT MAX(solution_id) FROM solution WHERE user_id=?',[$uid])->fetchColumn();
 verify(!in_array($submitted,$sids,true),'A new solution was created');$sids[]=$submitted;
 session_id($sessions[0]);session_start();$saved=$_SESSION[$hintKey]??null;session_write_close();
 verify(($saved['sid']??0)===$submitted,'Real submit records exactly its new solution ID');
 verify($mapOf(getpage($sessions[0],'status.php?user_id='.rawurlencode($uid)))===[$submitted=>$pids[0]], 'Submit redirect watches the new attempt, not earlier AC of the same problem');
 verify($mapOf(getpage($sessions[1]))===[], 'Anonymous map empty');
 echo "PASS: real submit, same-problem historical AC, pending, failure, fast AC, one-use/cache, expiry and access boundaries\n";
}finally{
 foreach($sids as $sid){foreach(['source_code_user','source_code'] as $table)editorial_query("DELETE FROM $table WHERE solution_id=?",[$sid]);editorial_query('DELETE FROM solution WHERE solution_id=?',[$sid]);}
 foreach([$uid,$other] as $u){foreach(['coin_ledger','coin_first_ac','coin_wallet'] as $t)editorial_query("DELETE FROM $t WHERE user_id=?",[$u]);editorial_query('DELETE FROM users WHERE user_id=?',[$u]);}
 if($cid){editorial_query('DELETE FROM contest_problem WHERE contest_id=?',[$cid]);editorial_query('DELETE FROM contest WHERE contest_id=?',[$cid]);}
 foreach($pids as $id)editorial_query('DELETE FROM problem WHERE problem_id=?',[$id]);
 foreach($sessions as $sid)@unlink(ini_get('session.save_path').'/sess_'.$sid);
}
