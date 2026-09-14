<?php
// CLI-only regression: isolated SQLite users, temporary sessions and loopback mail.
// Never loads deployment credentials or writes to the site's database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

if (($argv[1] ?? '') === 'worker') {
    chdir($argv[2] . '/admin');
    require 'user_register_change.php';
    exit;
}

function check($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
}

$root = sys_get_temp_dir() . '/oj-approval-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
mkdir("$root/admin");
mkdir("$root/include");
mkdir("$root/sessions");
copy(__DIR__ . '/../admin/user_register_change.php', "$root/admin/user_register_change.php");
copy(__DIR__ . '/../include/check_get_key.php', "$root/include/check_get_key.php");
copy(__DIR__ . '/../include/pdo.php', "$root/include/pdo.php");
file_put_contents("$root/include/db_info.inc.php", '<?php');
file_put_contents("$root/admin/admin-header.php", <<<'PHP'
<?php
$settings = json_decode(file_get_contents('../case.json'), true);
session_save_path('../sessions');
session_id('approval-regression');
session_start();
$OJ_NAME = 'test';
$_SESSION['test_administrator'] = true;
$_SESSION['test_getkey'] = 'valid';
$_GET = ['cid' => 'test-student', 'num' => $settings['action'], 'getkey' => $settings['key']];
$micServiceIP = $settings['upstream'];
$dbh = new PDO('sqlite:../users.sqlite');
$dbh->sqliteCreateFunction('NOW', fn() => '2026-09-14 00:00:00');
require '../include/pdo.php';
PHP
);
$db = new PDO("sqlite:$root/users.sqlite");
$db->exec('CREATE TABLE users (user_id TEXT PRIMARY KEY, email TEXT, nick TEXT, school TEXT, defunct TEXT, register_num INTEGER, accesstime TEXT)');
$cases = [
    ['approve-timeout', '1', 'valid', 0, 'stall'],
    ['approve-success', '1', 'valid', 0, '200'],
    ['approve-mail-error', '1', 'valid', 0, '503'],
    ['reject-mail-error', '0', 'valid', 0, '503'],
    ['already-approved-reject', '0', 'valid', 1, 'none'],
    ['invalid-action', '9', 'valid', 0, 'none'],
    ['invalid-key', '1', 'invalid', 0, 'none'],
    ['database-write-error', '1', 'valid', 0, 'database-error'],
    ['no-email', '1', 'valid', 0, 'empty'],
];
$failed = false;
try {
    foreach ($cases as [$name, $action, $key, $initial, $mode]) {
        $db->exec('DELETE FROM users');
        $db->exec('DROP TRIGGER IF EXISTS fail_update');
        if ($mode === 'database-error') {
            $db->exec("CREATE TRIGGER fail_update BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'simulated write failure'); END");
        }
        $insert = $db->prepare('INSERT INTO users VALUES (?, ?, ?, ?, ?, ?, NULL)');
        $insert->execute(['test-student', $mode === 'empty' ? '' : 'test@example.invalid', 'Test', 'Test', 'Y', $initial]);
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        check($server !== false, $error);
        stream_set_blocking($server, false);
        file_put_contents("$root/case.json", json_encode([
            'action' => $action, 'key' => $key,
            'upstream' => stream_socket_get_name($server, false),
        ]));
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $root],
            [0 => ['pipe', 'r'], 1 => ['file', "$root/output", 'w'], 2 => ['file', "$root/errors", 'w']], $pipes);
        fclose($pipes[0]);
        $start = microtime(true);
        $connected = false;
        $unlocked = false;
        $client = null;
        do {
            if (!$connected && ($client = @stream_socket_accept($server, 0))) {
                $connected = true;
                stream_set_timeout($client, 1);
                $headers = '';
                while (($line = fgets($client)) !== false && $line !== "\r\n") $headers .= $line;
                preg_match('/Content-Length:\s*(\d+)/i', $headers, $length);
                $body = '';
                while (strlen($body) < (int)($length[1] ?? 0)) {
                    $part = fread($client, (int)$length[1] - strlen($body));
                    if ($part === false || $part === '') break;
                    $body .= $part;
                }
                check(str_contains($headers, 'POST /mail/sendEmail/'), "$name: expected mail request");
                check(str_contains($body, 'email=test%40example.invalid'), "$name: email payload");
                $session = fopen("$root/sessions/sess_approval-regression", 'r+');
                $unlocked = flock($session, LOCK_EX | LOCK_NB);
                if ($unlocked) flock($session, LOCK_UN);
                fclose($session);
                if ($mode !== 'stall') {
                    fwrite($client, "HTTP/1.1 $mode Test\r\nContent-Length: 2\r\nConnection: close\r\n\r\nOK");
                    fclose($client);
                    $client = null;
                }
            }
            usleep(10000);
            $status = proc_get_status($process);
        } while ($status['running'] && microtime(true) - $start < 6);
        $elapsed = microtime(true) - $start;
        if ($status['running']) proc_terminate($process, 9);
        proc_close($process);
        if (is_resource($client)) fclose($client);
        fclose($server);
        $row = $db->query('SELECT * FROM users')->fetch(PDO::FETCH_ASSOC);
        $output = file_get_contents("$root/output");
        $errors = file_get_contents("$root/errors");
        printf("%s: %.3fs, mail=%s, session-unlocked=%s\n", $name, $elapsed, $connected ? 'yes' : 'no', $unlocked ? 'yes' : 'no');
        check(!$status['running'] && $elapsed < 4.5, "$name: approval must finish even when mail hangs");
        check(!str_contains($errors . $output, 'Fatal error'), "$name: no fatal errors");
        if (in_array($mode, ['none', 'empty', 'database-error'], true)) {
            check(!$connected, "$name: must not send mail");
        } else {
            check($connected && $unlocked, "$name: release session before mail I/O");
        }
        if ($mode === 'none' || $mode === 'database-error') {
            check($row && (int)$row['register_num'] === $initial && $row['defunct'] === 'Y', "$name: leave user unchanged");
        } elseif ($action === '1') {
            check($row && (int)$row['register_num'] === 1 && $row['defunct'] === 'N', "$name: approval persisted");
        } else {
            check($row === false, "$name: pending rejection persisted");
        }
        if (in_array($mode, ['stall', '503'], true)) {
            check(str_contains($output, '邮件通知失败'), "$name: report notification failure separately");
        }
        if ($mode === 'database-error') check(str_contains($output, '审批保存失败'), "$name: do not report a failed write as success");
    }
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    $failed = true;
} finally {
    $db = null;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
exit($failed ? 1 : 0);
