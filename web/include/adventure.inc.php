<?php
require_once __DIR__.'/knowledge_graph.inc.php';

function adv_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Adventure is always a public practice surface, even for administrators.
function adv_public_problems() {
    return pdo_query("SELECT p.problem_id id,p.title,p.source,p.accepted,p.submit FROM problem p
        WHERE p.defunct='N' AND NOT EXISTS (
            SELECT 1 FROM contest_problem cp JOIN contest c ON c.contest_id=cp.contest_id
            WHERE cp.problem_id=p.problem_id AND (c.private=1 OR (c.defunct='N' AND c.end_time>NOW()))
        ) ORDER BY p.problem_id");
}

function adv_results($user) {
    if (!$user) return array();
    $rows = pdo_query("SELECT problem_id,COUNT(*) attempts,MAX(result=4) accepted,
        MIN(in_date) first_attempt,MAX(in_date) last_attempt,
        MIN(CASE WHEN result=4 THEN in_date END) first_ac,
        MIN(CASE WHEN result=4 THEN solution_id END) first_ac_id,
        MIN(CASE WHEN result BETWEEN 5 AND 11 THEN in_date END) first_failure,
        MIN(CASE WHEN result BETWEEN 5 AND 11 THEN solution_id END) first_failure_id,
        MAX(CASE WHEN result BETWEEN 5 AND 11 THEN in_date END) last_failure
        FROM solution WHERE user_id=? AND problem_id>0 GROUP BY problem_id", $user);
    $result = array();
    foreach ($rows as $row) $result[intval($row['problem_id'])] = $row;
    return $result;
}

function adv_is_comeback($record) {
    return !empty($record['first_ac']) && !empty($record['first_failure']) &&
        ($record['first_failure'] < $record['first_ac'] ||
        ($record['first_failure'] === $record['first_ac'] && $record['first_failure_id'] < $record['first_ac_id']));
}

function adv_enemy($problems, $results, $day) {
    $candidates = array();
    foreach ($problems as $p) {
        $r = isset($results[$p['id']]) ? $results[$p['id']] : null;
        if ($r && !$r['accepted'] && $r['last_failure'] && substr($r['last_failure'], 0, 10) < $day) $candidates[] = $p;
    }
    usort($candidates, function ($a, $b) use ($day) {
        return strcmp(hash('sha256', $day.':'.$a['id']), hash('sha256', $day.':'.$b['id']));
    });
    return $candidates ? $candidates[0] : null;
}

function adv_route($graph, $public, $results, $slug, $mode) {
    $nodes = array();
    foreach ($graph['nodes'] as $node) $nodes[$node['slug']] = $node;
    if (!isset($nodes[$slug])) return array();
    $slugs = array($slug);
    foreach ($graph['edges'] as $edge) {
        if ($edge['relation'] === 'prerequisite' && $edge['target'] === $slug) array_unshift($slugs, $edge['source']);
    }
    $pool = array();
    foreach (array_unique($slugs) as $nodeSlug) {
        foreach ($nodes[$nodeSlug]['progress']['problems'] as $p) {
            $id = $p['id'];
            if (!isset($public[$id]) || ($mode === 'challenge' && !empty($results[$id]['accepted']))) continue;
            $p['node_name'] = $nodes[$nodeSlug]['name'];
            $pool[$id] = $p;
        }
    }
    $pool = array_values($pool);
    usort($pool, function ($a, $b) use ($mode, $results) {
        $tiers = array('easy'=>0, 'medium'=>1, 'hard'=>2);
        $rankA = $mode === 'review' && empty($results[$a['id']]['accepted']) ? 1 : 0;
        $rankB = $mode === 'review' && empty($results[$b['id']]['accepted']) ? 1 : 0;
        return ($rankA <=> $rankB) ?: ($tiers[$a['difficulty']] <=> $tiers[$b['difficulty']]) ?: ($a['id'] <=> $b['id']);
    });
    // Reserve a stop for the selected knowledge point, even when its prerequisites have many problems.
    $targetIds = array_column($nodes[$slug]['progress']['problems'], 'id');
    $target = null;
    foreach ($pool as $p) if (in_array($p['id'], $targetIds)) { $target = $p; break; }
    if (!$target) return array();
    $route = array();
    foreach ($pool as $p) {
        if ($p['id'] !== $target['id'] && count($route) < 2) $route[] = $p;
    }
    $route[] = $target;
    return $route;
}

function adv_week($now) {
    $start = strtotime('monday this week', $now);
    return array(date('Y-m-d 00:00:00', $start), date('Y-m-d 00:00:00', strtotime('+7 days', $start)));
}

// Adventure coins belong to the server's own calendar day: the reference makes
// the ledger's unique key claim once per user per day, and the half-open window
// [00:00:00, next 00:00:00) decides which accepted solutions count.
function adv_reward_day($now) {
    $day = date('Y-m-d', $now);
    return array(
        'reference' => intval(date('Ymd', $now)),
        'start' => $day.' 00:00:00',
        'end' => date('Y-m-d', strtotime($day.' +1 day')).' 00:00:00',
    );
}

function adv_week_problems($problems, $week) {
    usort($problems, function ($a, $b) use ($week) {
        return strcmp(hash('sha256', $week.':'.$a['id']), hash('sha256', $week.':'.$b['id']));
    });
    return array_slice($problems, 0, 5);
}

function adv_hunts() {
    return array(
        'maximum' => array('title'=>'被零骗过的最大值', 'rule'=>'输入 1 至 20 个整数，用空格分隔，每个数在 -1000 至 1000 之间。程序应输出最大值。',
            'code'=>"int best = 0;\nfor (int x : a) {\n    if (x > best) best = x;\n}\ncout << best;", 'reason'=>'最大值初始化为 0，遗漏了所有元素均为负数的情况。'),
        'search' => array('title'=>'消失的最后一个位置', 'rule'=>'第一项是查找目标，其余是 1 至 20 个非递减排列的整数，均在 -1000 至 1000 之间。存在目标应输出 1，否则输出 0。',
            'code'=>"int l = 0, r = a.size() - 1;\nbool found = false;\nwhile (l < r) {\n    int m = (l + r) / 2;\n    if (a[m] == target) { found = true; break; }\n    if (a[m] < target) l = m + 1;\n    else r = m - 1;\n}\ncout << found;", 'reason'=>'循环在 l == r 时结束，却没有检查最后一个候选位置。'),
        'coins' => array('title'=>'贪心找零的陷阱', 'rule'=>'输入一个 1 至 100 的整数金额。硬币面值为 1、3、4，数量不限，程序应输出最少硬币数。',
            'code'=>"int count = 0;\nfor (int coin : {4, 3, 1}) {\n    count += amount / coin;\n    amount %= coin;\n}\ncout << count;", 'reason'=>'优先选最大面值不保证硬币数量最少，例如两个 3 可能优于 4 加两个 1。'),
    );
}

function adv_check_hunt($slug, $input) {
    $hunts = adv_hunts();
    if (!isset($hunts[$slug])) return array('error'=>'请选择一个有效挑战。');
    if (strlen($input) > 256 || !preg_match('/^\s*-?\d+(?:\s+-?\d+)*\s*$/D', $input)) return array('error'=>'请按题目要求输入整数，用空格或换行分隔。');
    $values = array_map('intval', preg_split('/\s+/', trim($input)));
    if (count($values) > 21 || min($values) < -1000 || max($values) > 1000) return array('error'=>'数字数量或取值超出题目范围，请检查后重试。');
    if ($slug === 'maximum') {
        if (count($values) > 20) return array('error'=>'最多输入 20 个整数。');
        $expected = max($values); $actual = max(0, $expected);
    } elseif ($slug === 'search') {
        if (count($values) < 2) return array('error'=>'请同时输入目标和至少一个数组元素。');
        $target = array_shift($values);
        $sorted = $values; sort($sorted, SORT_NUMERIC);
        if ($values !== $sorted) return array('error'=>'数组必须按非递减顺序排列。');
        $expected = in_array($target, $values, true) ? 1 : 0;
        $actual = 0; $l = 0; $r = count($values) - 1;
        while ($l < $r) {
            $m = intdiv($l + $r, 2);
            if ($values[$m] === $target) { $actual = 1; break; }
            if ($values[$m] < $target) $l = $m + 1; else $r = $m - 1;
        }
    } else {
        if (count($values) !== 1 || $values[0] < 1 || $values[0] > 100) return array('error'=>'请输入一个 1 至 100 的整数金额。');
        $amount = $values[0]; $remaining = $amount; $actual = 0;
        foreach (array(4, 3, 1) as $coin) { $actual += intdiv($remaining, $coin); $remaining %= $coin; }
        $dp = array(0);
        for ($i = 1; $i <= $amount; $i++) {
            $dp[$i] = $i;
            foreach (array(1, 3, 4) as $coin) if ($coin <= $i) $dp[$i] = min($dp[$i], $dp[$i-$coin]+1);
        }
        $expected = $dp[$amount];
    }
    return array('hit'=>$expected !== $actual, 'expected'=>$expected, 'actual'=>$actual,
        'size'=>count($values), 'reason'=>$expected !== $actual ? $hunts[$slug]['reason'] : '这组输入还没有暴露错误，试试边界情况。');
}

function adv_shadow_score($rows, $start, $end) {
    $accepted = array(); $wrong = 0; $first = null;
    foreach ($rows as $row) {
        $at = strtotime($row['in_date']);
        if ($at < $start || $at > $end) continue;
        if (intval($row['result']) === 4) {
            $id = intval($row['problem_id']); $seconds = $at - $start;
            if (!isset($accepted[$id]) || $seconds < $accepted[$id]) $accepted[$id] = $seconds;
            $first = $first === null ? $seconds : min($first, $seconds);
        } elseif (intval($row['result']) >= 5 && intval($row['result']) <= 11) $wrong++;
    }
    return array('accepted'=>$accepted, 'wrong'=>$wrong, 'first'=>$first);
}
