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
mkdir($root . '/template/sta_sty', 0700, true);
mkdir($root . '/tmp', 0700);
foreach (['contestrank.php', 'contestrank-oi.php', 'contestrank2.php', 'contestrank3.php', 'ranklist.php', 'problemset.php', 'userinfo.php', 'include/cache_start.php', 'include/cache_end.php', 'include/cache_layer.php', 'include/memcache.php', 'include/academic_directory.php'] as $file) {
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
$OJ_FREE_PRACTICE = false;
function pdo_query($sql, ...$args) {
    file_put_contents(__DIR__ . '/../queries.log', json_encode([$sql, $args]) . "\n", FILE_APPEND);
    $state = json_decode(file_get_contents(__DIR__ . '/../state.json'), true);
    $cid = $args[0] ?? 0;
    if ($sql === 'SELECT ? AS first, ? AS second') return [$args];
    if (strpos($sql, 'SELECT t.user_id') !== false) return [[
        'user_id' => 'student', 'school' => json_encode($args), 'nick' => 'Student',
        'solved' => $state['score'], 'submit' => 10, 'sim_num' => 0]];
    if (strpos($sql, 'as `mycount`') !== false) return [['mycount' => 1]];
    if (strpos($sql, 'FROM `collegiate`') !== false) return [['id' => 1, 'name' => 'Test College']];
    if (strpos($sql, 'FROM `schoolList`') !== false) return [['y' => '2026']];
    if (strpos($sql, 'FROM schoolList') !== false) return [[24]];
    if (strpos($sql, 'volume from users') !== false) return [[1]];
    if (strpos($sql, 'SELECT `problem_id` FROM `solution`') !== false) return $state['progress'] ? [[1001]] : [];
    if (strpos($sql, 'COUNT(*) AS total FROM problem') !== false) return [['total' => 1]];
    if (strpos($sql, 'SELECT `problem_id`,`title`,`source`') !== false) return [[
        'problem_id' => 1001, 'title' => json_encode($args), 'source' => '', 'submit' => 10,
        'accepted' => $state['score'], 'defunct' => 'N']];
    if (strpos($sql, '`school`,`email`,`nick`,`qq`') !== false) return $cid === 'missing' ? [] : [[
        'school' => 'Test', 'email' => 'private@example.test', 'nick' => $cid, 'qq' => '']];
    if (strpos($sql, 'SELECT SUM(c.sim)') !== false) return [['sim_num' => 2]];
    if (strpos($sql, 'SUM(problem_id>0)') !== false) return [['ac' => $state['score'], 'Submit' => 10]];
    if (strpos($sql, 'UPDATE `users`') !== false) return 1;
    if (strpos($sql, 'as `Rank`') !== false) return [[1]];
    if (strpos($sql, 'FROM `loginlog`') !== false) return [['user_id' => $cid, 'password' => 'test-only']];
    if (strpos($sql, 'SELECT result,count(1)') !== false) return [[4, $state['score']]];
    if (strpos($sql, 'SELECT UNIX_TIMESTAMP') !== false) return [['md' => 1000, 'c' => 10, 'ac' => $state['score']]];
    if (strpos($sql, 'SELECT `solution_id`') !== false) return [['solution_id' => 1,
        'user_id' => 'alice', 'problem_id' => 1001, 'in_date' => date('Y-m-d H:i:s', $state['start'] + 100), 'result' => $state['result']]];
    if (strpos($sql, 'SELECT a.user_id,nick') !== false) return [['user_id' => 'alice', 'nick' => "Alice $cid"]];
    if (strpos($sql, 'SELECT `problem_id`,`num`') !== false) return [['problem_id' => 1001, 'num' => 0]];
    if (strpos($sql, 'select count(distinct') !== false) return [[1]];

    if (strpos($sql, '`start_time`') !== false) return $cid == 999 ? [] : [[
        'start_time' => date('Y-m-d H:i:s', $state['start']),
        'end_time' => date('Y-m-d H:i:s', $state['end']), 'title' => "Contest $cid"]];
    if (strpos($sql, '`IPprivate`') !== false) return [[$state['private']]];
    if (strpos($sql, 'SELECT team') !== false) return [[0]];
    if (strpos($sql, 'as pbc') !== false) return [['pbc' => 1]];
    if (strpos($sql, 'RIGHT JOIN sim') !== false) return [['user_id' => 'alice', 'num' => 2]];
    if (strpos($sql, 'FROM `linkI`') !== false) return [['userid' => 'alice', 'cnt' => 3]];
    if (strpos($sql, 'select num,user_id') !== false) return $state['result'] == 4 ? [['num' => 0, 'user_id' => 'alice']] : [];
    if (strpos($sql, 'join users') !== false) return [[
        'user_id' => 'alice', 'school' => 'Test', 'nick' => "Alice $cid", 'result' => $state['result'],
        'num' => 0, 'pass_rate' => $state['result'] === 4 ? 1 : 0.5,
        'in_date' => strpos($sql, 'unix_timestamp(solution.in_date)') !== false ? 100 : date('Y-m-d H:i:s', $state['start'] + 100)]];
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
file_put_contents($root . '/include/my_func.inc.php', '<?php function is_valid_user_name($user) { return preg_match("/^[a-z]+$/", $user); }');
foreach (['contestrank-oi', 'contestrank2'] as $page) {
    file_put_contents($root . '/template/test/' . $page . '.php', '<?php echo json_encode(["users" => $U, "first" => $first_blood, "submissions" => $solution_json ?? null]);');
}
file_put_contents($root . '/template/test/contestrank3.php', '<?php echo json_encode(["problems" => $problem_num, "teams" => $team_num, "lock" => $lock]);');
file_put_contents($root . '/template/test/ranklist.php', '<?php echo json_encode(["ranks" => $view_rank]);');
file_put_contents($root . '/template/test/problemset.php', '<?php echo json_encode(["problems" => $view_problemset, "count" => $view_total_count]);');
file_put_contents($root . '/template/sta_sty/userinfo.php', '<?php echo json_encode(["user" => $user, "email" => $email, "ac" => $AC, "stats" => $view_userstat, "chart" => $chart_data_ac]);');
file_put_contents($root . '/query.php', '<?php require "include/memcache.php"; echo json_encode(mysql_query_cache("SELECT ? AS first, ? AS second", $_GET["a"], $_GET["b"]));');
file_put_contents($root . '/template/test/error.php', '<?php echo json_encode(["error" => $view_errors]);');
file_put_contents($root . '/router.php', <<<'FIXTURE'
<?php
session_start();
if (($_GET['viewer'] ?? '') !== 'anonymous') $_SESSION['cache-test_user_id'] = $_GET['viewer'] ?? 'guest';
if (isset($_GET['admin'])) $_SESSION['cache-test_administrator'] = true;
$module = $_GET['module'] ?? 'contestrank';
if (!in_array($module, ['contestrank', 'contestrank-oi', 'contestrank2', 'contestrank3', 'ranklist', 'problemset', 'userinfo', 'query'], true)) exit;
require $module . '.php';
FIXTURE
);
$server = null;
try {
    foreach (['file' => 0, 'apcu' => 1] as $backend => $enabled) {
        $state = ['start' => time() - 1000, 'end' => time() + 1000, 'result' => 4, 'private' => '0', 'score' => 1, 'progress' => false];
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
        // The remaining modules must reuse raw data without sharing rendered user state.
        $state['start'] = time() - 1000;
        $state['end'] = time() + 1000;
        $state['result'] = 4;
        file_put_contents($root . '/state.json', json_encode($state));
        foreach (['contestrank-oi', 'contestrank2'] as $module) {
            $moduleCid = $module === 'contestrank-oi' ? 10 : 11;
            $before = $queries();
            $row = $request($moduleCid, 'alice', ['module' => $module]);
            $moduleCold = $queries() - $before;
            $before = $queries();
            check($request($moduleCid, 'bob', ['module' => $module]) === $row, "$module output stays the same");
            check($queries() - $before === 1, "$module warm request only reads contest timing");
            check($row['first'][0] === 'alice', "$module first blood");
            if ($module === 'contestrank-oi') {
                check($row['users'][0]['total'] === 100, 'OI full score');
                check($request($moduleCid, 'bob', ['module' => $module, 'lock' => 1])['users'][0]['total'] === 0, 'OI freeze after cache lookup');
            } else {
                check(json_decode($row['submissions'], true)[0]['in_date'] === 100, 'Replay keeps relative submission time');
            }
            printf("PASS %s %s: cold=%d, warm=1 SQL\n", $backend, $module, $moduleCold);
        }
        check($request(20, 'alice', ['module' => 'contestrank3', 'type' => 'json', 'list' => 'submit'])['error'] === 'Contest Not Finished!', 'Rolling board must wait until contest ends');
        $state['end'] = time() - 100;
        file_put_contents($root . '/state.json', json_encode($state));
        foreach (['submit', 'team', 'page'] as $list) {
            $params = ['module' => 'contestrank3'] + ($list === 'page' ? [] : ['type' => 'json', 'list' => $list]);
            $before = $queries();
            $row = $request(20, 'alice', $params);
            $moduleCold = $queries() - $before;
            $before = $queries();
            check($request(20, 'bob', $params) === $row, 'Rolling board output');
            check($queries() - $before === 1, "Rolling $list warm query count");
            if ($list === 'submit') check($row[0]['alphabetID'] === 'A' && $row[0]['resultID'] === 0, 'Rolling result mapping');
            printf("PASS %s rolling-%s: cold=%d, warm=1 SQL\n", $backend, $list, $moduleCold);
        }
        $state['end'] = time() + 1000;
        file_put_contents($root . '/state.json', json_encode($state));
        check($request(20, 'bob', ['module' => 'contestrank3', 'type' => 'json', 'list' => 'submit'])['error'] === 'Contest Not Finished!', 'End-time check stays live after warming rolling data');
        foreach (['ranklist' => ['school' => 'CS', 'prefix' => 'a b'], 'problemset' => ['search' => 'a b']] as $module => $filter) {
            $params = ['module' => $module] + $filter;
            $before = $queries();
            $row = $request(1, 'anonymous', $params);
            $moduleCold = $queries() - $before;
            $before = $queries();
            check($request(1, 'anonymous', $params) === $row, "$module filtered output");
            check($queries() === $before, "$module repeated filter must hit query cache");
            $changed = $params;
            $changed[$module === 'ranklist' ? 'prefix' : 'search'] = 'different';
            check($request(1, 'anonymous', $changed) !== $row, "$module filter cache isolation");
            printf("PASS %s %s: cold=%d, warm=0 SQL\n", $backend, $module, $moduleCold);
        }
        $params = ['module' => 'problemset', 'search' => 'role-test'];
        $request(1, 'anonymous', $params + ['admin' => 1]);
        $before = $queries();
        $request(1, 'anonymous', $params);
        check($queries() - $before === 2, 'Problem visibility SQL separates administrator and public caches');
        $before = $request(1, 'alice', $params);
        $state['progress'] = true;
        file_put_contents($root . '/state.json', json_encode($state));
        $after = $request(1, 'alice', $params);
        check($before['problems'][0][0] !== $after['problems'][0][0] && strpos($after['problems'][0][0], '>Y<') !== false, 'Personal AC marker stays live');
        $params = ['module' => 'userinfo', 'user' => 'alice'];
        $before = $queries();
        $profile = $request(1, 'anonymous', $params);
        $moduleCold = $queries() - $before;
        $before = $queries();
        $logged = $request(1, 'bob', $params);
        check($queries() - $before === 2, 'Profile keeps identity and write-source aggregates live');
        check($profile['email'] === '' && $logged['email'] === 'private@example.test', 'Profile privacy remains per viewer');
        check($request(1, 'bob', ['module' => 'userinfo', 'user' => 'charlie'])['user'] === 'charlie', 'Profile user isolation');
        check($request(1, 'bob', ['module' => 'userinfo', 'user' => 'missing'])['error'] === 'No such User!', 'Profile existence remains live');
        printf("PASS %s userinfo: cold=%d, warm=2 SQL\n", $backend, $moduleCold);
        $a = $request(1, 'bob', ['module' => 'query', 'a' => 'a b', 'b' => 'c']);
        $b = $request(1, 'bob', ['module' => 'query', 'a' => 'a', 'b' => 'b c']);
        check($a === [['a b', 'c']] && $b === [['a', 'b c']], 'Cache key preserves parameter boundaries');
        $state['score'] = 2;
        $state['result'] = 6;
        file_put_contents($root . '/state.json', json_encode($state));
        sleep(6);
        $fresh = $request(1, 'bob', $params);
        check($fresh['ac'] === 2 && $fresh['stats'][0][1] === 2 && $fresh['chart'][1000] === 2, 'Profile statistics refresh after expiry');
        $fresh = $request(10, 'bob', ['module' => 'contestrank-oi']);
        check($fresh['users'][0]['total'] === 50, 'OI partial score refreshes after expiry');
        $fresh = $request(11, 'bob', ['module' => 'contestrank2']);
        check(json_decode($fresh['submissions'], true)[0]['result'] === 6, 'Replay data refreshes after expiry');
        $fresh = $request(1, 'anonymous', ['module' => 'ranklist', 'school' => 'CS', 'prefix' => 'a b']);
        check(strpos($fresh['ranks'][0][4], '>2</a>') !== false, 'Filtered rank refreshes after expiry');
        $fresh = $request(1, 'anonymous', ['module' => 'problemset', 'search' => 'a b']);
        check(strpos($fresh['problems'][0][4], '>2</a>') !== false, 'Problem search refreshes after expiry');
        $state['end'] = time() - 100;
        file_put_contents($root . '/state.json', json_encode($state));
        $fresh = $request(20, 'bob', ['module' => 'contestrank3', 'type' => 'json', 'list' => 'submit']);
        check($fresh[0]['resultID'] === 4, 'Rolling submission refreshes after expiry');
        printf("PASS %s: parameter boundaries, visibility, live progress, all module TTLs\n", $backend);
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
