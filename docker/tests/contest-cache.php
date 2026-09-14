<?php
// Run with PHP 8.1 + APCu: php docker/tests/contest-cache.php
// Docker (from repo root): docker run --rm --network none --entrypoint php -v "$PWD:/workspace:ro" hnieoj-unified-web /workspace/docker/tests/contest-cache.php
// Uses an isolated HTTP server, synthetic query results and temporary caches; no database.
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$root = sys_get_temp_dir() . '/oj-contest-cache-test-' . bin2hex(random_bytes(6));
mkdir($root . '/include', 0700, true);
mkdir($root . '/template/test', 0700, true);
mkdir($root . '/tmp', 0700);
foreach (['contestrank.php', 'include/cache_start.php', 'include/cache_end.php', 'include/cache_layer.php', 'include/memcache.php'] as $file) {
    copy(__DIR__ . '/../../web/' . $file, $root . '/' . $file);
}
foreach (['setlang.php', 'const.inc.php', 'my_func.inc.php'] as $file) file_put_contents($root . '/include/' . $file, '<?php');
file_put_contents($root . '/include/db_info.inc.php', <<<'FIXTURE'
<?php
$OJ_NAME = 'cache-test';
$OJ_MEMCACHE = false;
$OJ_TEMPLATE = 'test';
$MSG_CONTEST = $MSG_RANKLIST = '';
$OJ_RANK_LOCK_PERCENT = (float)($_GET['lock'] ?? 0);
$OJ_CE_PENALTY = false;
function pdo_query($sql, ...$args) {
    file_put_contents(__DIR__ . '/../queries.log', json_encode([$sql, $args]) . "\n", FILE_APPEND);
    $state = json_decode(file_get_contents(__DIR__ . '/../state.json'), true);
    $cid = $args[0];
    if (strpos($sql, '`start_time`') !== false) return $cid == 999 ? [] : [[
        'start_time' => date('Y-m-d H:i:s', $state['start']),
        'end_time' => date('Y-m-d H:i:s', $state['end']), 'title' => "Contest $cid"]];
    if (strpos($sql, '`IPprivate`') !== false) return [[$state['private']]];
    if (strpos($sql, 'SELECT team') !== false) return [[0]];
    if (strpos($sql, 'as pbc') !== false) return [['pbc' => 1]];
    if (strpos($sql, 'RIGHT JOIN sim') !== false) return [['user_id' => 'alice', 'num' => 2]];
    if (strpos($sql, 'FROM `linkI`') !== false) return [['userid' => 'alice', 'cnt' => 3]];
    if (strpos($sql, 'select num,user_id') !== false) return $state['result'] == 4 ? [['num' => 0, 'user_id' => 'alice']] : [];
    if (strpos($sql, 'inner join users') !== false) return [[
        'user_id' => 'alice', 'school' => 'Test', 'nick' => "Alice $cid", 'result' => $state['result'],
        'num' => 0, 'in_date' => date('Y-m-d H:i:s', $state['start'] + 100)]];
    throw new RuntimeException('Unexpected SQL: ' . $sql);
}
FIXTURE
);
file_put_contents($root . '/template/test/contestrank.php', <<<'FIXTURE'
<?php
header('Content-Type: application/json');
echo json_encode(['users' => $U, 'first' => $first_blood, 'private' => $IPprivate,
    'viewer' => $_SESSION['cache-test_user_id'] ?? null, 'apcu' => $OJ_APCU_OK]);
FIXTURE
);
file_put_contents($root . '/template/test/error.php', '<?php echo json_encode(["error" => $view_errors]);');
file_put_contents($root . '/router.php', <<<'FIXTURE'
<?php
session_start();
$_SESSION['cache-test_user_id'] = $_GET['viewer'] ?? 'guest';
require 'contestrank.php';
FIXTURE
);
$server = null;
try {
    foreach (['file' => 0, 'apcu' => 1] as $backend => $enabled) {
        $state = ['start' => time() - 1000, 'end' => time() + 1000, 'result' => 4, 'private' => '0'];
        file_put_contents($root . '/state.json', json_encode($state));
        file_put_contents($root . '/queries.log', '');
        foreach (glob($root . '/tmp/hustoj*/*') ?: [] as $file) unlink($file);
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        check($socket !== false, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $server = proc_open([PHP_BINARY, '-d', 'apc.enabled=' . $enabled, '-d', 'apc.enable_cli=' . $enabled,
            '-d', 'disable_functions=' . ($enabled ? '' : 'apcu_fetch'),
            '-d', 'sys_temp_dir=' . $root . '/tmp', '-d', 'display_errors=1',
            '-S', $address, 'router.php'], [0 => ['pipe', 'r'], 1 => ['file', $root . '/server.log', 'a'],
            2 => ['file', $root . '/server.log', 'a']], $pipes, $root);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
            if ($ready) { fclose($ready); break; }
            usleep(20000);
        }
        check(isset($ready) && $ready !== false, 'HTTP server did not start');
        $sequence = 0;
        $request = function ($cid = 1, $viewer = 'alice', $extra = []) use ($address, &$sequence) {
            // A distinct URI deliberately misses HTML cache, exposing cross-request query reuse.
            $query = http_build_query($extra + ['cid' => $cid, 'viewer' => $viewer, 'request' => ++$sequence]);
            $body = file_get_contents('http://' . $address . '/?' . $query);
            $data = json_decode($body, true);
            check(is_array($data), 'Invalid endpoint output: ' . $body);
            return $data;
        };
        $queries = function () use ($root) { return count(file($root . '/queries.log', FILE_IGNORE_NEW_LINES)); };
        $first = $request();
        check($first['apcu'] === (bool)$enabled, "$backend backend selected");
        check($first['users'][0]['solved'] === 1 && $first['users'][0]['time'] === 100, 'AC score and penalty');
        check($first['users'][0]['sim_num'] === 2 && $first['users'][0]['link_num'] === 3, 'Auxiliary statistics');
        check($first['first'][0] === 'alice', 'First blood');
        $cold = $queries();
        $second = $request(1, 'bob');
        $warm = $queries() - $cold;
        check($second['users'] === $first['users'] && $second['viewer'] === 'bob', 'Shared data, separate viewer');
        check($cold === 8 && $warm === 3, "$backend: expected cold=8, warm=3 SQL calls; got cold=$cold, warm=$warm");
        $before = $queries();
        $other = $request(2);
        check($other['users'][0]['nick'] === 'Alice 2' && $queries() - $before === 8, 'Contest cache isolation');
        $locked = $request(1, 'bob', ['lock' => 1]);
        check($locked['users'][0]['solved'] === 0, 'Freeze is applied to cached raw submissions');
        $state['private'] = '3';
        $state['result'] = 6;
        file_put_contents($root . '/state.json', json_encode($state));
        $stale = $request();
        check($stale['private'] === '3', 'Control configuration remains uncached');
        check($stale['users'][0]['solved'] === 1, 'Submission result reused before TTL');
        sleep(6);
        $before = $queries();
        $fresh = $request();
        check($fresh['users'][0]['solved'] === 0 && $fresh['first'][0] === '', 'Result and empty first blood refresh after TTL');
        check($queries() - $before === 8, 'Expired queries are reloaded');
        $before = $queries();
        $request();
        check($queries() - $before === 3, 'Empty query results are cached');
        check($request(999)['error'] === 'No Such Contest', 'Missing contest remains rejected');
        $state['start'] = time() + 100;
        file_put_contents($root . '/state.json', json_encode($state));
        check($request()['error'] === 'Contest Not Started!', 'Start time changes take effect immediately');
        printf("PASS %s: cold=%d SQL, warm=%d SQL; viewer/contest isolation, freeze, TTL, empty results, control reads\n", $backend, $cold, $warm);
        proc_terminate($server);
        proc_close($server);
        $server = null;
    }
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
