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
$pids = array(); $cids = array(); $contestPids = array(); $failed = false; $extraUsers = array();
function check_adv($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function request_adv($path, $post=null, $auth=true, $expected=200, $sid = null) {
    if ($sid === null) $sid = $GLOBALS['sid'];
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
// The rendered form button label and the alert paragraph. Both phrases also
// appear in the page's own note text, so plain substring searches cannot tell a
// real warning from the explanation that documents it.
function adv_route_button($body) {
    if (preg_match('/<button class="ui primary button" type="submit">([^<]*)</u', $body, $m)) return trim($m[1]);
    return '';
}
function adv_feedback_text($body) {
    if (preg_match('/<p class="adv-feedback[^"]*"[^>]*>([^<]*)</u', $body, $m)) return trim($m[1]);
    return '';
}
// Shadow progress per problem, read from the rendered list instead of the page
// text: the note explaining the feature contains the same status words.
function adv_shadow_status($body) {
    $status = array();
    if (preg_match_all('/problem\.php\?id=(\d+)"[^>]*>.*?<span>([^<]*)</su', $body, $m, PREG_SET_ORDER)) {
        foreach ($m as $item) $status[intval($item[1])] = trim($item[2]);
    }
    return $status;
}
function adv_problem_ids($row) {
    return array(intval($row['problem1']),intval($row['problem2']),intval($row['problem3']));
}
function adv_fixture_result($pid,$result,$date,$contest=null) {
    global $uid;
    check_adv(pdo_query('INSERT INTO solution (problem_id,user_id,result,language,in_date,contest_id) VALUES (?,?,?,0,?,?)',$pid,$uid,$result,$date,$contest) !== false,'Insert fixture submission');
}
function adv_fixture_user() {
    global $extraUsers;
    $user = 'advfix_'.bin2hex(random_bytes(6));
    check_adv(pdo_query('SELECT user_id FROM users WHERE user_id=?',$user)===array(),'Unique repair test user');
    check_adv(pdo_query("INSERT INTO users (user_id,nick,password,defunct,register_num,reg_time) VALUES (?,?,'unusable-test-password','N',1,NOW())",$user,'冒险修复测试')===true,'Create repair test user');
    $extraUsers[] = $user;
    return $user;
}
function adv_fixture_result_for($user,$pid,$result,$date) {
    check_adv(pdo_query('INSERT INTO solution (problem_id,user_id,result,language,in_date,contest_id) VALUES (?,?,?,0,?,NULL)',$pid,$user,$result,$date) !== false,'Insert repair fixture submission');
}
// Seed a session with a pre-fix route so the page promotes and repairs it.
function adv_fixture_session($user, $ids, $slug) {
    global $OJ_NAME, $key;
    $session = 'advfix'.bin2hex(random_bytes(16));
    $problems = array();
    foreach ($ids as $id) $problems[] = array('id'=>intval($id));
    session_id($session); session_start();
    $_SESSION = array($OJ_NAME.'_user_id'=>$user, $OJ_NAME.'_postkey'=>$key,
        $OJ_NAME.'_adventure_'.$user=>array('route'=>array('problems'=>$problems,'start'=>time(),'cursor'=>0,'mode'=>'challenge','node'=>$slug)));
    session_write_close();
    @chmod(ini_get('session.save_path').'/sess_'.$session,0600);
    return $session;
}
function adv_fixture_state($session, $user) {
    global $OJ_NAME;
    session_id($session); session_start();
    $state = $_SESSION[$OJ_NAME.'_adventure_'.$user] ?? null;
    session_write_close();
    return $state;
}
// Drop one key from a stored fixture state so the page re-decides it.
function adv_session_unset($session, $user, $key) {
    global $OJ_NAME;
    session_id($session); session_start();
    if (isset($_SESSION[$OJ_NAME.'_adventure_'.$user])) unset($_SESSION[$OJ_NAME.'_adventure_'.$user][$key]);
    session_write_close();
}
// Write one adventure state key, standing in for a decision made earlier today.
function adv_session_set($session, $user, $key, $value) {
    global $OJ_NAME;
    session_id($session); session_start();
    if (!isset($_SESSION[$OJ_NAME.'_adventure_'.$user])) $_SESSION[$OJ_NAME.'_adventure_'.$user] = array();
    $_SESSION[$OJ_NAME.'_adventure_'.$user][$key] = $value;
    session_write_close();
}
// The shadow clock compares against "start plus elapsed wall time", so a fixture
// that submits long after starting needs a start in the past to have a window.
function adv_shadow_backdate($session, $user, $seconds) {
    global $OJ_NAME;
    session_id($session); session_start();
    if (isset($_SESSION[$OJ_NAME.'_adventure_'.$user]['shadow']['start'])) {
        $_SESSION[$OJ_NAME.'_adventure_'.$user]['shadow']['start'] -= $seconds;
    }
    session_write_close();
}
try {
    check_adv(pdo_query('SELECT user_id FROM users WHERE user_id=?',$uid)===array(),'Unique test user');
    check_adv(pdo_query("INSERT INTO users (user_id,nick,password,defunct,register_num,reg_time) VALUES (?,?,'unusable-test-password','N',1,NOW())",$uid,'冒险功能测试')===true,'Create test user');
    session_id($sid); session_start(); $_SESSION[$OJ_NAME.'_user_id']=$uid; $_SESSION[$OJ_NAME.'_postkey']=$key; session_write_close();
    chmod(ini_get('session.save_path').'/sess_'.$sid,0600);
    $graph = kg_load_graph(); $node = null; $extraCategories = array();
    // Pin the fixture to one knowledge node through a tag that only this run uses:
    // a shared database may hold other problems whose source matches a seeded
    // tag, and those would leak into the route and break every assertion about
    // it. Prefer a node without prerequisites so the pool is exactly the problems
    // created below.
    $prerequisiteTargets = array();
    foreach ($graph['edges'] as $edge) if ($edge['relation'] === 'prerequisite') $prerequisiteTargets[$edge['target']] = true;
    foreach ($graph['nodes'] as $candidate) {
        if (isset($prerequisiteTargets[$candidate['slug']])) continue;
        $node = $candidate;
        if (!$candidate['aliases']) break;
    }
    check_adv($node !== null,'Knowledge seed available');
    $publicIds = array();
    foreach (adv_public_problems() as $p) $publicIds[intval($p['id'])] = true;
    $fixtureTag = 'adv-http-'.bin2hex(random_bytes(4));
    // A problem tag is `content-1` joined with `content-2` by a dash, and the
    // fixture problems carry the bare tag as their source, so the secondary
    // label must stay empty or the knowledge map would never match them.
    pdo_query("INSERT INTO category(`content-1`,`content-2`,status,priority) VALUES(?,'',0,1)",$fixtureTag);
    $categoryId = intval($GLOBALS['dbh']->lastInsertId());
    $extraCategories[] = $categoryId;
    pdo_query('INSERT INTO knowledge_node_category(node_id,category_id) VALUES(?,?)',$node['id'],$categoryId);
    for ($i=0;$i<6;$i++) {
        $title = $uid.'题'.$i.'<script>alert(1)</script>';
        check_adv(pdo_query("INSERT INTO problem (title,source,defunct,in_date) VALUES (?,?,?,NOW())",$title,$fixtureTag,$i===5?'Y':'N')!==false,'Create problem');
        $pids[] = intval($GLOBALS['dbh']->lastInsertId());
    }
    // Four extra problems carry the contest bindings that the shadow and campus
    // sections need, so the route pool above stays free of them (a problem bound
    // to a running contest is not public practice) and each contest owns a
    // distinct problem.
    for ($i=0;$i<4;$i++) {
        check_adv(pdo_query("INSERT INTO problem (title,source,defunct,in_date) VALUES (?,?,?,NOW())",$uid.'影子'.$i,$fixtureTag,'N')!==false,'Create contest problem');
        $contestPids[] = intval($GLOBALS['dbh']->lastInsertId());
    }
    foreach (array(array(0,'-2 days','-1 day'),array(0,'-1 day','+1 day'),array(0,'-2 days','-1 day'),array(0,'-3 days','-2 days')) as $i=>$spec) {
        check_adv(pdo_query('INSERT INTO contest (title,start_time,end_time,private,defunct) VALUES (?,?,?,?,?)',$uid.'比赛'.$i,date('Y-m-d H:i:s',strtotime($spec[1])),date('Y-m-d H:i:s',strtotime($spec[2])),$spec[0],'N')!==false,'Create contest');
        $cid = intval($GLOBALS['dbh']->lastInsertId()); $cids[]=$cid;
        pdo_query('INSERT INTO contest_problem (contest_id,problem_id,num) VALUES (?,?,0)',$cid,$contestPids[$i]);
    }
    adv_fixture_result($pids[0],6,date('Y-m-d H:i:s',strtotime('-3 days')));
    adv_fixture_result($pids[5],6,date('Y-m-d H:i:s',strtotime('-3 days')));
    $publicIds = array_column(adv_public_problems(),'id');
    // Only a still-running contest hides its problems; one that has already ended
    // keeps them public.
    check_adv(!in_array($contestPids[1],$publicIds),'Running contest problem hidden ('.$contestPids[1].')');
    check_adv(in_array($contestPids[0],$publicIds) && in_array($contestPids[2],$publicIds),'Ended contest problems stay public');
    check_adv(!in_array($pids[5],$publicIds),'Hidden fixture problem excluded');
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
        $visBody = request_adv('/adventure.php?tab=route');
    check_adv(str_contains(adv_feedback_text($visBody),'已不再公开'),'Route permission rechecked');
        pdo_query("UPDATE problem SET defunct='N' WHERE problem_id=?",$routeFixture[0]);
    }
    // The daily route is fixed from the moment it is created: re-posting the
    // destination and mode it already saved must change nothing, and a route
    // whose stop left public practice is repaired in place.
    $today = date('Y-m-d');
    $before = pdo_query('SELECT problem1,problem2,problem3,start_time,node,mode FROM adventure_route_daily WHERE user_id=? AND route_date=?',$uid,$today)[0];
    request_adv('/adventure.php?tab=route',array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key),true,303);
    $after = pdo_query('SELECT problem1,problem2,problem3,start_time,node,mode FROM adventure_route_daily WHERE user_id=? AND route_date=?',$uid,$today)[0];
    check_adv($before === $after,'A usable daily route is never regenerated');
    $body = request_adv('/adventure.php?tab=route');
    check_adv(adv_route_button($body) === '查看今日路线','A usable route offers no rebuild action');
    // The fixed route ignores the destination and mode of later submissions.
    $otherNode = null;
    foreach ($graph['nodes'] as $n) if ($n['slug'] !== $node['slug']) { $otherNode = $n; break; }
    if ($otherNode) {
        request_adv('/adventure.php?tab=route',array('action'=>'route','node'=>$otherNode['slug'],'mode'=>'review','postkey'=>$key),true,303);
        $still = pdo_query('SELECT problem1,problem2,problem3,node,mode FROM adventure_route_daily WHERE user_id=? AND route_date=?',$uid,$today)[0];
        check_adv(adv_problem_ids($still)===adv_problem_ids($before) && $still['node']===$node['slug'] && $still['mode']==='challenge','A fixed route ignores a later destination and mode');
    }
    $fixUser = adv_fixture_user();
    $oldIds = adv_problem_ids($before);
    foreach ($oldIds as $pid) adv_fixture_result_for($fixUser,$pid,4,date('Y-m-d H:i:s'));
    $fixSession = adv_fixture_session($fixUser,$oldIds,$node['slug']);
    pdo_query("UPDATE problem SET defunct='Y' WHERE problem_id=?",$oldIds[0]);
    $body = request_adv('/adventure.php?tab=route',null,true,200,$fixSession);
    check_adv(str_contains(adv_feedback_text($body),'已不再公开') && adv_route_button($body) === '重新生成今日路线','A broken route asks for an explicit rebuild');
    check_adv(str_contains($body,adv_escape($node['slug'])),'The unusable route keeps its destination');
    request_adv('/adventure.php?tab=route',array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key),true,303,$fixSession);
    $body = request_adv('/adventure.php?tab=route',null,true,200,$fixSession);
    check_adv(adv_feedback_text($body) === '' && adv_route_button($body) === '查看今日路线','The rebuilt route is usable again');
    preg_match_all('/href="problem.php\?id=(\d+)" target="_blank"/',$body,$rebuilt);
    $newIds = array_map('intval',$rebuilt[1]);
    check_adv(count($newIds)===3,'A rebuilt route offers three stops');
    check_adv(!in_array($oldIds[0],$newIds,true),'The unpublic stop is replaced');
    check_adv($newIds!==$oldIds,'The rebuilt route is a different set of problems');
    $fixedRow = pdo_query('SELECT problem1,problem2,problem3,start_time,node,mode,cursor_id FROM adventure_route_daily WHERE user_id=? AND route_date=?',$fixUser,$today)[0];
    check_adv(adv_problem_ids($fixedRow)===$newIds,'The rebuilt route is persisted');
    check_adv($fixedRow['node']===$node['slug'] && $fixedRow['mode']==='challenge','The rebuild keeps the destination and mode it was saved with');
    $routeState = adv_fixture_state($fixSession,$fixUser);
    check_adv(array_map('intval',array_column($routeState['route']['problems'],'id'))===$newIds,'The rebuilt route is stored in the session');
    check_adv(strtotime($fixedRow['start_time'])>=strtotime($before['start_time']),'The rebuild restarts the route clock');
    // A restored route keeps the knowledge point of every stop in the output.
    $body = request_adv('/adventure.php?tab=route');
    check_adv(str_contains($body,adv_escape($node['name'])),'Restored route stops carry their knowledge point');
    // A stop whose problem row is gone is reported as unavailable and rebuilt.
    $goneUser = adv_fixture_user();
    // Create and drop a problem to get an id that certainly does not exist.
    pdo_query("INSERT INTO problem (title,source,defunct,in_date) VALUES ('advfix gone','','Y',NOW())");
    $goneId = intval($GLOBALS['dbh']->lastInsertId());
    pdo_query('DELETE FROM problem WHERE problem_id=?',$goneId);
    $goneIds = $oldIds; $goneIds[0] = $goneId;
    $goneSession = adv_fixture_session($goneUser,$goneIds,$node['slug']);
    adv_fixture_result_for($goneUser,$oldIds[1],4,date('Y-m-d H:i:s'));
    $body = request_adv('/adventure.php?tab=route',null,true,200,$goneSession);
    check_adv(str_contains(adv_feedback_text($body),'已不存在') && adv_route_button($body) === '重新生成今日路线','A deleted stop asks for the rebuild');
    // The gone stop exposes no title and no link, only the warning above.
    check_adv(!str_contains($body,'problem.php?id='.$goneId),'The deleted stop exposes no problem link');
    request_adv('/adventure.php?tab=route',array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key),true,303,$goneSession);
    $body = request_adv('/adventure.php?tab=route',null,true,200,$goneSession);
    check_adv(adv_feedback_text($body) === '' && !str_contains($body,'已不可用'),'The rebuilt route has no unavailable stop left');
    preg_match_all('/href="problem.php\?id=(\d+)" target="_blank"/',$body,$reset);
    check_adv(count($reset[1])===3 && !in_array($goneIds[0],array_map('intval',$reset[1]),true),'The deleted stop is replaced');
    // A broken route refresh must not block another tab from saving its state.
    $badUser = adv_fixture_user();
    pdo_query('INSERT INTO adventure_route_daily (user_id,route_date,node,mode,problem1,problem2,problem3,start_time,cursor_id) VALUES (?,?,?,?,?,?,?,?,0)',$badUser,date('Y-m-d'),$node['slug'],'challenge',$goneId,$goneId,$goneId,date('Y-m-d H:i:s'));
    $badSession = adv_fixture_session($badUser,array($goneIds[0],$goneIds[0],$goneIds[0]),$node['slug']);
    request_adv('/adventure.php?tab=hunt',array('action'=>'hunt','hunt'=>'coins','input'=>'6','postkey'=>$key),true,303,$badSession);
    $badState = adv_fixture_state($badSession,$badUser);
    check_adv(isset($badState['hunts']['coins']),'Another tab saves its state while the daily route is broken');
    check_adv(str_contains(request_adv('/adventure.php?tab=hunt',null,true,200,$badSession),'命中！'),'Counterexample hit survives a broken daily route');
    pdo_query("UPDATE problem SET defunct='N' WHERE problem_id=?",$oldIds[0]);
    foreach (array(2,3) as $index) {
        $c=pdo_query('SELECT start_time FROM contest WHERE contest_id=?',$cids[$index])[0];
        adv_fixture_result($contestPids[3],4,date('Y-m-d H:i:s',strtotime($c['start_time'])+600),$cids[$index]);
    }
    request_adv('/adventure.php?tab=shadow',array('action'=>'shadow','contest'=>$cids[3],'postkey'=>$key),true,303);
    // Give the shadow clock a window wide enough for the submissions below.
    adv_shadow_backdate($sid,$uid,300);
    $shadowBefore = request_adv('/adventure.php?tab=shadow');
    check_adv(str_contains($shadowBefore,'value="'.$cids[3].'" selected'),'Non-first contest retained');
    $statusBefore = adv_shadow_status($shadowBefore);
    check_adv(!isset($statusBefore[$contestPids[3]]) || !str_contains($statusBefore[$contestPids[3]],'本轮已通过'),'Same-second earlier AC excluded from shadow');
    adv_fixture_result($contestPids[3],4,date('Y-m-d H:i:s',adv_fixture_state($sid,$uid)['shadow']['start'] + 5));
    $statusAfter = adv_shadow_status(request_adv('/adventure.php?tab=shadow'));
    check_adv(isset($statusAfter[$contestPids[3]]) && str_contains($statusAfter[$contestPids[3]],'本轮已通过'),'Shadow practice result counted');
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
    // Enemy selection runs last: the page persists one pick for the day, so the
    // other prior failures are hidden first and the pool is narrowed to three
    // problems — exactly one of which is still open — before the target is
    // answered.
    pdo_query("UPDATE problem SET defunct='Y' WHERE problem_id=?",$pids[0]);
    foreach (array(1,2) as $index) pdo_query("UPDATE problem SET defunct='Y' WHERE problem_id=?",$pids[$index]);
    pdo_query('DELETE FROM coin_ledger WHERE user_id=?',$uid);
    $enemyTarget = $contestPids[2];
    pdo_query('DELETE FROM solution WHERE user_id=? AND problem_id=?',$uid,$enemyTarget);
    adv_fixture_result($enemyTarget,6,date('Y-m-d H:i:s',strtotime('-4 days')));
    adv_session_set($sid,$uid,'enemy',array('day'=>date('Y-m-d'),'id'=>$enemyTarget));
    adv_fixture_result($enemyTarget,4,date('Y-m-d H:i:s'));
    $enemyPage = request_adv('/adventure.php');
    check_adv(str_contains($enemyPage,'旧敌已破。'),'Enemy sticks after AC');
    echo "Adventure HTTP: authentication, CSRF, visibility, enemy AC, route completion, shadow, hunt, campus and memoir passed.\n";
} catch (Throwable $e) { $failed=true; fwrite(STDERR,$e->getMessage()."\n"); }
finally {
    pdo_query('DELETE FROM solution WHERE user_id=?',$uid);
    foreach ($cids as $id) { pdo_query('DELETE FROM contest_problem WHERE contest_id=?',$id); pdo_query('DELETE FROM contest WHERE contest_id=?',$id); }
    foreach ($pids as $id) pdo_query('DELETE FROM problem WHERE problem_id=?',$id);
    // The AC trigger and the route reward write money rows for this fixture user.
    foreach (array('coin_ledger','coin_first_ac','coin_wallet') as $table) pdo_query("DELETE FROM $table WHERE user_id=?",$uid);
    pdo_query('DELETE FROM adventure_route_daily WHERE user_id=?',$uid);
    pdo_query('DELETE FROM users WHERE user_id=?',$uid);
    $file=ini_get('session.save_path').'/sess_'.$sid; if (is_file($file)) unlink($file);
    foreach ($extraUsers as $user) {
        foreach (array('coin_ledger','coin_first_ac','coin_wallet','solution') as $table) pdo_query("DELETE FROM $table WHERE user_id=?",$user);
        pdo_query('DELETE FROM adventure_route_daily WHERE user_id=?',$user);
        pdo_query('DELETE FROM users WHERE user_id=?',$user);
    }
    foreach ($extraCategories as $category) {
        pdo_query('DELETE FROM knowledge_node_category WHERE category_id=?',$category);
        pdo_query('DELETE FROM category WHERE id=?',$category);
    }
    echo "Temporary adventure fixtures and session removed.\n";
}
exit($failed?1:0);
