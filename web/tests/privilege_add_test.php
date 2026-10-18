<?php
// CLI-only PHP 8 regression for the actual privilege option renderer; no DB writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = file_get_contents($argv[1] ?? dirname(__DIR__).'/admin/privilege_add.php');
try {
    $start = strpos($source, '$rightarray=array(');
    if ($start === false) throw new RuntimeException('Privilege renderer not found');
    $end = strpos($source, '?>', $start);
    if ($end === false) throw new RuntimeException('Privilege renderer is incomplete');
    $block = substr($source, $start, $end - $start);
    $expected = array('administrator','problem_editor','source_browser','contest_creator','http_judge','password_setter','printer','balloon');
    foreach (array(null, 'administrator', 'problem_editor') as $rightstr) {
        ob_start();
        try { eval($block); $html = ob_get_contents(); }
        finally { ob_end_clean(); }
        preg_match_all('/<option value="([^"]+)"( selected)?>[^<]+<\/option>/', $html, $matches);
        if ($matches[1] !== $expected) throw new RuntimeException('All eight permission options must render in order');
        $selected = $rightstr === null ? 0 : 1;
        if (substr_count($html, ' selected') !== $selected) throw new RuntimeException('Unexpected selected option count');
        if ($rightstr !== null && !str_contains($html, 'value="'.$rightstr.'" selected')) throw new RuntimeException('Selected permission changed');
    }
    echo "PASS: all eight privileges render on PHP ".PHP_VERSION."; administrator and other selected options preserved\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n");
    exit(1);
}
