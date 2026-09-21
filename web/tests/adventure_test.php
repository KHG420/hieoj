<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../include/adventure.inc.php';
function adv_expect($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
foreach (adv_hunts() as $hunt) adv_expect(isset($hunt['title'],$hunt['rule'],$hunt['code'],$hunt['reason']), 'Every hunt has renderable content');
foreach (array('maximum'=>'-4 -2', 'search'=>'5 5', 'coins'=>'6') as $slug=>$input) adv_expect(adv_check_hunt($slug, $input)['hit'], "$slug counterexample");
foreach (array('maximum'=>'0 2', 'search'=>'2 2 3', 'coins'=>'4') as $slug=>$input) adv_expect(!adv_check_hunt($slug, $input)['hit'], "$slug valid non-counterexample");
foreach (array(array('coins','0'), array('coins','1 2'), array('search','1 2 1'), array('search','1'), array('maximum','1001'), array('maximum','1e2'), array('maximum','<script>'), array('maximum',str_repeat('1 ',22)), array('missing','1')) as $case) adv_expect(isset(adv_check_hunt($case[0],$case[1])['error']), 'Reject malformed input');
// Exhaustively compare the fixed coin challenge against a separate shortest-path reference.
$distance = array_fill(0,101,1000); $distance[0] = 0;
for ($i=0;$i<=100;$i++) foreach (array(1,3,4) as $coin) if ($i+$coin<=100) $distance[$i+$coin] = min($distance[$i+$coin],$distance[$i]+1);
for ($i=1;$i<=100;$i++) adv_expect(adv_check_hunt('coins',(string)$i)['expected'] === $distance[$i], 'Optimal coin output');
$public = array();
for ($id=1;$id<=6;$id++) $public[$id] = array('id'=>$id,'title'=>'P'.$id,'difficulty'=>'easy');
$results = array(1=>array('accepted'=>0,'last_failure'=>'2026-09-13 12:00:00'),2=>array('accepted'=>1,'last_failure'=>'2026-09-12 12:00:00'),3=>array('accepted'=>0,'last_failure'=>'2026-09-14 12:00:00'));
adv_expect(adv_enemy(array_values($public),$results,'2026-09-14')['id'] === 1, 'Only prior unresolved attempts qualify');
$graph = array('nodes'=>array(array('slug'=>'base','name'=>'基础','progress'=>array('problems'=>array_values(array_slice($public,0,5,true)))),array('slug'=>'target','name'=>'进阶','progress'=>array('problems'=>array($public[6])))), 'edges'=>array(array('source'=>'base','target'=>'target','relation'=>'prerequisite')));
$route = adv_route($graph,$public,$results,'target','challenge');
adv_expect(count($route)===3 && $route[2]['id']===6 && !in_array(2,array_column($route,'id')), 'Route reserves target and excludes accepted');
unset($public[6]);
adv_expect(adv_route($graph,$public,$results,'target','challenge')===array(), 'Hidden target cannot enter route');
adv_expect(adv_week(strtotime('2026-09-13 23:59:59'))[0]==='2026-09-07 00:00:00', 'Sunday belongs to previous week');
adv_expect(adv_week(strtotime('2026-09-14 00:00:00'))[0]==='2026-09-14 00:00:00', 'Monday rolls over');
adv_expect(adv_week_problems(array_values($public),'2026-09-14')===adv_week_problems(array_reverse(array_values($public)),'2026-09-14'), 'Weekly selection independent of input order');
foreach (array(
    array('2026-09-14 23:59:59', 20260914, '2026-09-14 00:00:00', '2026-09-15 00:00:00'),
    array('2026-09-15 00:00:00', 20260915, '2026-09-15 00:00:00', '2026-09-16 00:00:00'),
    array('2026-12-31 12:00:00', 20261231, '2026-12-31 00:00:00', '2027-01-01 00:00:00'),
) as $edge) {
    $day = adv_reward_day(strtotime($edge[0]));
    adv_expect($day['reference']===$edge[1] && $day['start']===$edge[2] && $day['end']===$edge[3], 'Reward day window and reference at '.$edge[0]);
}
$start = strtotime('2026-09-14 12:00:00');
$rows = array(array('problem_id'=>1,'result'=>4,'in_date'=>'2026-09-14 11:59:59'),array('problem_id'=>1,'result'=>6,'in_date'=>'2026-09-14 12:00:05'),array('problem_id'=>1,'result'=>4,'in_date'=>'2026-09-14 12:00:10'),array('problem_id'=>1,'result'=>4,'in_date'=>'2026-09-14 12:00:20'),array('problem_id'=>2,'result'=>4,'in_date'=>'2026-09-14 12:01:01'));
$score = adv_shadow_score($rows,$start,$start+60);
adv_expect(count($score['accepted'])===1 && $score['first']===10 && $score['wrong']===1, 'Shadow time boundaries and AC deduplication');
adv_expect(adv_escape('<script>')==='&lt;script&gt;', 'Escape titles');
adv_expect(adv_is_comeback(array('first_ac'=>'2026-09-14 12:00:00','first_failure'=>'2026-09-14 12:00:00','first_ac_id'=>2,'first_failure_id'=>1)), 'Same-second failed attempt precedes AC by submission ID');
adv_expect(!adv_is_comeback(array('first_ac'=>'2026-09-14 12:00:00','first_failure'=>'2026-09-14 12:00:00','first_ac_id'=>1,'first_failure_id'=>2)), 'Failures after first AC are not comebacks');
echo "Adventure logic: all checks passed.\n";
