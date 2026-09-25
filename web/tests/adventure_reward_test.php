<?php
// CLI-only integration fixtures for the daily Adventure coin reward.
// Real PDO/MariaDB, real sessions and real HTTP against a disposable local
// deployment; the reward core is never mocked.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array($argv[1] ?? '', array('--fixtures','worker'), true)) {
    fwrite(STDERR, "Usage: php adventure_reward_test.php --fixtures\n");
    exit(1);
}
require __DIR__.'/../include/db_info.inc.php';
require __DIR__.'/../include/adventure.inc.php';
require __DIR__.'/../include/editorial.inc.php';
$bootstrapSession = session_id();
session_write_close();
if ($bootstrapSession !== '' && is_file(ini_get('session.save_path').'/sess_'.$bootstrapSession)) {
    unlink(ini_get('session.save_path').'/sess_'.$bootstrapSession);
}
if ($argv[1] === 'worker') {
    $spec = json_decode(base64_decode($argv[2]), true);
    try {
        if (($spec['op'] ?? '') === 'claim') {
            if (($spec['sql_mode'] ?? null) !== null) {
                editorial_db()->exec('SET SESSION sql_mode='.editorial_db()->quote($spec['sql_mode']));
            }
            exit(editorial_adventure_reward($spec['user'], intval($spec['reference']), 10) ? 0 : 4);
        }
        if (($spec['op'] ?? '') === 'http') {
            $curl = curl_init('http://127.0.0.1'.$spec['path']);
            curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_COOKIE=>'PHPSESSID='.$spec['sid']));
            $body = curl_exec($curl); $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
            if ($body === false || $code !== 200 || preg_match('/Fatal error|Parse error|Warning:|Uncaught /', $body)) {
                fwrite(STDERR, ($spec['path'] ?? '')." concurrent HTTP failed with $code\n");
                exit(2);
            }
            exit(0);
        }
        fwrite(STDERR, "unknown worker op\n");
        exit(3);
    } catch (Throwable $e) {
        fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");
        exit(2);
    }
}
$prefix = 'advrw_'.bin2hex(random_bytes(5));
$key = bin2hex(random_bytes(16));
$day = date('Y-m-d');
$today = adv_reward_day(time());
$yesterdayRef = intval(date('Ymd', strtotime($day.' -1 day')));
$todayStart = $day.' 00:00:00';
$todayEnd = date('Y-m-d 00:00:00', strtotime($day.' +1 day'));
$yesterdayStart = date('Y-m-d 00:00:00', strtotime($day.' -1 day'));
$pids = array(); $cids = array(); $sessions = array(); $users = array();
$failed = false; $checks = 0;
function rw_check($ok, $message) { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
function rw_value($sql, $args = array()) { return editorial_query($sql, $args)->fetchColumn(); }
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
function rw_user($suffix) {
    global $prefix;
    $uid = $prefix.$suffix;
    editorial_query("INSERT INTO users(user_id,nick,defunct,register_num) VALUES(?,?,'N',1)", array($uid,'冒险奖励测试'));
    return $uid;
}
function rw_problem($title, $source, $defunct = 'N') {
    editorial_query('INSERT INTO problem(title,source,defunct,in_date) VALUES(?,?,?,NOW())', array($title,$source,$defunct));
    return (int)editorial_db()->lastInsertId();
}
function rw_ac($user, $pid, $inDate = null, $contest = null) {
    editorial_query('INSERT INTO solution(problem_id,user_id,result,in_date,contest_id) VALUES(?,?,4,?,?)',
        array($pid,$user,$inDate === null ? date('Y-m-d H:i:s') : $inDate,$contest));
    return (int)editorial_db()->lastInsertId();
}
function rw_wa($user, $pid, $inDate = null) {
    editorial_query('INSERT INTO solution(problem_id,user_id,result,in_date) VALUES(?,?,6,?)',
        array($pid,$user,$inDate === null ? date('Y-m-d H:i:s') : $inDate));
}
function rw_adventure($user) {
    return array_map('intval', editorial_query("SELECT COUNT(*),COALESCE(SUM(amount),0),COALESCE(MAX(reference_id),0)
        FROM coin_ledger WHERE user_id=? AND kind='adventure_reward'", array($user))->fetch(PDO::FETCH_NUM));
}
function rw_session($user, $state = null) {
    global $OJ_NAME, $key, $sessions;
    $sid = 'advrw'.bin2hex(random_bytes(16));
    session_id($sid); session_start();
    $_SESSION = array($OJ_NAME.'_user_id'=>$user, $OJ_NAME.'_postkey'=>$key);
    if ($state !== null) $_SESSION[$OJ_NAME.'_adventure_'.$user] = $state;
    session_write_close();
    @chmod(ini_get('session.save_path').'/sess_'.$sid, 0600);
    $sessions[$sid] = true;
    return $sid;
}
function rw_state($sid, $user) {
    global $OJ_NAME;
    session_id($sid); session_start();
    $state = $_SESSION[$OJ_NAME.'_adventure_'.$user] ?? null;
    session_write_close();
    return $state;
}
// $slug must be a real knowledge node, otherwise a repair has no destination to
// re-resolve and every rebuild becomes a no-op.
function rw_route_state($ids, $start, $cursor = 0, $mode = 'challenge', $slug = 'fixture') {
    $problems = array();
    foreach ($ids as $id) $problems[] = array('id'=>intval($id), 'node_name'=>'验证节点');
    return array('route'=>array('problems'=>$problems, 'start'=>intval($start), 'mode'=>$mode, 'node'=>$slug, 'cursor'=>intval($cursor)));
}
function rw_http($path, $sid = null, $post = null, $expected = 200, $location = null) {
    usleep(160000); // Stay below the local nginx dynamic-request limit.
    $curl = curl_init('http://127.0.0.1'.$path);
    curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_HEADER=>true));
    if ($sid !== null) curl_setopt($curl, CURLOPT_COOKIE, 'PHPSESSID='.$sid);
    if ($post !== null) curl_setopt_array($curl, array(CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query($post)));
    $text = curl_exec($curl); $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE); curl_close($curl);
    if ($location !== null) rw_check(preg_match('/^Location: '.preg_quote($location,'/').'\r?$/mi', substr((string)$text,0,$headerSize)) === 1, "$path: expected redirect to $location");
    rw_check($code === $expected, "$path: expected HTTP $expected, got $code: ".substr((string)$text,$headerSize,300));
    rw_check(!preg_match('/Fatal error|Parse error|Warning:|Uncaught |db error/', (string)$text), "$path: PHP/SQL error");
    return substr((string)$text, $headerSize);
}
function rw_parallel($specs, $allowFailure = false) {
    $processes = array();
    foreach ($specs as $spec) {
        $pipes = array();
        $p = proc_open(array(PHP_BINARY,__FILE__,'worker',base64_encode(json_encode($spec))), array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')), $pipes);
        fclose($pipes[0]); $processes[] = array($p,$pipes);
    }
    $codes = array();
    foreach ($processes as list($p,$pipes)) {
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $code = proc_close($p);
        if (!$allowFailure) rw_check($code !== 2, 'Concurrent database operation failed: '.$err);
        $codes[] = $code;
    }
    return $codes;
}
try {
    rw_check(editorial_ready(), 'Coin schema and award triggers installed');
    $fullKindEnum = "enum('first_ac','editorial_reward','problem_unlock','adventure_reward') NOT NULL";
    rw_check(strpos(rw_value("SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema=DATABASE()
        AND table_name='coin_ledger' AND column_name='kind'"), "'adventure_reward'") !== false, 'adventure_reward migration applied');
    // Submissions are stamped by the database clock, so the day window the web
    // process computes must agree with it.
    rw_check(abs(time() - strtotime(rw_value('SELECT NOW()'))) < 60, 'Web and database clocks agree on the reward day');

    // AC5: an installation that missed the migration must fail closed instead of
    // letting a non-strict server coerce the kind and pay anyway. Narrowing the
    // enum rewrites every existing row, so it can only run when no real user
    // holds an adventure row yet: with such rows present a strict server rejects
    // the ALTER itself. This suite never leaves its own rows behind.
    $u13 = rw_user('n'); $users[] = $u13;
    $foreignRows = (int)rw_value("SELECT COUNT(*) FROM coin_ledger WHERE kind='adventure_reward' AND user_id NOT LIKE ?", array($prefix.'%'));
    if ($foreignRows > 0) {
        fwrite(STDERR, "Adventure reward: enum-narrowing check skipped ($foreignRows existing adventure rows would be rewritten).\n");
    } else {
        editorial_db()->exec("ALTER TABLE coin_ledger MODIFY COLUMN kind enum('first_ac','editorial_reward','problem_unlock') NOT NULL");
        $codes = rw_parallel(array(array('op'=>'claim','user'=>$u13,'reference'=>$today['reference'],'sql_mode'=>'')), true);
        rw_check($codes === array(2), 'Unmigrated ledger refuses the claim even without strict mode');
        rw_check((int)rw_value('SELECT COUNT(*) FROM coin_ledger WHERE user_id=?', array($u13)) === 0
            && (int)rw_value('SELECT COALESCE(SUM(balance),0) FROM coin_wallet WHERE user_id=?', array($u13)) === 0, 'Unmigrated ledger moves no coins');
        editorial_db()->exec('ALTER TABLE coin_ledger MODIFY COLUMN kind '.$fullKindEnum);
        $codes = rw_parallel(array(array('op'=>'claim','user'=>$u13,'reference'=>$today['reference'])));
        rw_check($codes === array(0), 'The same claim succeeds once the migration is applied');
        list($count, $sum) = rw_adventure($u13);
        rw_check($count === 1 && $sum === 10 && editorial_balance($u13) === 10, 'Recovered claim pays exactly once');
    }

    // An isolated knowledge tag keeps the real route-selection flow independent
    // of whatever else lives in the shared fixture problem set.
    $tag = 'PR1验证-'.$prefix;
    $graph = kg_load_graph();
    $prerequisiteTargets = array();
    foreach ($graph['edges'] as $edge) if ($edge['relation'] === 'prerequisite') $prerequisiteTargets[$edge['target']] = true;
    $node = null;
    foreach ($graph['nodes'] as $candidate) {
        if ($candidate['tags'] || isset($prerequisiteTargets[$candidate['slug']])) continue;
        $node = $candidate;
        if (!$candidate['aliases']) break;
    }
    rw_check($node !== null, 'Isolated knowledge node available');
    editorial_query("INSERT INTO category(`content-1`,`content-2`,status,priority) VALUES('PR1验证',?,0,1)", array($prefix));
    $categoryId = (int)editorial_db()->lastInsertId();
    editorial_query('INSERT INTO knowledge_node_category(node_id,category_id) VALUES(?,?)', array($node['id'],$categoryId));
    for ($i=0;$i<6;$i++) $pids[] = rw_problem($prefix.'题'.$i, $tag);
    $hiddenPid = rw_problem($prefix.'已隐藏题', $tag, 'Y');
    editorial_query("INSERT INTO contest(title,start_time,end_time,private,defunct) VALUES(?,NOW()-INTERVAL 1 DAY,NOW()+INTERVAL 1 DAY,0,'N')", array($prefix.'比赛'));
    $cid = (int)editorial_db()->lastInsertId();
    $cids[] = $cid;

    // AC1: the real page flow pays exactly ten coins once the third fresh
    // practice AC arrives, and never before that.
    $u1 = rw_user('a'); $users[] = $u1;
    $s1 = rw_session($u1);
    $routePage = rw_http('/adventure.php?tab=route', $s1);
    rw_check(str_contains($routePage,'10 金币') && str_contains($routePage,'每天最多奖励一次'), 'Route page explains the daily rule');
    rw_http('/adventure.php?tab=route', $s1, array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key), 303);
    $routePage = rw_http('/adventure.php?tab=route', $s1);
    preg_match_all('/href="problem\.php\?id=(\d+)" target="_blank"/', $routePage, $matches);
    rw_check(count($matches[1]) === 3, 'Route offers three stops');
    $routeIds = array_map('intval', $matches[1]);
    $stored = rw_state($s1, $u1);
    rw_check(isset($stored['route']) && !isset($stored['route']['reward_id']), 'Route state carries no session-random reward id');
    list($count, $sum) = rw_adventure($u1);
    rw_check($count === 0 && $sum === 0 && editorial_balance($u1) === 0, 'Fresh route pays nothing');
    rw_ac($u1, $routeIds[0]); rw_ac($u1, $routeIds[1]);
    list($count, $sum) = rw_adventure($u1);
    rw_check($count === 0 && $sum === 0 && editorial_balance($u1) === 4, 'Two accepted stops pay only the independent +2 each');
    rw_ac($u1, $routeIds[2]);
    $routePage = rw_http('/adventure.php?tab=route', $s1);
    list($count, $sum, $reference) = rw_adventure($u1);
    rw_check($count === 1 && $sum === 10 && $reference === $today['reference'], 'Third accepted stop pays one daily adventure reward');
    rw_check(editorial_balance($u1) === 16, 'Wallet equals three first-AC rewards plus ten adventure coins');
    rw_check(str_contains($routePage,'今日冒险奖励已到账'), 'Route page confirms the payout');
    rw_http('/adventure.php?tab=route', $s1); rw_http('/adventure.php?tab=route', $s1);
    list($count, $sum) = rw_adventure($u1);
    rw_check($count === 1 && $sum === 10 && editorial_balance($u1) === 16, 'Repeated refresh never pays twice');
    rw_check(str_contains(rw_http('/adventure.php?tab=route', $s1),'今日冒险奖励已领取'), 'Route page reports the claimed daily reward');
    $history = rw_http('/coins.php?tab=history', $s1);
    rw_check(str_contains($history,'今日冒险') && str_contains($history,'+10'), 'History shows 今日冒险 +10');
    rw_check(preg_match('/今日冒险\s*<\/td>/u', $history) === 1, 'Adventure history row carries no reference label');
    rw_check(!str_contains($history,'P'.$today['reference']) && !preg_match('/今日冒险[^<]*P\d/u', $history), 'History shows no bogus problem id for the reward');
    rw_check(str_contains($history,'每天最多奖励一次'), 'History explains the once-per-day rule');
    rw_http('/adventure.php?tab=route', null);
    rw_http('/adventure.php?tab=route', null, array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key), 401);
    rw_http('/adventure.php?tab=route', $s1, array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>'wrong'), 403);
    list($count, $sum) = rw_adventure($u1);
    rw_check($count === 1 && $sum === 10 && editorial_balance($u1) === 16, 'Anonymous and CSRF failures never touch the balance');

    // AC2: independent sessions that keep posting the same day's route get the
    // same three stops back, and their submissions still leave exactly one
    // ledger row for the account and the day.
    $u2 = rw_user('b'); $users[] = $u2;
    $s2a = rw_session($u2); $s2b = rw_session($u2);
    rw_http('/adventure.php?tab=route', $s2a, array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key), 303);
    $page = rw_http('/adventure.php?tab=route', $s2a);
    preg_match_all('/href="problem\.php\?id=(\d+)" target="_blank"/', $page, $matches);
    $firstRoute = array_map('intval', $matches[1]);
    foreach ($firstRoute as $pid) rw_ac($u2, $pid);
    rw_http('/adventure.php?tab=route', $s2a);
    list($count, $sum) = rw_adventure($u2);
    rw_check($count === 1 && $sum === 10, 'First session claims the daily reward');
    $firstRow = editorial_query('SELECT problem1,problem2,problem3,start_time FROM adventure_route_daily WHERE user_id=? AND route_date=?', array($u2,$day))->fetch(PDO::FETCH_ASSOC);
    rw_http('/adventure.php?tab=route', $s2b, array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key), 303);
    $page = rw_http('/adventure.php?tab=route', $s2b);
    preg_match_all('/href="problem\.php\?id=(\d+)" target="_blank"/', $page, $matches);
    rw_check(array_map('intval', $matches[1]) === $firstRoute, 'A second session gets the same fixed daily route back');
    $secondRow = editorial_query('SELECT problem1,problem2,problem3,start_time FROM adventure_route_daily WHERE user_id=? AND route_date=?', array($u2,$day))->fetch(PDO::FETCH_ASSOC);
    rw_check($firstRow === $secondRow, 'Re-posting a usable route keeps its problems and start time');
    foreach ($firstRoute as $pid) rw_ac($u2, $pid);
    rw_http('/adventure.php?tab=route', $s2b);
    list($count, $sum) = rw_adventure($u2);
    rw_check($count === 1 && $sum === 10, 'A repeated route cannot pay a second daily reward');
    // A stop that stops being public is rebuilt from the same knowledge point,
    // and the replacement route still cannot pay the day twice.
    $u15 = rw_user('p'); $users[] = $u15;
    $s15 = rw_session($u15, rw_route_state(array($pids[0],$pids[1],$hiddenPid), time(), 0, 'challenge', $node['slug']));
    $page = rw_http('/adventure.php?tab=route', $s15);
    rw_check(str_contains($page,'已不再公开') && str_contains($page,'重新生成今日路线'), 'A broken route offers the explicit rebuild');
    rw_http('/adventure.php?tab=route', $s15, array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key), 303);
    $page = rw_http('/adventure.php?tab=route', $s15);
    preg_match_all('/href="problem\.php\?id=(\d+)" target="_blank"/', $page, $matches);
    $repairedIds = array_map('intval', $matches[1]);
    rw_check(count($repairedIds) === 3, 'The rebuilt route offers three stops');
    rw_check(!in_array($hiddenPid,$repairedIds,true), 'The unpublic stop is replaced by a usable one');
    rw_check($repairedIds !== array($pids[0],$pids[1],$hiddenPid), 'The rebuilt route is a different set of problems');
    rw_check(!str_contains($page,'已不再公开'), 'The rebuilt route is no longer invalid');
    $repairedState = rw_state($s15, $u15);
    rw_check(array_map('intval', array_column($repairedState['route']['problems'],'id')) === $repairedIds, 'The rebuilt route is stored in the session');
    rw_check(abs(time() - intval($repairedState['route']['start'])) < 60, 'The rebuild restarts the route clock');
    $repairedRow = editorial_query('SELECT problem1,problem2,problem3 FROM adventure_route_daily WHERE user_id=? AND route_date=?', array($u15,$day))->fetch(PDO::FETCH_NUM);
    rw_check(array_map('intval',$repairedRow) === $repairedIds, 'The rebuilt route is persisted');
    foreach ($repairedIds as $pid) rw_ac($u15, $pid);
    rw_http('/adventure.php?tab=route', $s15);
    list($count, $sum) = rw_adventure($u15);
    rw_check($count === 1 && $sum === 10, 'The replacement route completes exactly once for the day');
    rw_http('/adventure.php?tab=route', $s15, array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key), 303);
    rw_http('/adventure.php?tab=route', $s15);
    list($count, $sum) = rw_adventure($u15);
    rw_check($count === 1 && $sum === 10, 'Posting the rebuilt route again cannot pay a second reward');
    // An already claimed day cannot be paid again through a mid-day rebuild.
    $u16 = rw_user('q'); $users[] = $u16;
    rw_check(editorial_adventure_reward($u16, $today['reference'], 10), 'Today reward fixture created');
    $s16 = rw_session($u16, rw_route_state(array($pids[0],$pids[1],$hiddenPid), time(), 0, 'challenge', $node['slug']));
    rw_http('/adventure.php?tab=route', $s16);
    rw_http('/adventure.php?tab=route', $s16, array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key), 303);
    $page = rw_http('/adventure.php?tab=route', $s16);
    preg_match_all('/href="problem\.php\?id=(\d+)" target="_blank"/', $page, $matches);
    foreach (array_map('intval', $matches[1]) as $pid) rw_ac($u16, $pid);
    rw_http('/adventure.php?tab=route', $s16);
    list($count, $sum) = rw_adventure($u16);
    rw_check($count === 1 && $sum === 10, 'A replaced route cannot pay a second time on an already claimed day');
    // Two racing rebuilds of the same broken route both render, and only one
    // replacement is persisted.
    $u17 = rw_user('r'); $users[] = $u17;
    $s17a = rw_session($u17, rw_route_state(array($pids[0],$pids[1],$hiddenPid), time(), 0, 'challenge', $node['slug']));
    $s17b = rw_session($u17, rw_route_state(array($pids[0],$pids[1],$hiddenPid), time(), 0, 'challenge', $node['slug']));
    // Both sessions must see the same unusable route for the race to be real, so
    // the damaged row is written directly instead of waiting for promotion.
    editorial_query('INSERT INTO adventure_route_daily(user_id,route_date,node,mode,problem1,problem2,problem3,start_time,cursor_id) VALUES(?,?,?,?,?,?,?,NOW(),0)
        ON DUPLICATE KEY UPDATE problem1=VALUES(problem1),problem2=VALUES(problem2),problem3=VALUES(problem3),node=VALUES(node),mode=VALUES(mode),start_time=NOW(),cursor_id=0',
        array($u17,$day,$node['slug'],'challenge',$pids[0],$pids[1],$hiddenPid));
    $codes = rw_parallel(array(
        array('op'=>'http','path'=>'/adventure.php?tab=route','sid'=>$s17a),
        array('op'=>'http','path'=>'/adventure.php?tab=route','sid'=>$s17b),
    ));
    rw_check($codes === array(0,0), 'Both round trips render during the rebuild race');
    $rowCount = (int)rw_value('SELECT COUNT(*) FROM adventure_route_daily WHERE user_id=? AND route_date=?', array($u17,$day));
    rw_check($rowCount === 1, 'A racing rebuild keeps a single daily route');
    $racedRow = editorial_query('SELECT problem1,problem2,problem3 FROM adventure_route_daily WHERE user_id=? AND route_date=?', array($u17,$day))->fetch(PDO::FETCH_NUM);
    // Rendering the page never rewrites the day's route: replacement is a user
    // action, so concurrent loads of a broken route leave it exactly as it was.
    rw_check(array_map('intval',$racedRow) === array($pids[0],$pids[1],$hiddenPid), 'Concurrent renders never rewrite the stored route');
    // The controlled rebuild then replaces it and clears the unpublic stop.
    rw_http('/adventure.php?tab=route', $s17a, array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key), 303);
    $rebuiltRow = editorial_query('SELECT problem1,problem2,problem3 FROM adventure_route_daily WHERE user_id=? AND route_date=?', array($u17,$day))->fetch(PDO::FETCH_NUM);
    rw_check(!in_array($hiddenPid, array_map('intval',$rebuiltRow), true), 'A controlled rebuild never keeps the unpublic stop');
    rw_check((int)rw_value('SELECT COUNT(*) FROM adventure_route_daily WHERE user_id=? AND route_date=?', array($u17,$day)) === 1, 'A rebuild keeps a single daily route');
    // A stop whose problem row disappeared is reported as unavailable and is
    // rebuilt with real ids instead of failing the rest of the page.
    $u18 = rw_user('s'); $users[] = $u18;
    // Create and drop a problem to get an id that certainly does not exist.
    $missingPid = rw_problem($prefix.'已删除题', $tag);
    editorial_query('DELETE FROM problem WHERE problem_id=?', array($missingPid));
    $s18 = rw_session($u18, rw_route_state(array($missingPid,$pids[1],$pids[2]), time(), 0, 'challenge', $node['slug']));
    $page = rw_http('/adventure.php?tab=route', $s18);
    rw_check(str_contains(adv_feedback_text($page),'已不存在') && adv_route_button($page) === '重新生成今日路线', 'A deleted stop offers the rebuild');
    rw_http('/adventure.php?tab=route', $s18, array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key), 303);
    $page = rw_http('/adventure.php?tab=route', $s18);
    preg_match_all('/href="problem\.php\?id=(\d+)" target="_blank"/', $page, $matches);
    $replacedIds = array_map('intval', $matches[1]);
    rw_check(count($replacedIds) === 3 && !in_array($missingPid,$replacedIds,true), 'The deleted stop is replaced with real problems');
    rw_check(adv_feedback_text($page) === '' && adv_route_button($page) === '查看今日路线', 'The rebuilt route no longer reports a missing stop');
    $u3 = rw_user('c'); $users[] = $u3;
    $s3 = rw_session($u3, rw_route_state(array_slice($pids,0,3), time(), 0, 'challenge', $node['slug']));
    foreach (array_slice($pids,0,3) as $pid) rw_ac($u3, $pid);
    rw_http('/adventure.php?tab=route', $s3);
    list($count, $sum) = rw_adventure($u3);
    rw_check($count === 1 && $sum === 10, 'Cross-account rewards stay independent');
    $u4 = rw_user('d'); $users[] = $u4;
    $codes = rw_parallel(array(
        array('op'=>'claim','user'=>$u4,'reference'=>$today['reference']),
        array('op'=>'claim','user'=>$u4,'reference'=>$today['reference']),
    ));
    sort($codes);
    rw_check($codes === array(0,4), 'One concurrent claim wins and the other is reported as already claimed');
    list($count, $sum) = rw_adventure($u4);
    rw_check($count === 1 && $sum === 10 && editorial_balance($u4) === 10, 'Concurrent claims move ten coins once');
    // Independent sessions also prove it end to end: PHP session locking cannot
    // serialize these two requests, and the ledger key still claims once.
    $u4b = rw_user('e'); $users[] = $u4b;
    $s4a = rw_session($u4b, rw_route_state(array_slice($pids,0,3), time(), 0, 'challenge', $node['slug']));
    $s4b = rw_session($u4b, rw_route_state(array_slice($pids,0,3), time(), 0, 'challenge', $node['slug']));
    foreach (array_slice($pids,0,3) as $pid) rw_ac($u4b, $pid);
    $codes = rw_parallel(array(
        array('op'=>'http','path'=>'/adventure.php?tab=route','sid'=>$s4a),
        array('op'=>'http','path'=>'/adventure.php?tab=route','sid'=>$s4b),
    ));
    rw_check($codes === array(0,0), 'Both concurrent session requests render');
    list($count, $sum) = rw_adventure($u4b);
    rw_check($count === 1 && $sum === 10 && editorial_balance($u4b) === 16, 'Concurrent independent sessions pay ten coins once');

    // AC3: yesterday's session must not become today's persisted route.
    // A fresh daily route must be generated before today's qualifying ACs.
    $u5 = rw_user('f'); $users[] = $u5;
    $routeStart = strtotime($day.' -1 day 23:00:00');
    $preFixSession = rw_route_state(array_slice($pids,0,3), $routeStart, 0, 'challenge', $node['slug']);
    // A session preserved from before the fix still carries its random id and flag.
    $preFixSession['route']['reward_id'] = 12345;
    $preFixSession['route']['rewarded'] = true;
    $s5 = rw_session($u5, $preFixSession);
    rw_check(editorial_adventure_reward($u5, $yesterdayRef, 10), 'Yesterday reward fixture created');
    rw_ac($u5, $pids[0], date('Y-m-d H:i:s', strtotime($day.' -1 day 23:10:00')));
    rw_ac($u5, $pids[1], date('Y-m-d H:i:s', strtotime($day.' -1 day 23:20:00')));
    rw_ac($u5, $pids[2], date('Y-m-d H:i:s', strtotime($day.' -1 day 23:30:00')));
    $page = rw_http('/adventure.php?tab=route', $s5);
    list($count, $sum, $reference) = rw_adventure($u5);
    rw_check($count === 1 && $sum === 10 && $reference === $yesterdayRef, 'Yesterday accepted stops cannot claim today');
    rw_check(!str_contains($page,'远征完成') && adv_route_button($page) === '开启三题远征', 'Yesterday route is not displayed as today route');
    rw_check(empty(rw_state($s5, $u5)['route']), 'Expired session route is cleared');
    rw_check((int)rw_value('SELECT COUNT(*) FROM adventure_route_daily WHERE user_id=? AND route_date=?', array($u5,$day)) === 0, 'Yesterday route is not promoted to today');
    rw_http('/adventure.php?tab=route', $s5, array('action'=>'route','node'=>$node['slug'],'mode'=>'review','postkey'=>$key), 303);
    rw_http('/adventure.php?tab=route', $s5);
    $freshRoute = rw_state($s5, $u5)['route'];
    rw_check($freshRoute['day'] === $day && count($freshRoute['problems']) === 3, 'A fresh route is persisted for today');
    list($count, $sum) = rw_adventure($u5);
    rw_check($count === 1 && $sum === 10, 'Yesterday ACs do not complete the fresh route');
    foreach ($freshRoute['problems'] as $problem) rw_ac($u5, $problem['id']);
    rw_http('/adventure.php?tab=route', $s5);
    list($count, $sum, $reference) = rw_adventure($u5);
    rw_check($count === 2 && $sum === 20 && $reference === $today['reference'], 'Stale session reward flag cannot deny today three fresh ACs');
    rw_http('/adventure.php?tab=route', $s5);
    list($count, $sum) = rw_adventure($u5);
    rw_check($count === 2 && $sum === 20, 'Refreshing pays today only once and preserves yesterday reward');
    // Restored problem rows lack node_name; even an empty graph must label all stops.
    $activeNodes = editorial_query('SELECT id FROM knowledge_node WHERE status=1')->fetchAll(PDO::FETCH_COLUMN);
    try {
        editorial_query('UPDATE knowledge_node SET status=0 WHERE status=1');
        $page = rw_http('/adventure.php?tab=route', $s5);
        rw_check(substr_count($page, '>关联知识点</span>') === 3, 'An empty graph gives each persisted stop a fallback label');
    } finally {
        foreach ($activeNodes as $id) editorial_query('UPDATE knowledge_node SET status=1 WHERE id=?', array($id));
    }
    // Midnight edges: [00:00:00, next 00:00:00) on the server clock.
    $u6 = rw_user('g'); $users[] = $u6;
    $s6 = rw_session($u6, rw_route_state(array_slice($pids,3,3), strtotime($todayStart), 0, 'challenge', $node['slug']));
    rw_ac($u6, $pids[3], $todayStart);
    rw_ac($u6, $pids[4], date('Y-m-d H:i:s', strtotime($todayStart) + 1));
    rw_ac($u6, $pids[5], date('Y-m-d H:i:s', strtotime($yesterdayStart) + 86399));
    rw_http('/adventure.php?tab=route', $s6);
    list($count, $sum) = rw_adventure($u6);
    rw_check($count === 0 && $sum === 0, 'Yesterday 23:59:59 and midnight start stay out of the day');
    rw_ac($u6, $pids[5], $todayEnd);
    rw_http('/adventure.php?tab=route', $s6);
    list($count, $sum) = rw_adventure($u6);
    rw_check($count === 0 && $sum === 0, 'Next midnight itself is excluded from the day');
    rw_ac($u6, $pids[5], date('Y-m-d H:i:s', strtotime($todayStart) + 43200));
    rw_http('/adventure.php?tab=route', $s6);
    list($count, $sum, $reference) = rw_adventure($u6);
    rw_check($count === 1 && $sum === 10 && $reference === $today['reference'], 'Accepted solutions inside the day qualify');

    // AC1 negatives: wrong answers, contest submissions, earlier ACs, an
    // invalidated route and repeated ACs of one problem never pay.
    $u7 = rw_user('h'); $users[] = $u7;
    $s7 = rw_session($u7, rw_route_state(array_slice($pids,0,3), time(), 0, 'challenge', $node['slug']));
    foreach (array_slice($pids,0,3) as $pid) rw_wa($u7, $pid);
    $page = rw_http('/adventure.php?tab=route', $s7);
    list($count, $sum) = rw_adventure($u7);
    rw_check($count === 0 && $sum === 0 && !str_contains($page,'远征完成'), 'Wrong answers never qualify');
    $u8 = rw_user('i'); $users[] = $u8;
    $s8 = rw_session($u8, rw_route_state(array_slice($pids,0,3), time(), 0, 'challenge', $node['slug']));
    foreach (array_slice($pids,0,3) as $pid) rw_ac($u8, $pid, date('Y-m-d H:i:s'), $cid);
    rw_http('/adventure.php?tab=route', $s8);
    list($count, $sum) = rw_adventure($u8);
    rw_check($count === 0 && $sum === 0, 'Contest accepted submissions never qualify');
    $u9 = rw_user('j'); $users[] = $u9;
    foreach (array_slice($pids,0,3) as $pid) rw_ac($u9, $pid);
    $cursor = (int)rw_value('SELECT COALESCE(MAX(solution_id),0) FROM solution WHERE user_id=?', array($u9));
    $s9 = rw_session($u9, rw_route_state(array_slice($pids,0,3), time(), $cursor, 'challenge', $node['slug']));
    rw_http('/adventure.php?tab=route', $s9);
    list($count, $sum) = rw_adventure($u9);
    rw_check($count === 0 && $sum === 0, 'Accepted solutions before the route cursor never qualify');
    $u10 = rw_user('k'); $users[] = $u10;
    $s10 = rw_session($u10, rw_route_state(array($pids[0],$pids[1],$hiddenPid), time(), 0, 'challenge', $node['slug']));
    rw_ac($u10, $pids[0]); rw_ac($u10, $pids[1]); rw_ac($u10, $hiddenPid);
    $page = rw_http('/adventure.php?tab=route', $s10);
    list($count, $sum) = rw_adventure($u10);
    rw_check($count === 0 && $sum === 0 && str_contains($page,'已不再公开'), 'Hidden route problems invalidate the reward');
    $u11 = rw_user('l'); $users[] = $u11;
    $s11 = rw_session($u11, rw_route_state(array_slice($pids,0,3), time(), 0, 'challenge', $node['slug']));
    rw_ac($u11, $pids[0]); rw_ac($u11, $pids[0]); rw_ac($u11, $pids[0]); rw_ac($u11, $pids[1]);
    rw_http('/adventure.php?tab=route', $s11);
    list($count, $sum) = rw_adventure($u11);
    rw_check($count === 0 && $sum === 0, 'Repeated ACs of one or two problems never qualify');
    // The reward day is computed from the server clock, not from posted fields.
    $u12 = rw_user('m'); $users[] = $u12;
    $s12 = rw_session($u12);
    rw_http('/adventure.php?tab=route', $s12, array('action'=>'route','node'=>$node['slug'],'mode'=>'challenge','postkey'=>$key,
        'reward_id'=>'999999999','reference_id'=>'19990101','day'=>'1999-01-01'), 303);
    $page = rw_http('/adventure.php?tab=route', $s12);
    preg_match_all('/href="problem\.php\?id=(\d+)" target="_blank"/', $page, $matches);
    foreach (array_map('intval', $matches[1]) as $pid) rw_ac($u12, $pid);
    rw_http('/adventure.php?tab=route', $s12);
    list($count, $sum, $reference) = rw_adventure($u12);
    rw_check($count === 1 && $sum === 10 && $reference === $today['reference'], 'Client-posted day fields cannot choose the reward key');
    // AC5: a wallet write that cannot complete takes the ledger claim with it.
    $u14 = rw_user('o'); $users[] = $u14;
    editorial_query('INSERT INTO coin_wallet(user_id,balance) VALUES(?,18446744073709551606)', array($u14));
    $codes = rw_parallel(array(array('op'=>'claim','user'=>$u14,'reference'=>$today['reference'],'sql_mode'=>'STRICT_ALL_TABLES')), true);
    rw_check($codes === array(2), 'A failed wallet write aborts the claim');
    rw_check((int)rw_value('SELECT COUNT(*) FROM coin_ledger WHERE user_id=?', array($u14)) === 0, 'A failed wallet write leaves no ledger row');
    rw_check((string)rw_value('SELECT balance FROM coin_wallet WHERE user_id=?', array($u14)) === '18446744073709551606', 'A failed wallet write leaves the balance untouched');
    editorial_query('UPDATE coin_wallet SET balance=0 WHERE user_id=?', array($u14));
    $codes = rw_parallel(array(array('op'=>'claim','user'=>$u14,'reference'=>$today['reference'])));
    rw_check($codes === array(0), 'The claim succeeds after the wallet failure is fixed');
    list($count, $sum) = rw_adventure($u14);
    rw_check($count === 1 && $sum === 10 && editorial_balance($u14) === 10, 'Retried claim pays exactly once');

    foreach (array_unique($users) as $uid) {
        rw_check(editorial_balance($uid) === (int)rw_value('SELECT COALESCE(SUM(amount),0) FROM coin_ledger WHERE user_id=?', array($uid)), 'Wallet reconciles with ledger for '.$uid);
    }
    echo "Adventure reward: $checks checks covering daily payout, session independence, concurrency, day boundaries, negatives, history and migration safety passed.\n";
} catch (Throwable $e) {
    $failed = true;
    fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n".$e->getTraceAsString()."\n");
} finally {
    // Restore the migrated enum even when a check failed while it was narrowed.
    if (isset($fullKindEnum)) editorial_db()->exec('ALTER TABLE coin_ledger MODIFY COLUMN kind '.$fullKindEnum);
    // Leftovers from an interrupted run would block the next one, so drop this
    // suite's own fixture rows by prefix before removing the accounts. Real
    // users are never matched by the prefix and are left alone.
    foreach (array('coin_ledger','coin_first_ac','coin_wallet','solution') as $table) {
        editorial_query("DELETE FROM $table WHERE user_id LIKE ?", array($prefix.'%'));
    }
    editorial_query('DELETE FROM adventure_route_daily WHERE user_id LIKE ?', array($prefix.'%'));
    foreach (array_unique($users) as $uid) {
        foreach (array('coin_ledger','coin_first_ac','coin_wallet','solution') as $table) {
            editorial_query("DELETE FROM $table WHERE user_id=?", array($uid));
        }
        editorial_query('DELETE FROM adventure_route_daily WHERE user_id=?', array($uid));
        editorial_query('DELETE FROM users WHERE user_id=?', array($uid));
    }
    foreach ($cids as $id) editorial_query('DELETE FROM contest WHERE contest_id=?', array($id));
    foreach (array_merge($pids,array($hiddenPid ?? 0)) as $pid) editorial_query('DELETE FROM problem WHERE problem_id=?', array($pid));
    if (isset($categoryId)) {
        editorial_query('DELETE FROM knowledge_node_category WHERE category_id=?', array($categoryId));
        editorial_query('DELETE FROM category WHERE id=?', array($categoryId));
    }
    foreach (array_keys($sessions) as $sid) {
        $path = ini_get('session.save_path').'/sess_'.$sid;
        if (is_file($path)) unlink($path);
    }
    echo "Temporary reward fixtures, sessions and knowledge tag removed.\n";
}
exit($failed ? 1 : 0);
