<?php
// CLI-only, isolated random fixtures. Run against a disposable local deployment.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array($argv[1] ?? '',array('--fixtures','worker'),true)) { fwrite(STDERR,"Usage: php editorial_test.php --fixtures\n"); exit(1); }
require __DIR__.'/../include/db_info.inc.php';
require __DIR__.'/../include/editorial.inc.php';
session_write_close();
if ($argv[1] === 'worker') {
    $spec = json_decode(base64_decode($argv[2]),true);
    try {
        if ($spec[0] === 'unlock') editorial_unlock($spec[1],$spec[2],false);
        elseif ($spec[0] === 'review') { $_SESSION[$OJ_NAME.'_administrator']=true; editorial_review($spec[1],$spec[2],'approved',''); }
        else editorial_query('UPDATE solution SET result=4 WHERE solution_id=?',array($spec[1]));
        exit(0);
    } catch (DomainException $e) { exit(2); }
    catch (Throwable $e) { fwrite(STDERR,$e->getMessage()); exit(3); }
}
$prefix = 'edtest_'.bin2hex(random_bytes(5));
$users = array($prefix.'a',$prefix.'b',$prefix.'c',$prefix.'d',$prefix.'e');
$pids = array(); $cids = array(); $sessions = array(); $failed = false; $checks = 0;
$key = bin2hex(random_bytes(16));
function ed_check($ok,$message) { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
function ed_value($sql,$args=array()) { return editorial_query($sql,$args)->fetchColumn(); }
function ed_ac($user,$pid,$result=4) { editorial_query('INSERT INTO solution(user_id,problem_id,result,in_date) VALUES(?,?,?,NOW())',array($user,$pid,$result)); return (int)editorial_db()->lastInsertId(); }
function ed_http($path,$who=null,$post=null,$expected=200,$location=null) {
    global $sessions;
    usleep(160000); // Stay below the local nginx dynamic-request limit.
    $c=curl_init('http://127.0.0.1'.$path);
    curl_setopt_array($c,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HEADER=>true));
    if ($who !== null) curl_setopt($c,CURLOPT_COOKIE,'PHPSESSID='.$sessions[$who]);
    if ($post !== null) curl_setopt_array($c,array(CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)));
    $text=curl_exec($c); $code=curl_getinfo($c,CURLINFO_HTTP_CODE); $headerSize=curl_getinfo($c,CURLINFO_HEADER_SIZE); curl_close($c);
    if ($location !== null) ed_check(preg_match('/^Location: '.preg_quote($location,'/').'\r?$/mi',substr((string)$text,0,$headerSize))===1,"$path: expected redirect to $location");
    ed_check($code===$expected,"$path: expected HTTP $expected, got $code: ".substr((string)$text,$headerSize,350));
    ed_check(!preg_match('/Fatal error|Parse error|Warning:|Uncaught /',(string)$text),"$path: PHP error");
    ed_check(stripos(substr((string)$text,0,$headerSize),'no-store')!==false,"$path: private response caching");
    return substr((string)$text,$headerSize);
}
function ed_parallel($specs) {
    $processes=array();
    foreach($specs as $spec) {
        $pipes=array();
        $p=proc_open(array(PHP_BINARY,__FILE__,'worker',base64_encode(json_encode($spec))),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
        fclose($pipes[0]); $processes[]=array($p,$pipes);
    }
    $codes=array();
    foreach($processes as list($p,$pipes)) { $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($p); ed_check($code!==3,'Concurrent database operation: '.$err); $codes[]=$code; }
    return $codes;
}
try {
    ed_check(editorial_ready(),'Schema and award triggers installed');
    foreach($users as $i=>$uid) {
        editorial_query("INSERT INTO users(user_id,nick,defunct,register_num) VALUES(?,?,'N',1)",array($uid,'测试作者'.$i));
        $sid='editorial'.bin2hex(random_bytes(16)); $sessions[$i]=$sid;
        session_id($sid); session_start(); $_SESSION=array($OJ_NAME.'_user_id'=>$uid,$OJ_NAME.'_postkey'=>$key);
        if($i===3) $_SESSION[$OJ_NAME.'_administrator']=true;
        session_write_close();
    }
    for($i=0;$i<8;$i++) {
        editorial_query("INSERT INTO problem(title,defunct,in_date) VALUES(?,'N',NOW())",array($prefix.'题目'.$i));
        $pids[]=(int)editorial_db()->lastInsertId();
    }
    foreach(array('/solutions.php','/solutions.php?tab=published','/solutions.php?page=2','/solutions.php?problem_id=0','/solutions.php?problem_id[]=1') as $route) {
        $removed=ed_http($route,null,null,302,'problemset.php');
        ed_check(!str_contains($removed,'ed-row-title') && !str_contains($removed,'problem-search'),'Unscoped public route never renders an editorial directory');
    }
    ed_http('/solutions.php',3,null,302,'problemset.php');
    ed_http('/solutions.php',0,array('action'=>'submit','postkey'=>$key),303,'problemset.php');
    $a=$users[0]; $b=$users[1]; $c=$users[2]; $admin=$users[3];
    ed_ac($a,$pids[0],6); ed_check(editorial_balance($a)===0,'Wrong answer earns no coins');
    $sid=ed_ac($a,$pids[0],0);
    editorial_query('UPDATE solution SET result=4 WHERE solution_id=?',array($sid));
    ed_check(editorial_balance($a)===2,'Judge update grants two coins');
    ed_ac($a,$pids[0]);
    editorial_query('UPDATE solution SET result=0 WHERE solution_id=?',array($sid));
    editorial_query('UPDATE solution SET result=4 WHERE solution_id=?',array($sid));
    ed_check(editorial_balance($a)===2,'Repeated AC and rejudge cannot repeat reward');
    ed_ac($a,0); ed_check(editorial_balance($a)===2,'Custom test with problem zero earns no coins');
    ed_ac($a,$pids[1]); ed_check(editorial_balance($a)===4,'Direct accepted insert grants two coins');
    editorial_query('INSERT INTO coin_first_ac(user_id,problem_id,solution_id) VALUES(?,?,0)',array($a,$pids[2]));
    ed_ac($a,$pids[2]); ed_check(editorial_balance($a)===4,'Historical AC marker prevents retroactive reward');
    $s1=ed_ac($c,$pids[7],0);$s2=ed_ac($c,$pids[7],0);
    ed_parallel(array(array('ac',$s1),array('ac',$s2)));
    ed_check(editorial_balance($c)===2,'Concurrent ACs on same problem award once');
    ed_http('/solutions.php?problem_id='.$pids[0],null,array('action'=>'submit'),401);
    ed_http('/solutions.php?problem_id='.$pids[0],0,array('action'=>'submit','title'=>'题解','content'=>'思路'),403);
    ed_http('/solutions.php?problem_id='.$pids[0],1,array('action'=>'submit','postkey'=>$key,'title'=>'题解','content'=>'思路'),400);
    ed_http('/solutions.php?tab=review',0,null,403);
    ed_http('/coins.php?tab=history',null,null,401);
    ed_http('/solutions.php?problem_id='.$pids[0],0,array('action'=>'submit','postkey'=>$key,'title'=>'','content'=>'思路'),400);
    $body='SECRET_BODY_'.$prefix."\n<script>alert('xss')</script>\nint main() { return 0; }";
    ed_http('/solutions.php?problem_id='.$pids[0],0,array('action'=>'submit','postkey'=>$key,'title'=>'前缀和 <script>','content'=>$body),303);
    $id=(int)ed_value('SELECT MAX(id) FROM problem_editorial WHERE user_id=?',array($a));
    ed_check(str_contains(ed_http('/solutions.php?id='.$id,0),'SECRET_BODY_'.$prefix),'Author reads own pending article free');
    ed_http('/solutions.php?id='.$id,1,null,404);
    ed_check(!str_contains(ed_http('/solutions.php?problem_id='.$pids[0]),'href="solutions.php?id='.$id.'"'),'Pending article not in public list');
    ed_http('/solutions.php?id='.$id.'&tab=review',0,array('action'=>'review','postkey'=>$key,'decision'=>'approved'),403);
    ed_http('/solutions.php?id='.$id.'&tab=review',3,array('action'=>'review','postkey'=>'bad','decision'=>'approved'),403);
    ed_http('/solutions.php?id='.$id.'&tab=review',3,array('action'=>'review','postkey'=>$key,'decision'=>'approved'),303);
    ed_check(editorial_balance($a)===14,'Approval pays ten coins');
    ed_http('/solutions.php?id='.$id.'&tab=review',3,array('action'=>'review','postkey'=>$key,'decision'=>'approved'),400);
    ed_check(editorial_balance($a)===14,'Repeated approval pays nothing');
    $problemList=ed_http('/solutions.php?problem_id='.$pids[0],0);
    ed_check(str_contains($problemList,'href="problem.php?id='.$pids[0].'"') && str_contains($problemList,'本题题解'),'Problem list provides scoped navigation');
    ed_check(!str_contains($problemList,'href="solutions.php"') && !str_contains($problemList,'problem-search'),'Global entry and problem search removed');
    $locked=ed_http('/solutions.php?id='.$id,1);
    ed_check(!str_contains($locked,'SECRET_BODY_') && str_contains($locked,'支付 5 金币'),'Locked response has no body');
    ed_check(!str_contains(ed_http('/solutions.php?id='.$id), 'SECRET_BODY_'),'Anonymous response has no body');
    ed_http('/solutions.php?id='.$id,1,array('action'=>'unlock','postkey'=>$key),400);
    ed_check(editorial_balance($b)===0 && !ed_value('SELECT 1 FROM problem_unlock WHERE user_id=?',array($b)),'Insufficient funds leave no unlock or debit');
    foreach(array_slice($pids,3,3) as $pid) ed_ac($b,$pid);
    ed_check(editorial_balance($b)===6,'Reader earns six coins from three distinct problems');
    ed_parallel(array(array('unlock',$b,$id),array('unlock',$b,$id),array('unlock',$b,$id)));
    ed_check(editorial_balance($b)===1,'Concurrent repeated unlock costs exactly five');
    ed_check((int)ed_value("SELECT COUNT(*) FROM coin_ledger WHERE user_id=? AND kind='problem_unlock'",array($b))===1,'One unlock ledger entry');
    $unlocked=ed_http('/solutions.php?id='.$id,1);
    ed_check(str_contains($unlocked,'href="solutions.php?problem_id='.$pids[0].'"') && str_contains($unlocked,'href="problem.php?id='.$pids[0].'"'),'Article remains attached to its problem');
    ed_check(str_contains($unlocked,'SECRET_BODY_') && str_contains($unlocked,'&lt;script&gt;') && !str_contains($unlocked,"<script>alert('xss')"),'Unlocked body renders escaped code and text');
    ed_http('/solutions.php?id='.$id,1,array('action'=>'unlock','postkey'=>$key),303,'solutions.php?id='.$id);
    ed_check(editorial_balance($b)===1,'Repeated visit and POST are free');
    ed_http('/solutions.php?id='.$id,0,array('action'=>'unlock','postkey'=>$key),303);
    ed_http('/solutions.php?id='.$id,3,array('action'=>'unlock','postkey'=>$key),303);
    ed_check(editorial_balance($a)===14 && editorial_balance($admin)===0,'Author and administrator pay nothing');
    $id2=editorial_submit($a,$pids[0],'另一种思路','独立题解');
    ed_parallel(array(array('review',$admin,$id2),array('review',$admin,$id2)));
    ed_check(editorial_balance($a)===24,'Concurrent approval grants one reward');
    ed_http('/solutions.php?id='.$id2,1,array('action'=>'unlock','postkey'=>$key),303);
    ed_check(str_contains(ed_http('/solutions.php?id='.$id2,1),'独立题解') && editorial_balance($b)===1,'One problem purchase includes later approved articles');
    $reject=editorial_submit($a,$pids[0],'需要修改的思路','待改进');
    ed_http('/solutions.php?id='.$reject.'&tab=review',3,array('action'=>'review','postkey'=>$key,'decision'=>'rejected'),400);
    ed_http('/solutions.php?id='.$reject.'&tab=review',3,array('action'=>'review','postkey'=>$key,'decision'=>'rejected','note'=>'请补充复杂度分析'),303);
    ed_check(editorial_balance($a)===24 && str_contains(ed_http('/solutions.php?id='.$reject,0),'请补充复杂度分析'),'Rejection is explained and unrewarded');
    ed_http('/solutions.php?id='.$reject,1,null,404);
    $e=$users[4]; foreach(array_slice($pids,4,3) as $pid) ed_ac($e,$pid);
    ed_parallel(array(array('unlock',$e,$id),array('unlock',$e,$id2)));
    ed_check(editorial_balance($e)===1 && (int)ed_value('SELECT COUNT(*) FROM problem_unlock WHERE user_id=?',array($e))===1,'Simultaneous different articles of one problem cost five total');
    // Two different problems competing for one wallet cannot overdraw it.
    ed_ac($c,$pids[3]); ed_ac($c,$pids[4]);
    $different=editorial_submit($a,$pids[1],'另一道题的题解','不同题目正文');
    $_SESSION[$OJ_NAME.'_administrator']=true; editorial_review($admin,$different,'approved','');
    $scoped=ed_http('/solutions.php?problem_id='.$pids[0]);
    ed_check(str_contains($scoped,'href="solutions.php?id='.$id.'"') && !str_contains($scoped,'href="solutions.php?id='.$different.'"'),'A problem list excludes other problems');
    $secondProblem=ed_http('/solutions.php?problem_id='.$pids[1]);
    ed_check(str_contains($secondProblem,'href="solutions.php?id='.$different.'"') && !str_contains($secondProblem,'href="solutions.php?id='.$id.'"'),'Other problem keeps its own editorial list');
    $codes=ed_parallel(array(array('unlock',$c,$id),array('unlock',$c,$different)));
    ed_check(count(array_filter($codes,fn($v)=>$v===0))===1 && editorial_balance($c)===1,'Concurrent different purchases cannot overdraw wallet');
    // Force a ledger uniqueness failure, proving the entire money operation rolls back.
    $rollback=editorial_submit($a,$pids[0],'事务验证','事务正文');
    editorial_query("INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES(?,'editorial_reward',?,10)",array($a,$rollback));
    $_SESSION[$OJ_NAME.'_administrator']=true;
    try { editorial_review($admin,$rollback,'approved',''); ed_check(false,'Expected approval failure'); } catch (PDOException $e) {}
    ed_check(editorial_balance($a)===34 && ed_value('SELECT status FROM problem_editorial WHERE id=?',array($rollback))==='pending','Failed reward rolls back approval and wallet');
    editorial_query("DELETE FROM coin_ledger WHERE user_id=? AND kind='editorial_reward' AND reference_id=?",array($a,$rollback));
    editorial_review($admin,$rollback,'approved','');
    // Use another author's article so the purchaser is not exempt.
    $other=editorial_submit($b,$pids[3],'读者的题解','另一位作者'); editorial_review($admin,$other,'approved','');
    editorial_query("INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES(?,'problem_unlock',?,-5)",array($a,$pids[3]));
    $before=editorial_balance($a);
    try { editorial_unlock($a,$other,false); ed_check(false,'Expected debit failure'); } catch (PDOException $e) {}
    ed_check(editorial_balance($a)===$before && !ed_value('SELECT 1 FROM problem_unlock WHERE user_id=? AND problem_id=?',array($a,$pids[3])),'Failed ledger rolls back debit and unlock');
    editorial_query("DELETE FROM coin_ledger WHERE user_id=? AND kind='problem_unlock'",array($a));
    ed_ac($a,$pids[3]);
    ed_check(str_contains(ed_http('/solutions.php?id='.$other,0),'另一位作者'),'Accepted users read other authors without buying');
    ed_http('/solutions.php?id='.$other,0,array('action'=>'unlock','postkey'=>$key),303);
    ed_check(editorial_balance($a)===$before+2 && !ed_value('SELECT 1 FROM problem_unlock WHERE user_id=? AND problem_id=?',array($a,$pids[3])),'Accepted user incurs no debit or paid unlock');
    ed_ac($b,$pids[0]);
    ed_check(editorial_balance($b)===13,'Passing an already purchased problem awards two without refunding the old purchase');
    // Hidden/private/ongoing contest content stays inaccessible, including purchased bodies.
    editorial_query("UPDATE problem SET defunct='Y' WHERE problem_id=?",array($pids[0]));
    ed_http('/solutions.php?id='.$id,1,null,404);
    ed_http('/solutions.php?problem_id='.$pids[0],0,null,404);
    ed_http('/solutions.php?problem_id='.$pids[0],0,array('action'=>'submit','postkey'=>$key,'title'=>'隐藏题','content'=>'不能发布'),404);
    editorial_query("UPDATE problem SET defunct='N' WHERE problem_id=?",array($pids[0]));
    foreach(array(1,0) as $private) {
        editorial_query("INSERT INTO contest(title,private,defunct,start_time,end_time) VALUES(?,?,'N',NOW()-INTERVAL 1 DAY,NOW()+INTERVAL 1 DAY)",array($prefix,$private));
        $cid=(int)editorial_db()->lastInsertId();$cids[]=$cid;
        editorial_query('INSERT INTO contest_problem(contest_id,problem_id,num) VALUES(?,?,0)',array($cid,$pids[0]));
        ed_http('/solutions.php?id='.$id,1,null,404);
        ed_check(!str_contains(ed_http('/solutions.php?problem_id='.$pids[0],1,null,404),'href="solutions.php?id='.$id.'"'),'Private/ongoing titles excluded from listing');
        editorial_query('DELETE FROM contest_problem WHERE contest_id=?',array($cid));
    }
    $history=ed_http('/coins.php?tab=history',1);
    ed_check(str_contains($history,'解锁本题全部题解') && str_contains($history,'-5'),'Ledger shows real spending');
    $ranking=ed_http('/coins.php',1);
    ed_check(!str_contains($ranking,'href="solutions.php"') && str_contains($ranking,'去题库练习'),'Coins navigation returns to problems instead of a global editorial list');
    preg_match('/<tbody>(.*?)<\/tbody>/s',$ranking,$rankBody);
    ed_check(isset($rankBody[1]) && strpos($rankBody[1],$a)<strpos($rankBody[1],$b),'Ranking orders current balances');
    foreach($users as $uid) ed_check(editorial_balance($uid)===(int)ed_value('SELECT COALESCE(SUM(amount),0) FROM coin_ledger WHERE user_id=?',array($uid)),'Wallet reconciles with ledger');
    // Pagination and text escaping use real records, without querying bodies for lists.
    for($i=0;$i<21;$i++) editorial_submit($a,$pids[1],'分页题解 '.$i,'分页正文');
    ed_check(str_contains(ed_http('/solutions.php?tab=mine',0),'下一页'),'Editorial pagination available');
    ed_check(str_contains(ed_http('/solutions.php?tab=mine&page=2',0),'上一页'),'Second page works');
    // New Markdown posts retain their format; legacy calls remain plain text.
    $writePath='/solutions.php?problem_id='.$pids[1].'&write=1';
    $compose=ed_http($writePath,0);
    ed_check(str_contains($compose,'id="editorial-form"') && str_contains($compose,'name="content_format" value="markdown"'),'Dedicated Markdown composer');
    ed_check(!str_contains(ed_http($writePath,1),'id="editorial-form"'),'Only accepted users can open the composer');
    $md="## 思路\n\n**前缀和** \$O(n)\$\n\n```cpp\nint main() { return 0; }\n```\n<script>bad()</script>";
    $invalid=ed_http($writePath,0,array('action'=>'submit','title'=>'保留的标题','content'=>$md,'content_format'=>'markdown','postkey'=>'expired'),403);
    ed_check(str_contains($invalid,'保留的标题') && str_contains($invalid,editorial_escape($md)),'CSRF failure retains title and body');
    ed_http($writePath,0,array('action'=>'submit','title'=>'格式验证','content'=>$md,'content_format'=>'html','postkey'=>$key),400);
    ed_http($writePath,0,array('action'=>'submit','title'=>'Markdown 验证','content'=>$md,'content_format'=>'markdown','postkey'=>$key),303);
    $mdId=(int)ed_value('SELECT MAX(id) FROM problem_editorial WHERE user_id=?',array($a));
    ed_check(ed_value('SELECT content_format FROM problem_editorial WHERE id=?',array($mdId))==='markdown','Markdown format stored');
    ed_check(ed_value('SELECT content_format FROM problem_editorial WHERE id=?',array($id))==='plain','Legacy format preserved');
    $mdBody=ed_http('/solutions.php?id='.$mdId,0);
    ed_check(str_contains($mdBody,'data-content-format="markdown"') && str_contains($mdBody,editorial_escape($md)) && !str_contains($mdBody,'<script>bad()'),'Body safely escaped before client rendering');
    ed_check(str_contains($mdBody,'data-submitted-draft='),'Successful submit signals matching draft cleanup');
    ed_check(!str_contains(ed_http('/solutions.php?id='.$mdId,0),'data-submitted-draft='),'Draft cleanup signal consumed once');
    $legacy=ed_http('/solutions.php?id='.$id,0);
    ed_check(str_contains($legacy,'data-content-format="plain"') && !str_contains($legacy,'editorial-markdown.js'),'Legacy article not reinterpreted');
    ed_http('/solutions.php?id='.$mdId,1,null,404);
    $rewardBefore=editorial_balance($a);
    ed_http('/solutions.php?id='.$mdId.'&tab=review',3,array('action'=>'review','postkey'=>$key,'decision'=>'approved'),303);
    ed_check(editorial_balance($a)===$rewardBefore+10,'Markdown approval earns the same ten coins');
    ed_check(!str_contains(ed_http('/solutions.php?id='.$mdId),editorial_escape($md)),'Markdown never leaks through anonymous paywall');
    echo "PASS: $checks checks covering judge rewards, authorization, CSRF, review, unlocks, concurrency, rollbacks, privacy, escaping, ledger and ranking.\n";
} catch(Throwable $e) { $failed=true; fwrite(STDERR,'FAIL: '.$e->getMessage()."\n".$e->getTraceAsString()."\n"); }
finally {
    foreach($users as $uid) {
        foreach(array('problem_unlock','problem_editorial','coin_ledger','coin_first_ac','coin_wallet','solution','users') as $table) editorial_query("DELETE FROM $table WHERE user_id=?",array($uid));
    }
    foreach($cids as $cid) { editorial_query('DELETE FROM contest_problem WHERE contest_id=?',array($cid));editorial_query('DELETE FROM contest WHERE contest_id=?',array($cid)); }
    foreach($pids as $pid) editorial_query('DELETE FROM problem WHERE problem_id=?',array($pid));
    foreach($sessions as $sid) { $path=ini_get('session.save_path').'/sess_'.$sid;if(is_file($path)) unlink($path); }
}
exit($failed ? 1 : 0);
