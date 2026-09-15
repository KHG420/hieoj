<?php
// Self-contained renderer regression for the syzoj status similarity markup.
// No database, HTTP or network access: it extracts the bounded similarity render block
// from web/status.php and evaluates it with fixtures, then inspects the syzoj template.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$status_src = file_get_contents($root.'/status.php');
$tpl_src = file_get_contents($root.'/template/syzoj/status.php');
$js_src = file_get_contents($root.'/template/syzoj/auto_refresh.js');
if ($status_src === false || $tpl_src === false || $js_src === false) {
    fwrite(STDERR, "FAIL: cannot read product sources\n");
    exit(1);
}

$checks = 0;
function ss_check($ok, $message) {
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($message);
}

// Extract the bounded similarity render block (markers live in web/status.php).
function ss_block($src) {
    $begin = '/* OJ_STATUS_SIMILARITY_BEGIN';
    $end = '/* OJ_STATUS_SIMILARITY_END */';
    $b = strpos($src, $begin);
    if ($b === false) throw new RuntimeException('OJ_STATUS_SIMILARITY_BEGIN marker missing');
    $b = strpos($src, '*/', $b);
    if ($b === false) throw new RuntimeException('OJ_STATUS_SIMILARITY_BEGIN marker not closed');
    $b += 2;
    $e = strpos($src, $end, $b);
    if ($e === false) throw new RuntimeException('OJ_STATUS_SIMILARITY_END marker missing');
    return substr($src, $b, $e - $b);
}

// Execute the real branch with a fixture (mirrors the globals the block relies on).
function ss_render($block, array $fixture) {
    $OJ_SIM = $fixture['oj_sim'];
    $OJ_TEMPLATE = $fixture['template'];
    $OJ_NAME = 'oj';
    $judge_color = $fixture['judge_color'];
    $judge_result = $fixture['judge_result'];
    $MSG_Tips = $fixture['tips'];
    $i = 0;
    $lock = false;
    $lock_time = '';
    $row = $fixture['row'];
    $_SESSION = $fixture['session'];
    $_GET = $fixture['get'];
    $view_status = array(0 => array(5 => "<span class='hidden' style='display:none' result='".$row['result']."' ></span>"));
    eval($block);
    return $view_status[$i][5];
}

$base_colors = array(
    0 => 'btn gray', 1 => 'btn btn-info', 2 => 'btn btn-warning', 3 => 'btn btn-warning',
    4 => 'btn btn-success', 5 => 'btn btn-danger', 6 => 'btn btn-danger', 7 => 'btn btn-warning',
    10 => 'btn btn-warning', 11 => 'btn btn-warning',
);
$base_results = array(
    0 => 'Pending', 1 => 'Pending Rejudge', 2 => 'Compiling', 3 => 'Running',
    4 => 'Accepted', 5 => 'Presentation Error', 6 => 'Wrong Answer', 7 => 'Time Limit Exceeded',
    10 => 'Runtime Error', 11 => 'Compile Error',
);

function ss_fixture(array $o = array()) {
    global $base_colors, $base_results;
    $f = array(
        'oj_sim' => true,
        'template' => 'syzoj',
        'judge_color' => $base_colors,
        'judge_result' => $base_results,
        'tips' => 'hint',
        'session' => array(),
        'get' => array(),
        'row' => array(
            'result' => 4,
            'sim' => 82,
            'sim_s_id' => 9001,
            's_id' => 5002,
            'solution_id' => 5001,
            'user_id' => 'student',
            'pass_rate' => 1.0,
            'in_date' => '2026-01-01 00:00:00',
        ),
    );
    if (isset($o['row'])) {
        $f['row'] = array_merge($f['row'], $o['row']);
        unset($o['row']);
    }
    return array_merge($f, $o);
}

function ss_luminance($hex) {
    $hex = ltrim($hex, '#');
    $f = function($c) { return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4); };
    return 0.2126 * $f(hexdec(substr($hex, 0, 2)) / 255)
        + 0.7152 * $f(hexdec(substr($hex, 2, 2)) / 255)
        + 0.0722 * $f(hexdec(substr($hex, 4, 2)) / 255);
}
function ss_contrast($a, $b) {
    $la = ss_luminance($a); $lb = ss_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

$failed = false;
try {
    $block = ss_block($status_src);
    ss_check(strpos($block, 'if($OJ_SIM') !== false, 'block contains the similarity condition');

    // AC1: syzoj public similar Accepted result.
    $out = ss_render($block, ss_fixture());
    ss_check(strpos($out, "<span class='btn btn-success'") !== false, 'public: result badge keeps btn-success');
    ss_check(strpos($out, '>Accepted</span>') !== false, 'public: result badge keeps accepted text');
    ss_check(strpos($out, '*Accepted') === false, 'public: no redundant similarity asterisk');
    ss_check(strpos($out, "<span class='oj-status-similarity'>相似度 82%</span>") !== false, 'public: muted unboxed 相似度 82% span');
    ss_check(strpos($out, 'comparesource.php') === false, 'public: no comparison link');
    ss_check(strpos($out, '9001') === false, 'public: original submission id not exposed');
    ss_check(strpos($out, 'btn-info') === false, 'public: legacy btn-info removed for syzoj');

    // AC1/AC2: authorized source_browser gets the escaped comparison link.
    $out = ss_render($block, ss_fixture(array('session' => array('oj_source_browser' => true))));
    ss_check(
        strpos($out, "<a href='comparesource.php?left=9001&right=5001' class='oj-status-similarity' target=original title='查看代码相似度详情（原提交 #9001）'>相似度 82%</a>") !== false,
        'authorized: escaped comparison link keeps left/right, target and tooltip'
    );
    ss_check(strpos($out, '*Accepted') === false, 'authorized: no redundant similarity asterisk');

    // AC2: showsim metadata span preserved for both audiences.
    $out = ss_render($block, ss_fixture(array('get' => array('showsim' => '1'))));
    ss_check(strpos($out, "<span sid='9001' class='original'></span>") !== false, 'public: showsim span.original sid preserved');
    $out = ss_render($block, ss_fixture(array('get' => array('showsim' => '1'), 'session' => array('oj_source_browser' => true))));
    ss_check(strpos($out, "<span sid='9001' class='original'></span>") !== false, 'authorized: showsim span.original sid preserved');

    // AC2: no similarity line/placeholder when it must not apply.
    foreach (array(
        'threshold 50' => ss_fixture(array('row' => array('sim' => 50))),
        'same source' => ss_fixture(array('row' => array('sim_s_id' => 5001, 's_id' => 5001))),
        'similarity disabled' => ss_fixture(array('oj_sim' => false)),
        'no similarity' => ss_fixture(array('row' => array('result' => 6, 'sim' => 0))),
    ) as $label => $fixture) {
        $out = ss_render($block, $fixture);
        ss_check(strpos($out, 'oj-status-similarity') === false, "$label: no similarity label");
        ss_check(strpos($out, '相似度') === false, "$label: no similarity placeholder");
    }
    $out = ss_render($block, ss_fixture(array('row' => array('result' => 6, 'sim' => 0))));
    ss_check(strpos($out, "<span class='btn btn-danger'") !== false && strpos($out, '>Wrong Answer</span>') !== false, 'no similarity: normal result badge unchanged');

    // AC2: other templates keep the legacy markup untouched.
    $out = ss_render($block, ss_fixture(array('template' => 'bs3')));
    ss_check(strpos($out, '*Accepted') !== false, 'legacy: asterisk preserved');
    ss_check(strpos($out, "<span class='btn-info'>(82%)</span>") !== false, 'legacy: btn-info percentage preserved');
    ss_check(strpos($out, 'oj-status-similarity') === false, 'legacy: syzoj class not emitted');
    $out = ss_render($block, ss_fixture(array('template' => 'bs3', 'session' => array('oj_source_browser' => true))));
    ss_check(strpos($out, "<a href=comparesource.php?left=9001&right=5001  class='btn-info'  target=original>9001(82%)</a>") !== false, 'legacy: authorized link preserved');

    // AC3: preserved surrounding behaviour and markers.
    ss_check(strpos($status_src, "ceinfo.php?sid=") !== false, 'CE path intact');
    ss_check(strpos($status_src, "reinfo.php?sid=") !== false, 'RE path intact');
    ss_check(strpos($status_src, "span class='hidden'") !== false && strpos($status_src, "result='") !== false, 'result marker span intact');
    ss_check(strpos($status_src, 'http_judge_form') !== false, 'HTTP judge form marker intact');
    ss_check(strpos($tpl_src, 'auto_refresh.js?v=1.0') !== false, 'auto refresh script tag intact');
    ss_check(strpos($js_src, 'span[class=original]') !== false && strpos($js_src, 'status-ajax.php?q=user_id') !== false, 'auto refresh hover metadata consumer intact');

    // AC1: scoped, muted, unboxed CSS with readable contrast.
    foreach (array(
        '#result-tab .oj-status-similarity',
        'display: block',
        'width: fit-content',
        'margin-top: 4px',
        'font-size: 12px',
        'line-height: 1.4',
        'color: #596a82',
        'background: none',
        'border: 0',
        'box-shadow: none',
    ) as $rule) {
        ss_check(strpos($tpl_src, $rule) !== false, "syzoj CSS contains: $rule");
    }
    ss_check(strpos($tpl_src, 'a.oj-status-similarity:hover') !== false, 'syzoj CSS: link hover rule');
    ss_check(strpos($tpl_src, ':focus') !== false && strpos($tpl_src, 'text-decoration: underline') !== false, 'syzoj CSS: hover/focus underline');
    ss_check(strpos($tpl_src, ':focus-visible') !== false && strpos($tpl_src, 'outline: 2px solid #3d4c63') !== false, 'syzoj CSS: keyboard visible focus');
    ss_check(ss_contrast('#596a82', '#ffffff') >= 4.5, 'syzoj CSS: muted gray contrast >= 4.5 on white');
    $scoped = true;
    foreach (explode("\n", $tpl_src) as $line) {
        if (strpos($line, 'oj-status-similarity') !== false && strpos($line, '#result-tab') === false) { $scoped = false; break; }
    }
    ss_check($scoped, 'syzoj CSS: every similarity rule is scoped under #result-tab');

    echo "PASS: $checks status similarity checks\n";
} catch (Throwable $e) {
    $failed = true;
    fwrite(STDERR, "FAIL: ".$e->getMessage()."\n");
}
exit($failed ? 1 : 0);
