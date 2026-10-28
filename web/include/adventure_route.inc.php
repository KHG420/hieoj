<?php
require_once __DIR__.'/adventure.inc.php';
require_once __DIR__.'/transactional_db.inc.php';

function adventure_route_from_daily($daily) {
    $ids = array(intval($daily['problem1']), intval($daily['problem2']), intval($daily['problem3']));
    $problems = array();
    foreach ($ids as $id) {
        $row = oj_query('SELECT problem_id id,title,source,accepted,submit FROM problem WHERE problem_id=?', array($id))->fetch(PDO::FETCH_ASSOC);
        // A stop whose problem disappeared is kept as a placeholder so the day's
        // route stays readable and can be rebuilt, instead of failing the page.
        $problems[] = $row ?: array('id' => $id, 'title' => '', 'source' => '', 'accepted' => 0, 'submit' => 0);
    }
    return array('day' => $daily['route_date'], 'problems' => $problems, 'start' => strtotime($daily['start_time']), 'mode' => $daily['mode'], 'node' => $daily['node'], 'cursor' => intval($daily['cursor_id']));
}
function adventure_daily_route($user, $day) {
    $row = oj_query('SELECT * FROM adventure_route_daily WHERE user_id=? AND route_date=? LIMIT 1', array($user, $day))->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
// Every node exposes the problems that belong to it, so the reverse map is what
// restores the knowledge point each stored stop came from.
function adventure_build_node_map($nodes) {
    $map = array();
    foreach ($nodes as $node) {
        foreach ($node['progress']['problems'] as $problem) {
            if (!isset($map[$problem['id']])) $map[$problem['id']] = $node['name'];
        }
    }
    return $map;
}
// The three stops may come from the target knowledge point and from its
// prerequisites, so each one gets the node that owns it rather than one shared
// label. A restored stop keeps a generic label when no node owns it any more.
function adventure_tag_route($route, $nodes) {
    if (!$route) return $route;
    $map = adventure_build_node_map($nodes);
    foreach ($route['problems'] as &$problem) {
        $problem['node_name'] = isset($map[$problem['id']]) ? $map[$problem['id']] : '关联知识点';
    }
    unset($problem);
    return $route;
}
// A route stays fixed for the day, so it may only be rebuilt when one of its
// stops stopped being public practice: the day's knowledge point is kept and
// re-resolved against the current graph, because a saved node can disappear too.
function adventure_route_problems($route) {
    $ids = array();
    foreach ($route['problems'] as $p) $ids[] = intval($p['id']);
    return $ids;
}
// A route is unusable when a stop is no longer public practice or when its
// problem row is gone; both are replaced by the same controlled rebuild.
function adventure_route_broken($route, $public) {
    if (!isset($route['problems']) || count($route['problems']) !== 3) return true;
    foreach ($route['problems'] as $p) {
        if (empty($p['title']) || !isset($public[intval($p['id'])])) return true;
    }
    return false;
}
// $slug and $mode are the destination and practice mode picked on the submitting
// form while the route was unusable; empty or unknown choices keep the day's
// saved ones.
function adventure_rebuild_route($user, $today, $now, $graph, $public, $results, $daily, $slug = '', $mode = '') {
    $nodes = $graph['nodes'];
    $available = array();
    foreach ($nodes as $node) $available[$node['slug']] = true;
    $savedSlug = isset($daily['node']) && is_string($daily['node']) ? $daily['node'] : '';
    $target = isset($available[$slug]) ? $slug : (isset($available[$savedSlug]) ? $savedSlug : '');
    $targetMode = $mode === 'review' ? 'review' : ($mode === 'challenge' ? 'challenge' : ($daily['mode'] === 'review' ? 'review' : 'challenge'));
    $oldRoute = adventure_route_from_daily($daily);
    $oldIds = adventure_route_problems($oldRoute);
    $newRoute = adv_route($graph, $public, $results, $target, $targetMode);
    $newIds = adventure_route_problems(array('problems' => $newRoute));
    if (count($newIds) < 3 || array_diff($newIds, $oldIds) === array()) return false;
    $cursorId = intval(oj_query('SELECT COALESCE(MAX(solution_id),0) FROM solution WHERE user_id=?', array($user))->fetchColumn());
    // Replace only the exact route that was just read. Problems, destination,
    // mode, start time and submission cursor move together, so only ACS after
    // this rebuild count, and a concurrent rebuild makes this a no-op instead of
    // a second set.
    $replace = oj_query('UPDATE adventure_route_daily SET problem1=?,problem2=?,problem3=?,node=?,mode=?,start_time=?,cursor_id=?
        WHERE user_id=? AND route_date=? AND problem1=? AND problem2=? AND problem3=? AND cursor_id=?',
        array($newIds[0], $newIds[1], $newIds[2], $target, $targetMode, date('Y-m-d H:i:s', $now), $cursorId, $user, $today, $oldIds[0], $oldIds[1], $oldIds[2], intval($oldRoute['cursor'])));
    return $replace->rowCount() === 1;
}

// The daily route is fixed, but a stop that left public practice cannot be
// practised any more: only such a route may be rebuilt, and the POST handler
// below is the only place allowed to replace it. It is derived from the session
// before and after the POST, so an error path still renders the newest route,
// and each stop is tagged with the knowledge point it belongs to.
function adventure_route_state($state, $tab, $public, $nodes) {
    $route = $tab === 'route' && isset($state['route']) ? $state['route'] : null;
    $invalid = $route && adventure_route_broken($route, $public);
    return array(adventure_tag_route($route, $nodes), $invalid, $invalid && $nodes !== array());
}
