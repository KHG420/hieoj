<?php
// Isolated local fixtures only. The queue results below are simulated, not sandbox execution.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') !== '--fixtures') { fwrite(STDERR,"Usage: php community_hunts_http_test.php --fixtures\n"); exit(1); }
require __DIR__.'/../include/db_info.inc.php';
require __DIR__.'/../include/community_hunts.inc.php';
session_write_close();
$prefix = 'hunttest_'.bin2hex(random_bytes(5));
$users = array(); $sessions = array(); $keys = array(); $checks = 0; $failed = false;
function ht_check($condition,$message) { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
function ht_request($path,$post=null,$who='author',$status=200) {
    global $sessions,$keys;
    usleep(160000); // Stay below the existing nginx 8 requests/second limit.
    $c=curl_init('http://127.0.0.1'.$path);
    curl_setopt_array($c,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15));
    if ($who) curl_setopt($c,CURLOPT_COOKIE,'PHPSESSID='.$sessions[$who]);
    if ($post !== null) {
        if (!array_key_exists('postkey',$post)) $post['postkey']=$who ? $keys[$who] : '';
        curl_setopt_array($c,array(CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)));
    }
    $body=curl_exec($c); $code=curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
    ht_check($body!==false && $code===$status,"$path expected $status, got $code: ".substr(strip_tags((string)$body),-600));
    ht_check(!preg_match('/Fatal error|Parse error|Warning:|Uncaught /',$body),"$path PHP errors");
    return $body;
}
function ht_form_path($body,$action) {
    $dom=new DOMDocument();
    libxml_use_internal_errors(true); $dom->loadHTML($body); libxml_clear_errors();
    $xp=new DOMXPath($dom);
    $forms=$xp->query('//form[input[@name="action" and @value="'.$action.'"]]');
    ht_check($forms->length===1,'One rendered '.$action.' form');
    return '/'.ltrim(html_entity_decode($forms->item(0)->getAttribute('action')), '/');
}
function ht_source($n) { return '#include <iostream>' . "\nint main(){std::cout << " . $n . ';}'; }
function ht_job_results($a,$v=13,$r=13,$b=13,$vo="VALID\n",$ro="1\n",$bo="0\n") {
    foreach (array('validator'=>array($v,$vo),'reference'=>array($r,$ro),'buggy'=>array($b,$bo)) as $role=>$spec) {
        hunt_query('UPDATE solution SET result=? WHERE solution_id=?',$spec[0],$a[$role.'_sid']);
        hunt_query('DELETE FROM runtimeinfo WHERE solution_id=?',$a[$role.'_sid']);
        if ($spec[1]!==null) hunt_query('INSERT INTO runtimeinfo(solution_id,error) VALUES(?,?)',$a[$role.'_sid'],$spec[1]);
    }
}
try {
    foreach (array('author','reader','admin') as $role) {
        $u=$prefix.'_'.$role; $users[$role]=$u;
        hunt_query("INSERT INTO users(user_id,nick,password,defunct,register_num,reg_time) VALUES(?,?,'unusable-test-password','N',1,NOW())",$u,'反例测试');
        $sid='hunt'.bin2hex(random_bytes(16)); $sessions[$role]=$sid; $keys[$role]=bin2hex(random_bytes(16));
        session_id($sid); session_start(); $_SESSION[$OJ_NAME.'_user_id']=$u; $_SESSION[$OJ_NAME.'_postkey']=$keys[$role];
        if ($role==='admin') $_SESSION[$OJ_NAME.'_administrator']=true;
        session_write_close();
    }
    ht_request('/hunt.php',null,null);
    ht_request('/hunt.php?mode=new',null,null,401);
    ht_request('/hunt.php?mode=mine',null,null,401);
    ht_request('/hunt.php?builtin=missing',null,null,404);
    ht_request('/hunt.php?mode=new',array('action'=>'save'),null,401);
    ht_request('/hunt.php?mode=new',array('action'=>'save','postkey'=>'bad'),'author',403);
    ht_request('/hunt.php?mode=new',array('action'=>'save','postkey'=>array('bad')),'author',403);
    $fields=array('action'=>'save','title'=>$prefix.' <script>alert(1)</script>','statement'=>'输入整数，输出整数。','buggy_source'=>ht_source(0),'reference_source'=>ht_source(1),'validator_source'=>'#include <iostream>' . "\nint main(){std::cout << \"VALID\";}",'language'=>'1');
    ht_request('/hunt.php?mode=new',array_merge($fields,array('language'=>'0')),'author',400);
    ht_request('/hunt.php?mode=new',array_merge($fields,array('title'=>array('bad'))),'author',400);
    ht_request('/hunt.php?mode=new',array_merge($fields,array('title'=>str_repeat('长',161))),'author',400);
    ht_request('/hunt.php?mode=new',array_merge($fields,array('reference_source'=>str_repeat('a',32001))),'author',400);
    ht_request('/hunt.php?mode=new',$fields,'author',303);
    $c=hunt_query('SELECT * FROM hunt_challenge WHERE author=? ORDER BY id DESC LIMIT 1',$users['author'])[0]; $id=intval($c['id']); $path='/hunt.php?id='.$id;
    ht_check(intval($c['version'])===1 && !$c['hidden'],'Ordinary user published immediately');
    $body=ht_request($path,null,null);
    ht_check(str_contains($body,'&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($body,'<script>alert(1)</script>'),'Title escaped');
    $authBody=ht_request($path);
    foreach(array('attempt','comment','visibility') as $action) ht_check(ht_form_path($authBody,$action)===$path,'Rendered form retains challenge ID: '.$action);
    ht_check(ht_form_path(ht_request('/hunt.php?builtin=coins'),'comment')==='/hunt.php?builtin=coins','Rendered built-in form target');
    ht_check(!str_contains($body,adv_escape($fields['reference_source'])),'Reference source not exposed on detail');
    ht_request('/hunt.php?mode=edit&id='.$id,null,'reader',403);
    ht_request('/hunt.php?mode=edit&id='.$id,array_merge($fields,array('version'=>'1')),'reader',403);
    ht_request($path,array('action'=>'visibility','hidden'=>'1','version'=>'1'),'reader',403);
    ht_request($path,array('action'=>'attempt','version'=>'1','input'=>'0'),null,401);
    ht_request($path,array('action'=>'attempt','version'=>'1','input'=>str_repeat('0',8193)),'reader',400);
    ht_request($path,array('action'=>'attempt','version'=>'1','input'=>"0\0hidden"),'reader',400);
    ht_request($path,array('action'=>'attempt','version'=>'1','input'=>''),'reader',303);
    $a=hunt_query('SELECT * FROM hunt_attempt WHERE challenge_id=? ORDER BY id DESC LIMIT 1',$id)[0];
    ht_check(intval($a['challenge_version'])===1 && $a['input_text']==='','Empty input and version snapshot');
    foreach (array('validator','reference','buggy') as $role) {
        $s=hunt_query('SELECT s.*,c.source,i.input_text,u.source user_source FROM solution s JOIN source_code c ON c.solution_id=s.solution_id JOIN source_code_user u ON u.solution_id=s.solution_id JOIN custominput i ON i.solution_id=s.solution_id WHERE s.solution_id=?',$a[$role.'_sid'])[0];
        ht_check(intval($s['result'])===0 && intval($s['problem_id'])===0 && $s['source']===$fields[$role.'_source'] && $s['source']===$s['user_source'],'Complete queue payload '.$role);
    }
    ht_check(str_contains(ht_request($path,null,'reader'),'正在排队'),'Pending queue feedback');
    ht_request($path.'&attempt='.$a['id'],null,'author',404);
    ht_job_results($a);
    ht_check(hunt_attempt_result($a)[0][0]==='hit','Different output hits');
    ht_check(str_contains(ht_request($path,null,'reader'),'命中！'),'Hit HTTP rendered');
    foreach (array(
        array(13,13,13,'VALID','1  2',"1\n2\n",'miss'),
        array(13,13,13,'VALID','','','miss'),
        array(13,13,13,'VALID','','0','hit'),
        array(13,13,13,'INVALID','1','0','invalid'),
        array(13,13,13,'VALID extra','1','0','error'),
        array(10,13,13,'VALID','1','0','error'),
        array(13,7,13,'VALID','1','0','error'),
        array(13,8,10,'VALID','1','0','error'),
        array(13,10,10,'VALID','1','0','error'),
        array(13,13,11,'VALID','1','0','error'),
        array(13,13,10,'VALID','1','0','hit'),
        array(13,13,7,'VALID','1','0','hit'),
        array(13,13,8,'VALID','1','0','hit'),
        array(13,13,9,'VALID','1','0','error'),
        array(13,13,13,'VALID',null,'0','error'),
        array(13,13,13,null,'1','0','error'),
        array(13,13,13,'VALID','1',null,'error'),
        array(13,13,0,'VALID','1','0','pending')
    ) as $case) {
        ht_job_results($a,...array_slice($case,0,6));
        ht_check(hunt_attempt_result($a)[0][0]===$case[6],'Judge truth table '.json_encode($case));
    }
    ht_job_results($a);
    ht_request('/hunt.php?mode=edit&id='.$id,array_merge($fields,array('version'=>'1','buggy_source'=>ht_source(2))),'author',303);
    ht_check(intval(hunt_query('SELECT version FROM hunt_challenge WHERE id=?',$id)[0]['version'])===2,'Edit increments version');
    ht_check(hunt_query('SELECT source FROM source_code WHERE solution_id=?',$a['buggy_sid'])[0]['source']===$fields['buggy_source'],'Edit preserves old program');
    ht_check(str_contains(ht_request($path,null,'reader'),'这是旧版本的结果'),'Old version labeled');
    ht_request('/hunt.php?mode=edit&id='.$id,array_merge($fields,array('version'=>'1')),'author',400);
    ht_request($path,array('action'=>'attempt','version'=>'1','input'=>'0'),'reader',400);
    // Per-user limit is enforced across queue submissions.
    ht_request($path,array('action'=>'attempt','version'=>'2','input'=>'0'),'reader',303);
    ht_request($path,array('action'=>'attempt','version'=>'2','input'=>'0'),'reader',303);
    ht_request($path,array('action'=>'attempt','version'=>'2','input'=>'0'),'reader',400);
    ht_request($path,array('action'=>'comment','content'=>'hello'),null,401);
    ht_request($path,array('action'=>'comment','content'=>'hello','postkey'=>'bad'),'reader',403);
    ht_request($path,array('action'=>'comment','content'=>'   '),'reader',400);
    ht_request($path,array('action'=>'comment','content'=>str_repeat('x',6001)),'reader',400);
    ht_request($path,array('action'=>'comment','content'=>'<script>alert("comment")</script>'),'reader',303);
    $comment=hunt_query('SELECT * FROM hunt_comment WHERE author=? ORDER BY id DESC LIMIT 1',$users['reader'])[0];
    ht_check(str_contains(ht_request($path),'&lt;script&gt;alert(&quot;comment&quot;)&lt;/script&gt;'),'Comment escaped');
    $invalidComment=ht_request($path,array('action'=>'comment','content'=>'   '),'reader',400);
    ht_check(str_contains($invalidComment,'&lt;script&gt;alert(&quot;comment&quot;)'),'Existing discussion remains visible on validation error');
    ht_request($path,array('action'=>'delete_comment','comment_id'=>$comment['id']),'author',403);
    ht_request('/hunt.php?builtin=maximum',array('action'=>'delete_comment','comment_id'=>$comment['id']),'reader',403);
    ht_request($path,array('action'=>'delete_comment','comment_id'=>$comment['id']),'reader',303);
    ht_check(!str_contains(ht_request($path),'&lt;script&gt;alert(&quot;comment&quot;)'),'Deleted comment hidden');
    ht_request($path,array('action'=>'comment','content'=>'moderate this'),'reader',303);
    $cid=hunt_query('SELECT id FROM hunt_comment WHERE author=? ORDER BY id DESC LIMIT 1',$users['reader'])[0]['id'];
    ht_request($path,array('action'=>'delete_comment','comment_id'=>$cid),'admin',303);
    ht_request('/hunt.php?builtin=coins',array('action'=>'comment','content'=>$prefix.' built-in discussion'),'author',303);
    ht_check(str_contains(ht_request('/hunt.php?builtin=coins'),$prefix.' built-in discussion'),'Built-in comments');
    ht_check(!str_contains(ht_request('/hunt.php?builtin=maximum'),$prefix.' built-in discussion'),'Built-in isolation');
    ht_check(str_contains(ht_request('/adventure.php?tab=hunt&hunt=coins'),'hunt.php?builtin=coins#comments'),'Built-in comment entry');
    for ($i=0;$i<22;$i++) hunt_query('INSERT INTO hunt_comment(target,author,content) VALUES(?,?,?)','custom:'.$id,$users['author'],$prefix.' page '.$i);
    ht_check(str_contains(ht_request($path),'下一页'),'Comment pagination next');
    ht_check(str_contains(ht_request($path.'&page=2'),'上一页'),'Comment pagination previous');
    ht_request($path,array('action'=>'visibility','hidden'=>'1','version'=>'2'),'admin',303);
    ht_request($path,null,null,404); ht_request($path,null,'reader',404);
    ht_check(!str_contains(ht_request('/hunt.php',null,null),adv_escape($fields['title'])),'Hidden removed from public list');
    ht_check(str_contains(ht_request('/hunt.php?mode=mine'),'已下架'),'Author can find hidden title');
    ht_request($path,array('action'=>'comment','content'=>'hidden comment'),'author',403);
    ht_request($path,array('action'=>'attempt','version'=>'3','input'=>'0'),'author',403);
    ht_request($path,array('action'=>'visibility','hidden'=>'0','version'=>'3'),'admin',403);
    ht_request($path,array('action'=>'visibility','hidden'=>'0','version'=>'3'),'author',303);
    ht_request($path,null,null);
    // Python and account status follow existing site controls.
    $python=array_merge($fields,array('title'=>$prefix.' Python','language'=>'6','buggy_source'=>'print(0)','reference_source'=>'print(1)','validator_source'=>'print("VALID")'));
    ht_request('/hunt.php?mode=new',$python,'reader',303);
    for ($i=0;$i<21;$i++) hunt_query('INSERT INTO hunt_challenge(author,title,statement,buggy_source,reference_source,validator_source,language) SELECT author,CONCAT(title,?),statement,buggy_source,reference_source,validator_source,language FROM hunt_challenge WHERE id=?',' page '.$i,$id);
    ht_check(str_contains(ht_request('/hunt.php'),'下一页'),'Community list pagination next');
    ht_check(str_contains(ht_request('/hunt.php?page=2'),'上一页'),'Community list pagination previous');
    ht_request('/hunt.php?mode=new',$fields,'author',400);
    hunt_query("UPDATE users SET defunct='Y' WHERE user_id=?",$users['reader']);
    ht_request('/hunt.php?mode=new',$python,'reader',403);

} catch (Throwable $e) { $failed=true; fwrite(STDERR,"FAIL: ".$e->getMessage()."\n"); }
finally {
    foreach ($users as $u) {
        $rows=pdo_query('SELECT solution_id FROM solution WHERE user_id=?',$u);
        foreach ($rows ?: array() as $row) foreach (array('runtimeinfo','compileinfo','custominput','source_code','source_code_user') as $table) pdo_query("DELETE FROM $table WHERE solution_id=?",$row['solution_id']);
        pdo_query('DELETE FROM solution WHERE user_id=?',$u);
        pdo_query('DELETE FROM hunt_attempt WHERE user_id=?',$u);
        pdo_query('DELETE FROM hunt_comment WHERE author=?',$u);
        pdo_query('DELETE FROM hunt_challenge WHERE author=?',$u);
        pdo_query('DELETE FROM users WHERE user_id=?',$u);
    }
    foreach ($sessions as $sid) { session_id($sid); session_start(); session_destroy(); }
}
if (!$failed) echo "PASS: $checks community HTTP/DB assertions; queue verdicts simulated, no native sandbox execution.\n";
exit($failed?1:0);
