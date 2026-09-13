<?php

function kg_category_key($primary, $secondary) {
    $primary = trim((string)$primary);
    $secondary = trim((string)$secondary);
    return $secondary === '' ? $primary : $primary.'-'.$secondary;
}

function kg_split_problem_tags($source) {
    $source = trim((string)$source);
    if ($source === '') return array();
    $parts = preg_split('/\s+/u', $source, -1, PREG_SPLIT_NO_EMPTY);
    $tags = array();
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') $tags[$part] = true;
    }
    return array_keys($tags);
}

function kg_match_tag_nodes($tags, $tagToNodes) {
    $matched = array();
    foreach ($tags as $tag) {
        if (!isset($tagToNodes[$tag])) continue;
        foreach ($tagToNodes[$tag] as $slug => $_) $matched[$slug] = true;
    }
    return $matched;
}

function kg_schema_ready() {
    $rows = pdo_query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('knowledge_node','knowledge_edge','knowledge_node_category','knowledge_node_tag_alias')");
    return $rows !== false && intval($rows[0][0]) === 4;
}

function kg_problem_difficulty($accepted, $submitted) {
    $submitted = intval($submitted);
    if ($submitted < 10) return 'medium';
    $rate = intval($accepted) / max(1, $submitted);
    if ($rate >= 0.60) return 'easy';
    if ($rate >= 0.30) return 'medium';
    return 'hard';
}

function kg_user_node_progress($problems, $userResults) {
    $tiers = array(
        'easy' => array('total' => 0, 'accepted' => 0, 'weight' => 0.40),
        'medium' => array('total' => 0, 'accepted' => 0, 'weight' => 0.40),
        'hard' => array('total' => 0, 'accepted' => 0, 'weight' => 0.20),
    );
    $acceptedCount = 0;
    $attemptedCount = 0;
    $lastActivity = null;
    $recommendations = array();

    foreach ($problems as &$problem) {
        $pid = intval($problem['id']);
        $result = isset($userResults[$pid]) ? $userResults[$pid] : null;
        $problem['user_status'] = 'unseen';
        $problem['attempts'] = 0;
        if ($result) {
            $attemptedCount++;
            $problem['attempts'] = intval($result['attempts']);
            $problem['user_status'] = $result['accepted'] ? 'accepted' : 'attempted';
            if ($result['accepted']) $acceptedCount++;
            if ($lastActivity === null || $result['last_activity'] > $lastActivity) $lastActivity = $result['last_activity'];
        }
        $tier = $problem['difficulty'];
        $tiers[$tier]['total']++;
        if ($problem['user_status'] === 'accepted') $tiers[$tier]['accepted']++;
        if ($problem['user_status'] !== 'accepted') $recommendations[] = $problem;
    }
    unset($problem);

    $weightTotal = 0.0;
    $mastery = 0.0;
    foreach ($tiers as &$tier) {
        $tier['score'] = $tier['total'] ? intval(round(100 * $tier['accepted'] / $tier['total'])) : null;
        if ($tier['total']) {
            $weightTotal += $tier['weight'];
            $mastery += ($tier['accepted'] / $tier['total']) * $tier['weight'];
        }
    }
    unset($tier);
    $mastery = $weightTotal > 0 ? intval(round(100 * $mastery / $weightTotal)) : 0;
    $completion = count($problems) ? intval(round(100 * $acceptedCount / count($problems))) : 0;

    usort($recommendations, function ($a, $b) {
        $statusRank = array('attempted' => 0, 'unseen' => 1);
        $difficultyRank = array('easy' => 0, 'medium' => 1, 'hard' => 2);
        $sa = isset($statusRank[$a['user_status']]) ? $statusRank[$a['user_status']] : 3;
        $sb = isset($statusRank[$b['user_status']]) ? $statusRank[$b['user_status']] : 3;
        if ($sa !== $sb) return $sa - $sb;
        $da = $difficultyRank[$a['difficulty']];
        $db = $difficultyRank[$b['difficulty']];
        if ($da !== $db) return $da - $db;
        return intval($a['id']) - intval($b['id']);
    });

    if (!count($problems)) $state = 'empty';
    else if ($attemptedCount === 0) $state = 'unseen';
    else if ($mastery >= 80) $state = 'skilled';
    else if ($mastery >= 60) $state = 'mastered';
    else $state = 'learning';

    return array(
        'problem_count' => count($problems),
        'attempted_count' => $attemptedCount,
        'accepted_count' => $acceptedCount,
        'completion' => $completion,
        'mastery' => $mastery,
        'state' => $state,
        'last_activity' => $lastActivity,
        'tiers' => $tiers,
        'recommendations' => array_slice($recommendations, 0, 6),
        'problems' => $problems,
    );
}

function kg_load_graph($userId = null) {
    $nodeRows = pdo_query("SELECT id,slug,name,domain,description,sort_order FROM knowledge_node WHERE status=1 ORDER BY domain,sort_order,id");
    $edgeRows = pdo_query("SELECT e.source_node_id,e.target_node_id,e.relation FROM knowledge_edge e JOIN knowledge_node s ON s.id=e.source_node_id AND s.status=1 JOIN knowledge_node t ON t.id=e.target_node_id AND t.status=1 ORDER BY e.relation,e.source_node_id,e.target_node_id");
    $mappingRows = pdo_query("SELECT m.node_id,c.id category_id,c.`content-1`,c.`content-2` FROM knowledge_node_category m JOIN category c ON c.id=m.category_id AND c.status=0 JOIN knowledge_node n ON n.id=m.node_id AND n.status=1 ORDER BY m.node_id,c.priority,c.id");
    $aliasRows = pdo_query("SELECT a.node_id,a.tag_text FROM knowledge_node_tag_alias a JOIN knowledge_node n ON n.id=a.node_id AND n.status=1 ORDER BY a.tag_text,a.node_id");
    $problemRows = pdo_query("SELECT problem_id,title,source,submit,accepted FROM problem WHERE defunct='N' ORDER BY problem_id");

    $nodes = array();
    $nodeIdToSlug = array();
    $domains = array();
    foreach ($nodeRows as $row) {
        $slug = $row['slug'];
        $nodes[$slug] = array(
            'id' => intval($row['id']), 'slug' => $slug, 'name' => $row['name'],
            'domain' => $row['domain'], 'description' => $row['description'],
            'sort_order' => intval($row['sort_order']), 'tags' => array(), 'aliases' => array(),
        );
        $nodeIdToSlug[intval($row['id'])] = $slug;
        if (!isset($domains[$row['domain']])) $domains[$row['domain']] = array('name' => $row['domain'], 'node_count' => 0);
        $domains[$row['domain']]['node_count']++;
    }

    $tagToNodes = array();
    $catalogTags = array();
    foreach ($mappingRows as $row) {
        $nodeId = intval($row['node_id']);
        if (!isset($nodeIdToSlug[$nodeId])) continue;
        $slug = $nodeIdToSlug[$nodeId];
        $tag = kg_category_key($row['content-1'], $row['content-2']);
        $nodes[$slug]['tags'][] = array('id' => intval($row['category_id']), 'name' => $tag);
        if (!isset($tagToNodes[$tag])) $tagToNodes[$tag] = array();
        $tagToNodes[$tag][$slug] = true;
        $catalogTags[$tag] = true;
    }
    foreach ($aliasRows as $row) {
        $nodeId = intval($row['node_id']);
        if (!isset($nodeIdToSlug[$nodeId])) continue;
        $slug = $nodeIdToSlug[$nodeId];
        $tag = trim($row['tag_text']);
        if ($tag === '') continue;
        $nodes[$slug]['aliases'][] = $tag;
        if (!isset($tagToNodes[$tag])) $tagToNodes[$tag] = array();
        $tagToNodes[$tag][$slug] = true;
        $catalogTags[$tag] = true;
    }

    $nodeProblems = array();
    foreach ($nodes as $slug => $_node) $nodeProblems[$slug] = array();
    $allObservedTags = array();
    $mappedProblemCount = 0;
    $blankTagProblemCount = 0;
    $unmappedProblems = array();
    foreach ($problemRows as $row) {
        $problem = array(
            'id' => intval($row['problem_id']), 'title' => $row['title'],
            'accepted' => intval($row['accepted']), 'submitted' => intval($row['submit']),
            'difficulty' => kg_problem_difficulty($row['accepted'], $row['submit']),
        );
        $problemTags = kg_split_problem_tags($row['source']);
        if (!count($problemTags)) $blankTagProblemCount++;
        foreach ($problemTags as $tag) {
            $allObservedTags[$tag] = isset($allObservedTags[$tag]) ? $allObservedTags[$tag] + 1 : 1;
        }
        $matched = kg_match_tag_nodes($problemTags, $tagToNodes);
        if (count($matched)) $mappedProblemCount++;
        else $unmappedProblems[] = array('id' => intval($row['problem_id']), 'title' => $row['title'], 'tags' => $problemTags);
        foreach ($matched as $slug => $_) $nodeProblems[$slug][] = $problem;
    }

    $userResults = array();
    if ($userId !== null && $userId !== '') {
        $resultRows = pdo_query("SELECT problem_id,COUNT(*) attempts,MAX(result=4) accepted,MAX(in_date) last_activity FROM solution WHERE user_id=? GROUP BY problem_id", $userId);
        foreach ($resultRows as $row) {
            $userResults[intval($row['problem_id'])] = array(
                'attempts' => intval($row['attempts']), 'accepted' => intval($row['accepted']) === 1,
                'last_activity' => $row['last_activity'],
            );
        }
    }

    foreach ($nodes as $slug => &$node) {
        $progress = kg_user_node_progress($nodeProblems[$slug], $userResults);
        if ($userId === null || $userId === '') {
            foreach ($progress['problems'] as &$problem) {
                unset($problem['user_status'], $problem['attempts']);
            }
            unset($problem);
            $progress['attempted_count'] = null;
            $progress['accepted_count'] = null;
            $progress['completion'] = null;
            $progress['mastery'] = null;
            $progress['state'] = 'anonymous';
            $progress['recommendations'] = array_slice($progress['problems'], 0, 6);
        }
        $node['progress'] = $progress;
    }
    unset($node);

    $edges = array();
    foreach ($edgeRows as $row) {
        $source = isset($nodeIdToSlug[intval($row['source_node_id'])]) ? $nodeIdToSlug[intval($row['source_node_id'])] : null;
        $target = isset($nodeIdToSlug[intval($row['target_node_id'])]) ? $nodeIdToSlug[intval($row['target_node_id'])] : null;
        if ($source && $target) $edges[] = array('source' => $source, 'target' => $target, 'relation' => $row['relation']);
    }

    $unmappedTags = array();
    foreach ($allObservedTags as $tag => $count) {
        if (!isset($catalogTags[$tag])) $unmappedTags[] = array('name' => $tag, 'problem_count' => $count);
    }
    usort($unmappedTags, function ($a, $b) { return $b['problem_count'] - $a['problem_count']; });

    $domainOrder = array('编程基础','基础算法','数据结构','搜索','动态规划','数学','字符串算法','进阶专题');
    $domainList = array_values($domains);
    usort($domainList, function ($a, $b) use ($domainOrder) {
        $ai = array_search($a['name'], $domainOrder, true);
        $bi = array_search($b['name'], $domainOrder, true);
        $ai = $ai === false ? count($domainOrder) : $ai;
        $bi = $bi === false ? count($domainOrder) : $bi;
        if ($ai === $bi) return strcmp($a['name'], $b['name']);
        return $ai - $bi;
    });
    foreach ($domainList as &$domain) {
        $domain['problem_count'] = 0;
        $domain['mastery'] = $userId ? 0 : null;
        $scores = array();
        foreach ($nodes as $node) {
            if ($node['domain'] !== $domain['name']) continue;
            $domain['problem_count'] += $node['progress']['problem_count'];
            if ($userId && $node['progress']['problem_count']) $scores[] = $node['progress']['mastery'];
        }
        if ($userId) $domain['mastery'] = count($scores) ? intval(round(array_sum($scores) / count($scores))) : 0;
    }
    unset($domain);

    return array(
        'authenticated' => $userId !== null && $userId !== '',
        'domains' => $domainList,
        'nodes' => array_values($nodes),
        'edges' => $edges,
        'governance' => array(
            'active_problem_count' => count($problemRows),
            'mapped_problem_count' => $mappedProblemCount,
            'unmapped_problem_count' => count($problemRows) - $mappedProblemCount,
            'blank_tag_problem_count' => $blankTagProblemCount,
            'coverage' => count($problemRows) ? intval(round(100 * $mappedProblemCount / count($problemRows))) : 100,
            'unmapped_tag_count' => count($unmappedTags),
            'unmapped_tags' => array_slice($unmappedTags, 0, 30),
            'unmapped_problems' => array_slice($unmappedProblems, 0, 100),
        ),
    );
}

?>
