<?php
// Local HTTP regression with temporary news/user/session fixtures; never run on production.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') !== '--fixtures') {
    fwrite(STDERR, "Run as www-data on a disposable local deployment: page_boundaries_test.php --fixtures\n");
    exit(1);
}
require __DIR__.'/../include/db_info.inc.php';
session_write_close();
$uid = 'boundary_'.bin2hex(random_bytes(5));
$sid = 'boundary'.bin2hex(random_bytes(16));
$news = []; $checks = 0; $failed = false;
function boundary_check($ok, $message) {
    global $checks; $checks++;
    if (!$ok) throw new RuntimeException($message);
}
function boundary_http($path, $status = 200, $admin = false) {
    global $sid;
    usleep(180000);
    $ch = curl_init('http://127.0.0.1'.$path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20]);
    if ($admin) curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID='.$sid);
    $body = curl_exec($ch); $actual = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    boundary_check($body !== false && $actual === $status, "$path: expected $status, got $actual");
    boundary_check(!preg_match('/Fatal error|Warning:|SQLSTATE|Uncaught /', $body), "$path: PHP error");
    if ($admin) boundary_check(!str_contains($body, 'Please Login First'), 'Fixture session must be readable by PHP-FPM; run as www-data');
    return $body;
}
function boundary_ids($html) {
    preg_match_all('/<tr\b[^>]*>\s*<td\b[^>]*>(.*?)<\/td>/si', $html, $matches);
    return array_map(fn($cell)=>trim(strip_tags($cell)), $matches[1]);
}
try {
    boundary_check(pdo_query("INSERT INTO users(user_id,nick,school,defunct,register_num) VALUES(?,?,'Test','N',1)", $uid, $uid) === true, 'Create temporary user');
    session_id($sid); session_start();
    $_SESSION = [$OJ_NAME.'_user_id'=>$uid, $OJ_NAME.'_administrator'=>true];
    session_write_close();
    foreach (['N','Y'] as $hidden) {
        $id = pdo_query('INSERT INTO news(user_id,title,content,time,defunct) VALUES(?,?,?,NOW(),?)', $uid, $uid, 'Boundary news body', $hidden);
        boundary_check((int)$id > 0, 'Create temporary announcement'); $news[] = (int)$id;
    }
    boundary_check(str_contains(boundary_http('/viewnews.php?id='.$news[0]), 'Boundary news body'), 'Visible announcement is readable');
    boundary_http('/viewnews.php?id='.$news[1], 404);
    foreach (["'", "' AND 1=1 -- ", "' AND 1=0 -- "] as $suffix) {
        boundary_http('/viewnews.php?id='.rawurlencode($news[0].$suffix), 404);
    }
    $missing = 2147483647;
    foreach (['viewnews.php', 'problem.php', 'problemstatus.php', 'userinfo.php', 'contestrank.php', 'contestrank2.php', 'contestrank3.php', 'contestrank-oi.php'] as $page) {
        boundary_http('/'.$page, 404);
    }
    foreach (['viewnews.php?id=', 'problem.php?id=', 'problemstatus.php?id=', 'thread.php?tid=', 'contestrank.php?cid=', 'contestrank2.php?cid=', 'contestrank3.php?cid=', 'contestrank-oi.php?cid='] as $path) {
        boundary_http('/'.$path.$missing, 404);
    }
    boundary_http('/userinfo.php?user=boundary_missing_user', 404);
    boundary_http('/problem.php?id='.$missing, 404, true);
    boundary_http('/problem.php?cid='.$missing.'&pid=0', 404, true);
    $thread = boundary_http('/thread.php?tid='.$missing, 404);
    boundary_check(str_contains($thread, '主页') && str_contains($thread, '问题') && str_contains($thread, '登录'), 'Missing thread keeps translated navigation');
    foreach (['news'=>25, 'user'=>25, 'problem'=>25, 'contest'=>10] as $name=>$size) {
        $table = $name === 'user' ? 'users' : $name;
        $total = (int)pdo_query('SELECT COUNT(*) FROM '.$table)[0][0];
        $last = max(1, (int)ceil($total/$size));
        $base = '/admin/'.$name.'_list.php?page=';
        $first = boundary_ids(boundary_http($base.'1', 200, true));
        boundary_check(count($first)>1, "$name fixture has data");
        foreach (['0','-1'] as $page) {
            boundary_check(boundary_ids(boundary_http($base.$page, 200, true)) === $first, "$name invalid page uses first page");
        }
        $end = boundary_ids(boundary_http($base.$last, 200, true));
        boundary_check(boundary_ids(boundary_http($base.'99999999', 200, true)) === $end, "$name excessive page uses last page");
        boundary_check(!preg_match('/page=-\d/', boundary_http($base.'0', 200, true)), "$name has no negative page links");
    }
    echo "PASS: $checks page boundary, announcement query and admin pagination checks\n";
} catch (Throwable $e) {
    $failed = true; fwrite(STDERR, "FAIL: ".$e->getMessage()."\n");
} finally {
    foreach ($news as $id) pdo_query('DELETE FROM news WHERE news_id=? AND user_id=?', $id, $uid);
    pdo_query('DELETE FROM users WHERE user_id=?', $uid);
    $path = ini_get('session.save_path').'/sess_'.$sid;
    if (is_file($path)) unlink($path);
}
exit($failed ? 1 : 0);
