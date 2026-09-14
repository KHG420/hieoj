<?php
// Explicit opt-in: exercises actual Nginx/PHP/database with temporary accounts.
// No real application is approved, no email is sent, fixtures are removed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') !== '--fixtures') {
    fwrite(STDERR, "Usage: php registration_approval_http_test.php --fixtures\n");
    exit(1);
}
require __DIR__ . '/../include/db_info.inc.php';
require_once __DIR__ . '/../include/my_func.inc.php';
session_write_close();
$prefix = 'approvaltest_' . bin2hex(random_bytes(6));
$admin = $prefix . '_a';
$student = $prefix . '_s';
$rejected = $prefix . '_r';
$ids = [$admin, $student, $rejected];
$password = bin2hex(random_bytes(12));
$cookies = tempnam(sys_get_temp_dir(), 'oj-approval-cookies-');
$studentCookies = tempnam(sys_get_temp_dir(), 'oj-approval-student-');
$testCookies = $cookies;
$owned = [];
$failed = false;
function approval_check($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
}
function approval_request($path, $post = null) {
    global $testCookies;
    $curl = curl_init('http://127.0.0.1' . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_COOKIEFILE => $testCookies, CURLOPT_COOKIEJAR => $testCookies,
    ]);
    if ($post !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    $start = microtime(true);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    approval_check($body !== false && $status === 200, "$path: HTTP $status");
    approval_check(!preg_match('/Fatal error|Uncaught (?:Error|TypeError|PDOException)/', $body), "$path: PHP failure");
    printf("HTTP %s %.3fs\n", strtok($path, '?'), microtime(true) - $start);
    return $body;
}
function approval_key($id, $page = 'user_register.php') {
    $body = approval_request('/admin/' . $page);
    approval_check(!str_contains($body, 'Please Login First'), 'Admin authentication');
    preg_match('/cid=' . preg_quote($id, '/') . '&getkey=([^&>"\s]+)/', $body, $match);
    approval_check(isset($match[1]), "Approval link exists for fixture");
    return $match[1];
}
function approval_user($id) {
    return pdo_query('SELECT register_num,defunct,nick FROM users WHERE user_id=?', $id);
}
try {
    foreach ($ids as $id) {
        approval_check(approval_user($id) === [], 'Fixture ID must be unused');
        $ok = pdo_query('INSERT INTO users (user_id,nick,password,email,school,defunct,register_num,reg_time) VALUES (?,?,?,?,?,?,?,NOW())',
            $id, 'Approval test', pwGen($password), '', 'Test', $id === $admin ? 'N' : 'Y', $id === $admin ? 1 : 0);
        approval_check($ok === true, 'Create isolated fixture');
        $owned[] = $id;
    }
    pdo_query('INSERT INTO privilege (user_id,rightstr) VALUES (?,?)', $admin, 'administrator');
    approval_request('/loginpage.php');
    $body = approval_request('/login.php', ['user_id' => $admin, 'password' => $password]);
    approval_check(!str_contains($body, '用户名或密码错误'), 'Temporary admin login');
    $key = approval_key($student);
    approval_request('/admin/user_register_change.php?cid=' . $student . '&num=1&getkey=invalid');
    approval_check((int)approval_user($student)[0]['register_num'] === 0, 'Invalid CSRF cannot approve');
    $body = approval_request('/admin/user_register_change.php?cid=' . $student . '&num=1&getkey=' . $key);
    approval_check(str_contains($body, '审批已通过'), 'Approval result shown');
    $row = approval_user($student)[0];
    approval_check((int)$row['register_num'] === 1 && $row['defunct'] === 'N', 'Approval saved');
    // Get a fresh key from another pending fixture, then try a stale reject link.
    $key = approval_key($rejected);
    $body = approval_request('/admin/user_register_change.php?cid=' . $student . '&num=0&getkey=' . $key);
    approval_check(str_contains($body, '已经处理') && count(approval_user($student)) === 1, 'Stale rejection cannot delete approved account');
    $key = approval_key($rejected);
    $body = approval_request('/admin/user_register_change.php?cid=' . $rejected . '&num=0&getkey=' . $key);
    approval_check(str_contains($body, '已拒绝') && approval_user($rejected) === [], 'Reject only pending fixture');
    // Exercise the other application type without touching an actual student.
    pdo_query('INSERT INTO `modify` (user_id,content,thing,reason,time,status) VALUES (?,?,?,?,NOW(),0)',
        $student, 'Approved test', 'nick', 'Automated regression');
    $requests = pdo_query('SELECT id FROM `modify` WHERE user_id=?', $student);
    $cid = (string)$requests[0]['id'];
    $key = approval_key($cid, 'user_modify.php');
    approval_request('/admin/user_modify_change.php?cid=' . $cid . '&getkey=' . $key);
    approval_check(approval_user($student)[0]['nick'] === 'Approved test', 'Profile application changes fixture only');
    $testCookies = $studentCookies;
    approval_request('/loginpage.php');
    $body = approval_request('/login.php', ['user_id' => $student, 'password' => $password]);
    approval_check(!str_contains($body, '用户名或密码错误'), 'Approved student can log in');
    $body = approval_request('/admin/user_register.php');
    approval_check(str_contains($body, 'Please Login First'), 'Student cannot access admin approvals');
    $testCookies = $cookies;
    foreach (['/', '/problemset.php', '/status.php', '/admin/user_modify.php', '/admin/user_register.php'] as $path) approval_request($path);
    echo "PASS: registration approve/reject, CSRF, stale action, profile approval, student login and permission checks\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    $failed = true;
} finally {
    // Delete only exact random IDs successfully created by this invocation.
    foreach ($owned as $id) {
        foreach (['modify', 'privilege', 'loginlog', 'users'] as $table) {
            approval_check(pdo_query("DELETE FROM `$table` WHERE user_id=?", $id) !== false, "Clean fixture $table");
        }
        approval_check(approval_user($id) === [], 'Fixture removed');
    }
    foreach ([$cookies, $studentCookies] as $jar) {
        if (is_file($jar)) {
            foreach (file($jar) as $line) {
                $parts = explode("\t", trim($line));
                if (count($parts) === 7 && $parts[5] === 'PHPSESSID' && preg_match('/^[a-zA-Z0-9,-]+$/', $parts[6])) {
                    $sessionFile = ini_get('session.save_path') . '/sess_' . $parts[6];
                    if (is_file($sessionFile)) unlink($sessionFile);
                }
            }
            unlink($jar);
        }
    }
    echo "Temporary fixture rows and HTTP sessions removed.\n";
}
exit($failed ? 1 : 0);
