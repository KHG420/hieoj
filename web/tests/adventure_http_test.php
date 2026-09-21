<?php
// Local integration fixtures only; never modifies existing users, problems or submissions.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') !== '--fixtures') { fwrite(STDERR, "Usage: php adventure_http_test.php --fixtures\n"); exit(1); }
require __DIR__.'/../include/db_info.inc.php';
require __DIR__.'/../include/adventure.inc.php';
session_write_close();
$uid = 'advtest_'.bin2hex(random_bytes(6));
$sid = 'advtest'.bin2hex(random_bytes(16));
$key = bin2hex(random_bytes(16));
$pids = array(); $cids = array(); $failed = false;
function check_adv($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function request_adv($path, $post=null, $auth=true, $expected=200) {
    global $sid;
    usleep(160000); // Stay below the local nginx dynamic-request limit.
    $curl = curl_init('http://127.0.0.1'.$path);
    curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15));
    if ($auth) curl_setopt($curl, CURLOPT_COOKIE, 'PHPSESSID='.$sid);
    if ($post !== null) curl_setopt_array($curl, array(CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)));
    $body = curl_exec($curl); $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    check_adv($body !== false && $code === $expected, "$path expected $expected, got $code");
    check_adv(!preg_match('/Fatal error|Parse error|Warning:|db error|Uncaught /', $body), "$path PHP/SQL error");
    return $body;
}
function adv_fixture_result($pid,$result,$date,$contest=null) {
    global $uid;
    check_adv(pdo_query('INSERT INTO solution (problem_id,user_id,result,language,in_date,contest_id) VALUES (?,?,?,0,?,?)',$pid,$uid,$result,$date,$contest) !== false,'Insert fixture submission');
}
try {
    check_adv(pdo_query('SELECT user_id FROM users WHERE user_id=?',$uid)===array(),'Unique test user');
    check_adv(pdo_query("INSERT INTO users (user_id,nick,password,defunct,register_num,reg_time) VALUES (?,?,'unusable-test-password','N',1,NOW())",$uid,'冒险功能测试')===true,'Create test user');
    session_id($sid); session_start(); $_SESSION[$OJ_NAME.'_user_id']=$uid; $_SESSION[$OJ_NAME.'_postkey']=$key; session_write_close();
    chmod(ini_get('session.save_path').'/sess_'.$sid,0600);
    $graph = kg_load_graph(); $node = null;
    foreach ($graph['nodes'] as $n) if ($n['tags']) { $node = $n; break; }
    check_adv($node !== null,'Knowledge seed available');
    for ($i=0;$i<6;$i++) {
        $title = $uid.'题'.$i.'<script>alert(1)</script>';
        check_adv(pdo_query("INSERT INTO problem (title,source,defunct,in_date) VALUES (?,?,?,NOW())",$title,$node['tags'][0]['name'],$i===5?'Y':'N')!==false,'Create problem');
        $pids[] = intval(pdo_query('SELECT LAST_INSERT_ID()')[0][0]);
    }
    foreach (array(array(1,'-2 days','-1 day'),array(0,'-1 day','+1 day'),array(0,'-2 days','-1 day'),array(0,'-3 days','-2 days')) as $i=>$spec) {
        check_adv(pdo_query('INSERT INTO contest (title,start_time,end_time,private,defunct) VALUES (?,?,?,?,?)',$uid.'比赛'.$i,date('Y-m-d H:i:s',strtotime($spec[1])),date('Y-m-d H:i:s',strtotime($spec[2])),$spec[0],'N')!==false,'Create contest');
        $cid = intval(pdo_query('SELECT LAST_INSERT_ID()')[0][0]); $cids[]=$cid;
        pdo_query('INSERT INTO contest_problem (contest_id,problem_id,num) VALUES (?,?,0)',$cid,$pids[$i>=2?0:$i+3]);
    }
    adv_fixture_result($pids[0],6,date('Y-m-d H:i:s',strtotime('-3 days')));
    adv_fixture_result($pids[5],6,date('Y-m-d H:i:s',strtotime('-3 days')));
    $publicIds = array_column(adv_public_problems(),'id');
    foreach (array(3,4,5) as $i) check_adv(!in_array($pids[$i],$publicIds),'Private/active/hidden excluded');
    $body=request_adv('/adventure.php');
    check_adv(str_contains($body,htmlspecialchars($uid.'题0',ENT_QUOTES,'UTF-8')) && !str_contains($body,'题0<script>'),'Enemy selected and escaped');
    check_adv(str_contains(request_adv('/adventure.php',null,false),'你的冒险，从登录开始'),'Anonymous empty state');
    request_adv('/adventure.php?tab=hunt',array('action'=>'hunt','hunt'=>'coins','input'=>'6'),true,403);
    request_adv('/adventure.php?tab=hunt',array('action'=>'hunt','hunt'=>'coins','input'=>'6'),false,401);
    request_adv('/adventure.php?tab=hunt',array('action'=>'hunt','hunt'=>'coins','input'=>'6','postkey'=>$key),true,303);
    check_adv(str_contains(request_adv('/adventure.php?tab=hunt'),'命中！'),'Counterexample success after redirect');
    request_adv('/adventure.php?tab=hunt',array('action'=>'hunt','hunt'=>'coins','input'=>'0','postkey'=>$key),true,303);
    check_adv(str_contains(request_adv('/adventure.php?tab=hunt'),'请输入一个 1 至 100'),'Malformed input feedback');
    adv_fixture_result($pids[0],4,date('Y-m-d H:i:s'));
    check_adv(str_contains(request_adv('/adventure.php'),'旧敌已破。'),'Enemy sticks after AC');
    request_adv('/adventure.php?tab=route',array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key),true,303);
    $body=request_adv('/adventure.php?tab=route');
    check_adv(str_contains($body,'value="'.adv_escape($node['slug']).'" selected'),'Route destination retained');
    preg_match_all('/href="problem.php\?id=(\d+)" target="_blank"/',$body,$matches);
    check_adv(count($matches[1])===3,'Three route stops');
    foreach ($matches[1] as $pid) adv_fixture_result(intval($pid),4,date('Y-m-d H:i:s'));
    check_adv(str_contains(request_adv('/adventure.php?tab=route'),'远征完成'),'Route completion from real records');
    // A visibility change invalidates an active route without exposing its saved title.
    $routeFixture = array_values(array_intersect(array_map('intval',$matches[1]),$pids));
    if ($routeFixture) {
        pdo_query("UPDATE problem SET defunct='Y' WHERE problem_id=?",$routeFixture[0]);
        check_adv(str_contains(request_adv('/adventure.php?tab=route'),'已不再公开'),'Route permission rechecked');
        pdo_query("UPDATE problem SET defunct='N' WHERE problem_id=?",$routeFixture[0]);
    }
    request_adv('/adventure.php?tab=route',array('action'=>'route','node'=>$node['slug'],'mode'=>'review','postkey'=>$key),true,303);
    check_adv(str_contains(request_adv('/adventure.php?tab=route'),'value="review" selected'),'Review mode retained');
    foreach (array(2,3) as $index) {
        $c=pdo_query('SELECT start_time FROM contest WHERE contest_id=?',$cids[$index])[0];
        adv_fixture_result($pids[0],4,date('Y-m-d H:i:s',strtotime($c['start_time'])+600),$cids[$index]);
    }
    request_adv('/adventure.php?tab=shadow',array('action'=>'shadow','contest'=>$cids[3],'postkey'=>$key),true,303);
    check_adv(str_contains(request_adv('/adventure.php?tab=shadow'),'value="'.$cids[3].'" selected'),'Non-first contest retained');
    check_adv(!str_contains(request_adv('/adventure.php?tab=shadow'),'本轮已通过'),'Same-second earlier AC excluded from shadow');
    adv_fixture_result($pids[0],4,date('Y-m-d H:i:s'));
    check_adv(str_contains(request_adv('/adventure.php?tab=shadow'),'本轮已通过'),'Shadow practice result counted');
    $body=request_adv('/adventure.php?tab=memoir');
    check_adv(str_contains($body,'保存本月回忆卡') && str_contains($body,'&lt;script&gt;'),'Memoir and escaped fixture titles');
    // Weekly counting: duplicate AC counts once, earlier AC excludes the pair.
    $week=adv_week(time()); $weekly=adv_week_problems(adv_public_problems(),$week[0]);
    check_adv(count($weekly)>=2,'Weekly fixture targets');
    pdo_query('DELETE FROM solution WHERE user_id=?',$uid);
    adv_fixture_result($weekly[0]['id'],4,date('Y-m-d H:i:s'));
    adv_fixture_result($weekly[0]['id'],4,date('Y-m-d H:i:s'));
    adv_fixture_result($weekly[1]['id'],4,date('Y-m-d H:i:s',strtotime($week[0])-1));
    adv_fixture_result($weekly[1]['id'],4,date('Y-m-d H:i:s'));
    $body=request_adv('/adventure.php?tab=campus');
    check_adv(str_contains($body,'你已贡献 1 次'),'Weekly first-AC deduplication');
    pdo_query('DELETE FROM solution WHERE user_id=? AND problem_id=?',$uid,$weekly[0]['id']);
    adv_fixture_result($weekly[0]['id'],4,date('Y-m-d H:i:s'),$cids[2]);
    adv_fixture_result($weekly[0]['id'],4,date('Y-m-d H:i:s'));
    check_adv(str_contains(request_adv('/adventure.php?tab=campus'),'你已贡献 0 次'),'Same-week contest first AC excludes later practice');
    echo "Adventure HTTP: authentication, CSRF, visibility, enemy AC, route completion, shadow, hunt, campus and memoir passed.\n";
} catch (Throwable $e) { $failed=true; fwrite(STDERR,$e->getMessage()."\n"); }
finally {
    pdo_query('DELETE FROM solution WHERE user_id=?',$uid);
    foreach ($cids as $id) { pdo_query('DELETE FROM contest_problem WHERE contest_id=?',$id); pdo_query('DELETE FROM contest WHERE contest_id=?',$id); }
    foreach ($pids as $id) pdo_query('DELETE FROM problem WHERE problem_id=?',$id);
    // The AC trigger and the route reward write money rows for this fixture user.
    foreach (array('coin_ledger','coin_first_ac','coin_wallet') as $table) pdo_query("DELETE FROM $table WHERE user_id=?",$uid);
    pdo_query('DELETE FROM users WHERE user_id=?',$uid);
    $file=ini_get('session.save_path').'/sess_'.$sid; if (is_file($file)) unlink($file);
    echo "Temporary adventure fixtures and session removed.\n";
}
exit($failed?1:0);
