<?php
// CLI only. Uses random fixtures on a disposable local deployment.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(($argv[1]??'')!=='--fixtures'){fwrite(STDERR,"Run with --fixtures against a disposable database.\n");exit(1);}
require __DIR__.'/../include/db_info.inc.php';require __DIR__.'/../include/editorial.inc.php';session_write_close();
$prefix='labtest_'.bin2hex(random_bytes(5));$users=array($prefix.'a',$prefix.'b',$prefix.'m');$sessions=array();$key=bin2hex(random_bytes(16));$checks=0;$pids=array();$failed=false;
$before=editorial_query('SELECT * FROM acm_lab_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
function check_lab($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function lab_http($path,$who=null,$post=null,$status=200){global $sessions;$ch=curl_init('http://127.0.0.1'.$path);usleep(180000);curl_setopt_array($ch,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>20));if($who!==null)curl_setopt($ch,CURLOPT_COOKIE,'PHPSESSID='.$sessions[$who]);if($post!==null)curl_setopt_array($ch,array(CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>is_array($post)&&isset($post['screenshot'])?$post:http_build_query($post)));$out=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);check_lab($code===$status,"$path expected $status got $code: ".substr($out,-600));check_lab(!preg_match('/Fatal error|Warning:|SQLSTATE|Uncaught /',$out),"$path has no PHP errors");return $out;}
try{
 foreach($users as $i=>$u){editorial_query("INSERT INTO users(user_id,nick,school,defunct,register_num) VALUES(?,?,?,'N',1)",array($u,$u,$prefix));$sessions[$i]='lab'.bin2hex(random_bytes(16));session_id($sessions[$i]);session_start();$_SESSION=array($OJ_NAME.'_user_id'=>$u,$OJ_NAME.'_postkey'=>$key);if($i===2)$_SESSION[$OJ_NAME.'_administrator']=true;session_write_close();}
 editorial_query('UPDATE acm_lab_settings SET recruitment_open=1 WHERE id=1');
 lab_http('/lab.php');lab_http('/lab.php?tab=mine',null,null,401);lab_http('/lab.php?tab=manage',0,null,403);lab_http('/lab.php?tab=settings',0,null,403);
 $join=array('action'=>'submit','postkey'=>$key,'name'=>'测试同学','department'=>'测试专业','grade'=>'2026','contact'=>'test@example.test','experience'=>'零基础','reason'=>'学习算法','availability'=>'每周三小时');
 $bad=$join;$bad['postkey']='bad';lab_http('/lab.php?tab=join',0,$bad,403);
 lab_http('/lab.php?tab=join',0,$join,303);$id=editorial_query('SELECT id FROM acm_lab_request WHERE user_id=?',array($users[0]))->fetchColumn();
 $join['reason']='更新申请';lab_http('/lab.php?tab=join',0,$join,303);check_lab((int)editorial_query('SELECT COUNT(*) FROM acm_lab_request WHERE user_id=?',array($users[0]))->fetchColumn()===1,'Repeated join updates original request');
 check_lab(str_contains(lab_http('/lab.php?tab=mine&id='.$id,0),'更新申请'),'Owner sees update');lab_http('/lab.php?tab=mine&id='.$id,1,null,404);lab_http('/lab.php?tab=about&id='.$id,null,null,404);
 $review=array('action'=>'review','postkey'=>$key,'status'=>'talking','reply'=>'请来沟通','admin_note'=>'PRIVATE_NOTE_'.$prefix);
 lab_http('/lab.php?tab=mine&id='.$id,0,$review,400);lab_http('/lab.php?tab=manage&id='.$id,2,$review,303);
 $own=lab_http('/lab.php?tab=mine&id='.$id,0);check_lab(str_contains($own,'请来沟通')&&!str_contains($own,'PRIVATE_NOTE_'),'Reply visible and admin note withheld');check_lab(str_contains(lab_http('/lab.php?tab=manage&id='.$id,2),'PRIVATE_NOTE_'),'Admin sees private note');
 lab_http('/lab.php?tab=settings',2,array('action'=>'settings','postkey'=>$key,'introduction'=>'实验室介绍测试','contact'=>'测试联系方式'),303);check_lab(str_contains(lab_http('/lab.php'),'暂未开放申请'),'Closed recruitment shown');lab_http('/lab.php?tab=join',1,$join,400);
 editorial_query('UPDATE acm_lab_settings SET recruitment_open=1 WHERE id=1');
 $review['status']='accepted';lab_http('/lab.php?tab=manage&id='.$id,2,$review,303);lab_http('/lab.php?tab=join',0,$join,400);
 $img=tempnam('/tmp','lab-image-');file_put_contents($img,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII='));
 $bug=array('action'=>'submit','postkey'=>$key,'kind'=>'bug','title'=>'<script>feedback</script>','url'=>'https://example.test','steps'=>'第一步','expected'=>'期望','actual'=>'实际','device'=>'测试设备','screenshot'=>new CURLFile($img,'image/png','screen.png'));
 lab_http('/lab.php?tab=feedback',0,$bug,303);$bugId=editorial_query("SELECT MAX(id) FROM acm_lab_request WHERE user_id=? AND kind='bug'",array($users[0]))->fetchColumn();
 check_lab(str_contains(lab_http('/lab.php?tab=mine&id='.$bugId,0),'&lt;script&gt;feedback&lt;/script&gt;'),'Feedback escaped');check_lab(str_contains(lab_http('/lab.php?tab=mine&id='.$bugId.'&attachment=1',0),'Content-Type: image/png'),'Owner reads private screenshot');lab_http('/lab.php?tab=mine&id='.$bugId.'&attachment=1',1,null,404);lab_http('/lab.php?tab=about&id='.$bugId.'&attachment=1',null,null,404);
 file_put_contents($img,'<svg onload="alert(1)"></svg>');$bug['screenshot']=new CURLFile($img,'image/png','fake.png');lab_http('/lab.php?tab=feedback',0,$bug,400);unlink($img);
 lab_http('/lab.php?tab=feedback',0,array('action'=>'submit','postkey'=>$key,'kind'=>'suggestion','title'=>'改善建议','description'=>'建议内容','benefit'=>'希望解决的问题'),303);
 $list=lab_http('/lab.php?tab=manage&kind=join',2);check_lab(!str_contains($list,'&lt;script&gt;feedback'),'Management filters by type');
 for($i=0;$i<21;$i++)editorial_query("INSERT INTO acm_lab_request(user_id,kind,title,details,reply,admin_note) VALUES(?,'suggestion',?,'{}','','')",array($users[1],$prefix.$i));check_lab(str_contains(lab_http('/lab.php?tab=mine',1),'下一页'),'Feedback pagination');check_lab(!str_contains(lab_http('/lab.php?tab=mine',0),$prefix.'20'),'List private to owner');
 for($i=0;$i<3;$i++){editorial_query("INSERT INTO problem(title,defunct,in_date) VALUES(?,'N',NOW())",array($prefix.$i));$pids[]=editorial_db()->lastInsertId();}
 foreach(array(array(0,0,4,'-2 years'),array(0,1,4,'-2 days'),array(0,1,4,'-1 day'),array(0,2,6,'-1 day'),array(1,0,4,'-2 years')) as $t)editorial_query('INSERT INTO solution(user_id,problem_id,result,in_date) VALUES(?,?,?,?)',array($users[$t[0]],$pids[$t[1]],$t[2],date('Y-m-d H:i:s',strtotime($t[3]))));
 editorial_query('UPDATE users SET solved=2,submit=4 WHERE user_id=?',array($users[0]));editorial_query('UPDATE users SET solved=1,submit=1 WHERE user_id=?',array($users[1]));
 $year=lab_http('/ranklist.php?prefix='.$prefix,0);$all=lab_http('/ranklist.php?range=all&prefix='.$prefix,0);
 check_lab(str_contains($year,'最近一年')&&!str_contains($year,"userinfo.php?user=".$users[1]),'Default year excludes historical-only users');
 check_lab(str_contains($all,"userinfo.php?user=".$users[1]),'All-time includes historical users');
 preg_match('/<tr>\s*<td>1<\/td>(.*?)<\/tr>/s',$year,$m);check_lab(isset($m[1])&&str_contains($m[1],"jresult=4'>1</a>")&&str_contains($m[1],"'>3</a>"),'Year counts distinct AC problems and period submissions');check_lab(str_contains($all,'&amp;range=all'),'Pagination preserves all-time range');check_lab(!str_contains($year,'下一页'),'No next page when the filtered ranking fits on one page');

}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL: '.$e->getMessage()."\n".$e->getTraceAsString()."\n");}
finally{
 editorial_query('UPDATE acm_lab_settings SET introduction=?,contact=?,recruitment_open=? WHERE id=1',array($before['introduction'],$before['contact'],$before['recruitment_open']));
 foreach($users as $u){editorial_query('DELETE FROM acm_lab_request WHERE user_id=?',array($u));editorial_query('DELETE FROM solution WHERE user_id=?',array($u));foreach(array('coin_first_ac','coin_ledger','coin_wallet') as $table)editorial_query("DELETE FROM $table WHERE user_id=?",array($u));editorial_query('DELETE FROM users WHERE user_id=?',array($u));}
 foreach($pids as $pid)editorial_query('DELETE FROM problem WHERE problem_id=?',array($pid));
 foreach($sessions as $sid){session_id($sid);session_start();session_destroy();}
}
if(!$failed) echo "PASS: $checks laboratory and ranking checks\n";
exit($failed?1:0);
