<?php
$cache_time = 0;
$OJ_CACHE_SHARE = false;
require_once './include/db_info.inc.php';
require_once './include/setlang.php';
require_once './include/adventure.inc.php';
header('Cache-Control: private, no-store');
if (isset($OJ_ON_SITE_CONTEST_ID)) {
    header('Location: contest.php?cid='.intval($OJ_ON_SITE_CONTEST_ID));
    exit;
}
$user = isset($_SESSION[$OJ_NAME.'_user_id']) ? $_SESSION[$OJ_NAME.'_user_id'] : null;
$tabs = array('enemy'=>'昨日之敌', 'route'=>'算法远征', 'shadow'=>'影子挑战', 'hunt'=>'反例猎人', 'campus'=>'校园共同挑战', 'memoir'=>'解题回忆录');
$tab = isset($_GET['tab']) && is_string($_GET['tab']) && isset($tabs[$_GET['tab']]) ? $_GET['tab'] : 'enemy';
$now = time();
$stateKey = $OJ_NAME.'_adventure_'.$user;
$state = $user && isset($_SESSION[$stateKey]) ? $_SESSION[$stateKey] : array();
$error = null;
$public = array();
foreach (adv_public_problems() as $p) $public[intval($p['id'])] = $p;
$results = in_array($tab, array('enemy','route','campus','memoir'), true) ? adv_results($user) : array();
$graph = $tab === 'route' && kg_schema_ready() ? kg_load_graph() : array('nodes'=>array(), 'edges'=>array());
$contests = array();
if ($user && $tab === 'shadow') {
    $rows = pdo_query("SELECT c.contest_id,c.title,c.start_time,c.end_time FROM contest c
        WHERE c.defunct='N' AND c.private=0 AND c.end_time<=NOW() AND c.end_time>c.start_time
        AND EXISTS (SELECT 1 FROM solution s WHERE s.contest_id=c.contest_id AND s.user_id=? AND s.in_date BETWEEN c.start_time AND c.end_time)
        ORDER BY c.end_time DESC LIMIT 50", $user);
    foreach ($rows as $c) {
        $problems = pdo_query('SELECT problem_id FROM contest_problem WHERE contest_id=? ORDER BY num', $c['contest_id']);
        $ids = array_map('intval', array_column($problems, 'problem_id'));
        if (!$ids || array_diff($ids, array_keys($public))) continue;
        $c['ids'] = array_values(array_unique($ids));
        $contests[intval($c['contest_id'])] = $c;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) { http_response_code(401); $error = '请先登录，再开始挑战。'; }
    elseif (!isset($_POST['postkey'], $_SESSION[$OJ_NAME.'_postkey']) || !is_string($_POST['postkey']) || !hash_equals($_SESSION[$OJ_NAME.'_postkey'], $_POST['postkey'])) {
        http_response_code(403); $error = '页面凭证已失效，请刷新页面后重试。';
    } else {
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        if ($action === 'route' && $tab === 'route') {
            $slug = isset($_POST['node']) && is_string($_POST['node']) ? $_POST['node'] : '';
            $mode = isset($_POST['mode']) && $_POST['mode'] === 'review' ? 'review' : 'challenge';
            $route = adv_route($graph, $public, $results, $slug, $mode);
            if (count($route) < 3) $error = '这个知识点暂时凑不齐三道可练习题，请换一个知识点或选择巩固模式。';
            else {
                $cursor = pdo_query('SELECT COALESCE(MAX(solution_id),0) FROM solution WHERE user_id=?', $user);
                $state['route'] = array('problems'=>$route, 'start'=>$now, 'mode'=>$mode, 'node'=>$slug, 'cursor'=>intval($cursor[0][0]));
            }
        } elseif ($action === 'shadow' && $tab === 'shadow') {
            $cid = isset($_POST['contest']) && is_scalar($_POST['contest']) ? intval($_POST['contest']) : 0;
            if (!isset($contests[$cid])) $error = '这场比赛暂时不能复练，请从列表中重新选择。';
            else {
                $cursor = pdo_query('SELECT COALESCE(MAX(solution_id),0) FROM solution WHERE user_id=?', $user);
                $state['shadow'] = array('contest'=>$cid, 'start'=>$now, 'cursor'=>intval($cursor[0][0]));
            }
        } elseif ($action === 'hunt' && $tab === 'hunt') {
            $slug = isset($_POST['hunt']) && is_string($_POST['hunt']) ? $_POST['hunt'] : '';
            $input = isset($_POST['input']) && is_string($_POST['input']) ? $_POST['input'] : '';
            $feedback = adv_check_hunt($slug, $input);
            $state['hunt_feedback'] = array('slug'=>$slug, 'input'=>substr($input, 0, 256), 'result'=>$feedback);
            if (!empty($feedback['hit'])) $state['hunts'][$slug] = true;
        } else { http_response_code(400); $error = '无效操作，请使用页面中的挑战按钮。'; }
        if (!$error) {
            $_SESSION[$stateKey] = $state;
            header('Location: adventure.php?tab='.$tab, true, 303);
            exit;
        }
    }
}
$enemy = null; $victories = array(); $growth = 0;
if ($tab === 'enemy' && $user) {
    $day = date('Y-m-d', $now);
    if (!isset($state['enemy']) || $state['enemy']['day'] !== $day || !isset($public[$state['enemy']['id']])) {
        $picked = adv_enemy(array_values($public), $results, $day);
        $state['enemy'] = array('day'=>$day, 'id'=>$picked ? $picked['id'] : 0);
        $_SESSION[$stateKey] = $state;
    }
    $id = $state['enemy']['id'];
    if (isset($public[$id], $results[$id])) {
        $enemy = $public[$id]; $enemy['record'] = $results[$id];
        foreach ($results as $pid=>$r) if (isset($public[$pid]) && $r['first_ac'] && $r['first_ac'] > $results[$id]['last_failure']) $growth++;
    }
    foreach ($public as $id=>$p) {
        if (isset($results[$id]) && adv_is_comeback($results[$id])) {
            $p['record'] = $results[$id]; $victories[] = $p;
        }
    }
    usort($victories, function ($a, $b) { return strcmp($b['record']['first_ac'], $a['record']['first_ac']); });
    $victories = array_slice($victories, 0, 5);
}
$route = isset($state['route']) ? $state['route'] : null;
$routeDone = array(); $routeInvalid = false;
if ($tab === 'route' && $route) {
    foreach ($route['problems'] as $p) if (!isset($public[$p['id']])) $routeInvalid = true;
    if (!$routeInvalid) {
        $rows = pdo_query('SELECT DISTINCT problem_id FROM solution WHERE user_id=? AND result=4 AND solution_id>? AND in_date>=? AND (contest_id IS NULL OR contest_id=0)', $user, $route['cursor'], date('Y-m-d H:i:s', $route['start']));
        $routeDone = array_map('intval', array_column($rows, 'problem_id'));
    }
}
$shadow = null;
if ($tab === 'shadow' && isset($state['shadow'], $contests[$state['shadow']['contest']])) {
    $c = $contests[$state['shadow']['contest']];
    $start = $state['shadow']['start']; $duration = strtotime($c['end_time']) - strtotime($c['start_time']);
    $elapsed = min($duration, max(0, $now-$start));
    $pastRows = pdo_query('SELECT problem_id,result,in_date FROM solution WHERE user_id=? AND contest_id=? AND in_date BETWEEN ? AND ? ORDER BY in_date', $user, $c['contest_id'], $c['start_time'], $c['end_time']);
    $ids = implode(',', $c['ids']);
    $currentRows = pdo_query("SELECT problem_id,result,in_date FROM solution WHERE user_id=? AND solution_id>? AND problem_id IN ($ids) AND (contest_id IS NULL OR contest_id=0) AND in_date BETWEEN ? AND ? ORDER BY in_date", $user, $state['shadow']['cursor'], date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $start+$elapsed));
    $shadow = array('contest'=>$c, 'elapsed'=>$elapsed, 'duration'=>$duration, 'finished'=>$elapsed >= $duration,
        'past'=>adv_shadow_score($pastRows, strtotime($c['start_time']), strtotime($c['start_time'])+$elapsed),
        'current'=>adv_shadow_score($currentRows, $start, $start+$elapsed),
        'timeline'=>adv_shadow_score($pastRows, strtotime($c['start_time']), strtotime($c['end_time'])));
}
$week = adv_week($now); $campus = array(); $contributions = 0; $contributors = 0; $mine = 0;
if ($tab === 'campus') {
    $campus = adv_week_problems(array_values($public), $week[0]);
    if ($campus) {
        $ids = implode(',', array_map('intval', array_column($campus, 'id')));
        $rows = pdo_query("SELECT s.user_id,s.problem_id FROM solution s WHERE s.result=4 AND s.problem_id IN ($ids)
            AND s.in_date>=? AND s.in_date<? AND (s.contest_id IS NULL OR s.contest_id=0)
            AND NOT EXISTS (SELECT 1 FROM solution prev WHERE prev.user_id=s.user_id AND prev.problem_id=s.problem_id AND prev.result=4
                AND (prev.in_date<s.in_date OR (prev.in_date=s.in_date AND prev.solution_id<s.solution_id)))
            GROUP BY s.user_id,s.problem_id", $week[0], $week[1]);
        $counts = array(); $people = array();
        foreach ($rows as $r) { $counts[$r['problem_id']] = ($counts[$r['problem_id']] ?? 0)+1; $people[$r['user_id']] = true; if ($r['user_id'] === $user) $mine++; }
        $contributions = count($rows); $contributors = count($people);
        foreach ($campus as &$p) $p['contributions'] = $counts[$p['id']] ?? 0;
        unset($p);
    }
}
$memoir = array(); $month = date('Y-m');
if ($tab === 'memoir' && $user) {
    if (isset($_GET['month']) && is_string($_GET['month']) && preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D', $_GET['month']) && $_GET['month'] <= $month) $month = $_GET['month'];
    $from = $month.'-01 00:00:00'; $to = date('Y-m-d H:i:s', strtotime($from.' +1 month'));
    $rows = pdo_query('SELECT problem_id,result,language,in_date FROM solution WHERE user_id=? AND in_date>=? AND in_date<? ORDER BY in_date,solution_id', $user, $from, $to);
    $solved = array(); $languages = array(); $days = array(); $attempts = array(); $comebacks = array();
    foreach ($rows as $r) {
        $id = intval($r['problem_id']); if (!isset($public[$id])) continue;
        $days[substr($r['in_date'], 0, 10)] = true; $attempts[$id] = ($attempts[$id] ?? 0)+1;
        if (intval($r['result']) === 4) {
            $solved[$id] = $public[$id]; $languages[$r['language']] = true;
            if (isset($results[$id]) && $results[$id]['first_ac'] >= $from && adv_is_comeback($results[$id])) $comebacks[$id] = $public[$id];
        }
    }
    $hardWon = null;
    foreach ($comebacks as $id=>$p) if ($hardWon === null || intval($results[$id]['attempts']) > intval($results[$hardWon['id']]['attempts'])) $hardWon = $p;
    $memoir = array('solved'=>count($solved), 'languages'=>count($languages), 'days'=>count($days), 'attempts'=>array_sum($attempts), 'comebacks'=>count($comebacks), 'first'=>$solved ? reset($solved) : null, 'hard_won'=>$hardWon);
}
require 'template/syzoj/adventure.php';
