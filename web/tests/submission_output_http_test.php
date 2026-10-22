<?php
// Explicit opt-in: creates exact random fixtures and removes them in finally.
// Final results are inserted directly, so no judge task or email is triggered.
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--fixtures') exit(1);
chdir(dirname(__DIR__));
require 'include/db_info.inc.php';
$boot = session_id(); session_write_close();
if ($boot) @unlink(ini_get('session.save_path').'/sess_'.$boot);
$prefix = 'outtest_'.bin2hex(random_bytes(5));
$users = []; $sessions = []; $solutions = []; $pid = null; $checks = 0; $failed = false;
$runtime = 'PRIVATE_RUNTIME_'.bin2hex(random_bytes(8));
$compiler = 'COMPILE_DIAGNOSTIC_'.bin2hex(random_bytes(8)).": Main.cc: error: expected semicolon";
function output_check($ok, $message) {
    global $checks; $checks++;
    if (!$ok) throw new RuntimeException($message);
}
function output_http($path, $role = null, $expected = 200, $referer = null) {
    global $sessions, $runtime;
    usleep(180000);
    $c = curl_init('http://127.0.0.1/'.$path);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20]);
    if ($role !== null) curl_setopt($c, CURLOPT_COOKIE, 'PHPSESSID='.$sessions[$role]);
    if ($referer !== null) curl_setopt($c, CURLOPT_REFERER, 'http://127.0.0.1/'.$referer);
    $body = curl_exec($c); $status = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    output_check($body !== false && $status === $expected, "$path $role: HTTP $status, expected $expected");
    output_check(!preg_match('/Fatal error|Warning:|Uncaught /', $body), "$path has no PHP failure");
    if (!in_array($role, ['admin','source'], true)) output_check(!str_contains($body, $runtime), "$path must not disclose runtime data");
    return $body;
}
try {
    foreach (['owner','other','admin','source'] as $role) {
        $uid = $prefix.'_'.$role;
        output_check(pdo_query("INSERT INTO users(user_id,nick,defunct,register_num,reg_time) VALUES(?,?,'N',1,NOW())", $uid, 'Output test') === true, 'Create exact temporary user');
        $users[$role] = $uid;
        $sessions[$role] = 'output'.bin2hex(random_bytes(16));
        session_id($sessions[$role]); session_start();
        $_SESSION = [$OJ_NAME.'_user_id'=>$uid];
        if ($role === 'admin') $_SESSION[$OJ_NAME.'_administrator'] = true;
        if ($role === 'source') $_SESSION[$OJ_NAME.'_source_browser'] = true;
        session_write_close();
    }
    $pid = (int)pdo_query("INSERT INTO problem(title,source,defunct,in_date) VALUES(?,?,'N',NOW())", $prefix, $prefix);
    output_check($pid > 0, 'Temporary problem created');
    foreach ([4,5,6,7,8,9,10,11,13] as $result) {
        $sid = (int)pdo_query('INSERT INTO solution(user_id,problem_id,result,language,in_date,time,memory,judger) VALUES(?,?,?,1,NOW(),1,64,?)', $users['owner'], $pid, $result, 'PRIVATE_JUDGER');
        output_check($sid > 0, 'Temporary final result created'); $solutions[$result] = $sid;
        pdo_query('INSERT INTO runtimeinfo(solution_id,error) VALUES(?,?)', $sid, $runtime."\ninput: secret\nexpected: secret\nactual: secret\n/srv/private/worker");
        // Retain old compiler diagnostics to exercise a forged CE AI referer after rejudge.
        pdo_query('INSERT INTO compileinfo(solution_id,error) VALUES(?,?)', $sid, $compiler);
        foreach ([null, 'owner', 'other'] as $role) {
            output_http('reinfo.php?sid='.$sid, $role, 403);
            $csv = output_http('status-ajax.php?solution_id='.$sid, $role);
            output_check((int)explode(',', $csv)[0] === $result, 'Normal verdict remains visible');
            $json = json_decode(output_http('status-ajax.php?t=json&solution_id='.$sid, $role), true);
            output_check((int)$json['result'] === $result && !in_array('PRIVATE_JUDGER', $json, true), 'JSON verdict retained and both judger keys masked');
        }
        output_http('status-ajax.php?tr=1&solution_id='.$sid, 'other', 403);
        output_http('status-ajax.php?tr=1&solution_id='.$sid, null, 403);
        if ($result === 11) {
            output_check(str_contains(output_http('status-ajax.php?tr=1&solution_id='.$sid, 'owner'), $compiler), 'Own compiler diagnostic remains visible');
            output_check(str_contains(output_http('ceinfo.php?sid='.$sid, 'owner'), $compiler), 'CE detail page remains usable');
        } else {
            output_http('status-ajax.php?tr=1&solution_id='.$sid, 'owner', 403);
        }
    }
    $sid = $solutions[10];
    foreach (['admin','source'] as $role) {
        output_check(str_contains(output_http('reinfo.php?sid='.$sid, $role), $runtime), 'Privileged runtime details available');
        output_check(str_contains(output_http('status-ajax.php?tr=1&solution_id='.$sid, $role), $runtime), 'Privileged runtime polling available');
    }
    // A cached privileged page must not bypass permission removal in the same session.
    session_id($sessions['source']); session_start(); unset($_SESSION[$OJ_NAME.'_source_browser']); session_write_close();
    output_http('reinfo.php?sid='.$sid, 'source', 403);
    foreach (['aiapi/demo.php','aiapi/yuanqi.php'] as $api) {
        foreach (['reinfo.php','ceinfo.php'] as $referer) output_http($api.'?sid='.$sid, 'owner', 403, $referer);
    }
    $html = output_http('status.php?user_id='.rawurlencode($users['owner']), 'owner');
    output_check(!preg_match('/href=[\'"]?reinfo\.php\?sid=/', $html), 'Rendered student verdicts have no runtime links');
    output_check(str_contains($html, 'ceinfo.php?sid='.$solutions[11]), 'Rendered CE link remains');
    output_check(str_contains($html, 'var oj_can_view_runtime = false;'), 'Polling receives the student policy');
    $html = output_http('status.php?user_id='.rawurlencode($users['owner']), 'admin');
    output_check(str_contains($html, 'reinfo.php?sid='.$sid), 'Administrator status keeps runtime links');
    echo "PASS: $checks runtime output boundary checks including owner, anonymous, other user, admin, source browser, cache, AI and CE\n";
} catch (Throwable $e) {
    $failed = true; fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n");
} finally {
    foreach ($solutions as $sid) {
        foreach (['runtimeinfo','compileinfo','custominput','source_code','source_code_user','solution_ai_answer','solution'] as $table) pdo_query("DELETE FROM `$table` WHERE solution_id=?", $sid);
    }
    if ($pid) pdo_query('DELETE FROM problem WHERE problem_id=? AND title=?', $pid, $prefix);
    foreach ($users as $uid) {
        foreach (['coin_ledger','coin_first_ac','coin_wallet','users'] as $table) pdo_query("DELETE FROM `$table` WHERE user_id=?", $uid);
    }
    foreach ($sessions as $sid) @unlink(ini_get('session.save_path').'/sess_'.$sid);
    echo "Temporary submission fixtures and sessions removed.\n";
}
exit($failed ? 1 : 0);
