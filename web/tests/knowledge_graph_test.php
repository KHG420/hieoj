<?php
require_once(dirname(__FILE__).'/../include/knowledge_graph.inc.php');

function expect_equal($actual, $expected, $message) {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: $message\nExpected: ".var_export($expected,true)."\nActual: ".var_export($actual,true)."\n");
        exit(1);
    }
}

expect_equal(kg_category_key('C语言', '循环结构'), 'C语言-循环结构', 'category key joins two levels');
expect_equal(kg_category_key('ACM', ''), 'ACM', 'category key supports a top-level tag');
expect_equal(kg_split_problem_tags("C语言-数组  ACM-思维\t算法设计-双指针"), array('C语言-数组','ACM-思维','算法设计-双指针'), 'source tags split on all whitespace');
$tagNodes = array('快速幂' => array('fast-power' => true), '矩阵快速幂' => array('fast-power' => true, 'matrix-fast-power' => true));
expect_equal(kg_match_tag_nodes(array('快速幂','矩阵快速幂'), $tagNodes), array('fast-power' => true, 'matrix-fast-power' => true), 'standard and alias tags map to unique knowledge nodes');
expect_equal(kg_problem_difficulty(70, 100), 'easy', 'high acceptance is easy');
expect_equal(kg_problem_difficulty(40, 100), 'medium', 'middle acceptance is medium');
expect_equal(kg_problem_difficulty(10, 100), 'hard', 'low acceptance is hard');
expect_equal(kg_problem_difficulty(0, 3), 'medium', 'small samples remain medium');

$problems = array(
    array('id'=>1,'title'=>'A','difficulty'=>'easy','accepted'=>70,'submitted'=>100),
    array('id'=>2,'title'=>'B','difficulty'=>'medium','accepted'=>40,'submitted'=>100),
    array('id'=>3,'title'=>'C','difficulty'=>'hard','accepted'=>10,'submitted'=>100),
);
$results = array(
    1 => array('attempts'=>1,'accepted'=>true,'last_activity'=>'2026-09-01 10:00:00'),
    2 => array('attempts'=>2,'accepted'=>false,'last_activity'=>'2026-09-02 10:00:00'),
);
$progress = kg_user_node_progress($problems, $results);
expect_equal($progress['attempted_count'], 2, 'attempted problems are distinct');
expect_equal($progress['accepted_count'], 1, 'accepted problems are distinct');
expect_equal($progress['completion'], 33, 'completion uses unique accepted problems');
expect_equal($progress['mastery'], 40, 'mastery uses 40/40/20 tier weights');
expect_equal($progress['recommendations'][0]['id'], 2, 'attempted unresolved problem is recommended first');

echo "knowledge graph logic: OK\n";
?>
