<?php
// 注册验证码邮件 endpoint 集成测试（无网络、无真实邮件、无真实凭据）。
//
// 运行方式（在仓库根目录，使用本机已安装的 PHP 8.1 镜像）：
//   docker run --rm --network none -v "$PWD:/workspace:ro" --entrypoint php \
//     hnieoj-unified-web:latest /workspace/docker/tests/sendemail-registration.php
//
// 测试直接执行真实的 web/sendemail.php，但在 `php -n` 子进程中用 userland stub 替换
// curl 边界（不加载 curl 扩展），并用文件 journal 记录缓存读写；session 使用真实
// 文件 session（隔离 save_path），以便验证验证码/冷却只在发送成功后提交。
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

function se_check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function se_json_lines($path)
{
    if (!is_file($path)) {
        return array();
    }
    $rows = array();
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            $rows[] = $decoded;
        }
    }
    return $rows;
}

function se_rmtree($root)
{
    if (!is_dir($root)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}

function se_run_php(array $command, $cwd, array $env, &$exit)
{
    $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $process = proc_open($command, $descriptors, $pipes, $cwd, $env);
    se_check(is_resource($process), 'start PHP subprocess');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    return array($stdout, $stderr);
}

function se_session_file($root, $id)
{
    return $root . '/sessions/sess_' . $id;
}

function se_session_dump($root, $id)
{
    if (!is_file(se_session_file($root, $id))) {
        return null;
    }
    $exit = 0;
    list($stdout) = se_run_php(
        array(PHP_BINARY, '-n',
            '-d', 'session.save_path=' . $root . '/sessions',
            '-d', 'session.gc_probability=0',
            $root . '/session_dump.php', $id),
        $root,
        getenv(),
        $exit
    );
    $data = json_decode(trim($stdout), true);
    return is_array($data) ? $data : null;
}

function se_seed_session($root, $id, array $data)
{
    $exit = 0;
    se_run_php(
        array(PHP_BINARY, '-n',
            '-d', 'session.save_path=' . $root . '/sessions',
            '-d', 'session.gc_probability=0',
            $root . '/session_seed.php', $id, json_encode($data)),
        $root,
        getenv(),
        $exit
    );
    se_check($exit === 0, 'seed session ' . $id);
    se_check(se_session_dump($root, $id) === $data, 'seeded session readable');
}

function se_run($root, array $options)
{
    static $sequence = 0;
    $sequence++;
    $id = isset($options['session']) ? $options['session'] : 'se-' . bin2hex(random_bytes(8));
    $mode = isset($options['mode']) ? $options['mode'] : 'ok';
    $env = getenv();
    unset($env['TENCENTCLOUD_SECRET_ID'], $env['TENCENTCLOUD_SECRET_KEY']);
    $secretId = 'AKIDtest-placeholder-0000000000000000';
    $secretKey = 'test-secret-placeholder-000000000000';
    if (!array_key_exists('creds', $options) || $options['creds']) {
        $env['TENCENTCLOUD_SECRET_ID'] = $secretId;
        $env['TENCENTCLOUD_SECRET_KEY'] = $secretKey;
    }
    $errlog = $root . '/err-' . $sequence . '.log';
    $env['SE_GET_EMAIL'] = (string)(isset($options['email']) ? $options['email'] : '');
    $env['SE_REMOTE_ADDR'] = (string)(isset($options['ip']) ? $options['ip'] : '127.0.0.1');
    $env['SE_SESSION_ID'] = $id;
    $env['SE_CURL_MODE'] = $mode;
    $env['SE_ERRLOG'] = $errlog;
    $env['SE_CURL_JOURNAL'] = $root . '/curl-' . $sequence . '.log';
    $env['SE_CACHE_DIR'] = $root . '/cache';
    $env['SE_CACHE_JOURNAL'] = $root . '/cache-' . $sequence . '.log';

    $exit = 0;
    list($stdout, $stderr) = se_run_php(
        array(PHP_BINARY, '-n',
            '-d', 'session.save_path=' . $root . '/sessions',
            '-d', 'session.gc_probability=0',
            '-d', 'error_reporting=E_ALL',
            '-d', 'auto_prepend_file=' . $root . '/prepend.php',
            $root . '/sendemail.php'),
        $root,
        $env,
        $exit
    );

    return array(
        'session_id' => $id,
        'exit' => $exit,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'log' => is_file($errlog) ? (string)file_get_contents($errlog) : '',
        'curl' => se_json_lines($env['SE_CURL_JOURNAL']),
        'cache' => se_json_lines($env['SE_CACHE_JOURNAL']),
        'session' => se_session_dump($root, $id),
        'secrets' => array($secretId, $secretKey),
    );
}

function se_cache_sets($result)
{
    return array_values(array_filter($result['cache'], function ($entry) {
        return isset($entry['op']) && $entry['op'] === 'set';
    }));
}

function se_assert_no_secret_leak($result, $label)
{
    foreach ($result['secrets'] as $secret) {
        if (!is_string($secret) || $secret === '') {
            continue;
        }
        foreach (array('stdout', 'stderr', 'log') as $field) {
            se_check(strpos((string)$result[$field], $secret) === false, $label . ': secret leaked via ' . $field);
        }
        se_check(strpos(json_encode($result['curl']), $secret) === false, $label . ': secret leaked via curl journal');
        se_check(strpos(json_encode($result['cache']), $secret) === false, $label . ': secret leaked via cache journal');
    }
}

function se_setup($root)
{
    $repo = dirname(__DIR__, 2);
    mkdir($root . '/include', 0700, true);
    mkdir($root . '/sessions', 0700, true);
    mkdir($root . '/cache', 0700, true);
    copy($repo . '/web/sendemail.php', $root . '/sendemail.php');
    copy($repo . '/web/include/cache_layer.php', $root . '/include/cache_layer.php');
    file_put_contents($root . '/include/my_func.inc.php', "<?php\n");
    file_put_contents($root . '/session_dump.php', <<<'PHP'
<?php
session_id($argv[1]);
session_start();
echo json_encode($_SESSION);
session_write_close();
PHP
    );
    file_put_contents($root . '/session_seed.php', <<<'PHP'
<?php
session_id($argv[1]);
session_start();
$_SESSION = json_decode($argv[2], true);
session_write_close();
PHP
    );
    file_put_contents($root . '/prepend.php', <<<'PHP'
<?php
// `php -n` 边界 stub：真实端点代码，替换 curl 扩展与缓存层；session 使用真实文件实现。
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$__seErrlog = getenv('SE_ERRLOG');
if (is_string($__seErrlog) && $__seErrlog !== '') {
    ini_set('error_log', $__seErrlog);
}
error_reporting(E_ALL);

if (extension_loaded('curl')) {
    fwrite(STDERR, "sendemail-registration must run the endpoint with php -n (curl extension loaded)\n");
    exit(2);
}

foreach (array(
    'CURLOPT_URL' => 10002,
    'CURLOPT_RETURNTRANSFER' => 19913,
    'CURLOPT_POST' => 47,
    'CURLOPT_POSTFIELDS' => 10015,
    'CURLOPT_HTTPHEADER' => 10023,
    'CURLOPT_TIMEOUT' => 13,
    'CURLOPT_SSL_VERIFYPEER' => 64,
    'CURLOPT_SSL_VERIFYHOST' => 81,
    'CURLOPT_VERBOSE' => 41,
    'CURLOPT_STDERR' => 10037,
    'CURLINFO_HTTP_CODE' => 2097154,
) as $__seName => $__seValue) {
    if (!defined($__seName)) {
        define($__seName, $__seValue);
    }
}

$GLOBALS['__se_curl'] = array('opts' => array(), 'http' => 0, 'errno' => 0);

function curl_init($url = null)
{
    $GLOBALS['__se_curl'] = array('opts' => array(), 'http' => 0, 'errno' => 0);
    if (is_string($url) && $url !== '') {
        $GLOBALS['__se_curl']['opts'][CURLOPT_URL] = $url;
    }
    return 1;
}

function curl_setopt($handle, $option, $value)
{
    $GLOBALS['__se_curl']['opts'][$option] = $value;
    return true;
}

function curl_setopt_array($handle, array $options)
{
    foreach ($options as $option => $value) {
        $GLOBALS['__se_curl']['opts'][$option] = $value;
    }
    return true;
}

function curl_exec($handle)
{
    $options = $GLOBALS['__se_curl']['opts'];
    $headers = isset($options[CURLOPT_HTTPHEADER]) ? (array)$options[CURLOPT_HTTPHEADER] : array();
    $authorization = '';
    foreach ($headers as $header) {
        if (stripos($header, 'Authorization:') === 0) {
            $authorization = trim(substr($header, strlen('Authorization:')));
        }
    }
    $parts = preg_split('/\s+/', $authorization);
    $signedHeaders = '';
    if (preg_match('/SignedHeaders=([^,]+)/', $authorization, $matches)) {
        $signedHeaders = $matches[1];
    }
    $mode = getenv('SE_CURL_MODE');
    $mode = is_string($mode) && $mode !== '' ? $mode : 'ok';
    $journal = array(
        'mode' => $mode,
        'url' => isset($options[CURLOPT_URL]) ? $options[CURLOPT_URL] : '',
        'timeout' => isset($options[CURLOPT_TIMEOUT]) ? $options[CURLOPT_TIMEOUT] : null,
        'verify_peer' => isset($options[CURLOPT_SSL_VERIFYPEER]) ? $options[CURLOPT_SSL_VERIFYPEER] : null,
        'verify_host' => isset($options[CURLOPT_SSL_VERIFYHOST]) ? $options[CURLOPT_SSL_VERIFYHOST] : null,
        'verbose' => array_key_exists(CURLOPT_VERBOSE, $options),
        'stderr' => array_key_exists(CURLOPT_STDERR, $options),
        'auth_scheme' => isset($parts[0]) ? $parts[0] : '',
        'auth_credential' => strpos($authorization, 'Credential=') !== false,
        'auth_signature' => strpos($authorization, 'Signature=') !== false,
        'signed_headers' => $signedHeaders,
        'body' => isset($options[CURLOPT_POSTFIELDS]) ? (string)$options[CURLOPT_POSTFIELDS] : '',
    );
    $journalPath = getenv('SE_CURL_JOURNAL');
    if (is_string($journalPath) && $journalPath !== '') {
        file_put_contents($journalPath, json_encode($journal) . "\n", FILE_APPEND);
    }

    switch ($mode) {
        case 'provider_error':
            $GLOBALS['__se_curl']['http'] = 200;
            return json_encode(array('Response' => array(
                'Error' => array('Code' => 'FailedOperation.SendEmail', 'Message' => 'provider-secret-message'),
                'RequestId' => 'req-provider-1',
            )));
        case 'provider_error_no_code':
            // 关键回归：Response.Error 存在但没有 Code，且同时带有 MessageId——
            // 必须按 Error 的存在直接判定失败，不能被“无 Code”或 MessageId 误导为成功。
            $GLOBALS['__se_curl']['http'] = 200;
            return json_encode(array('Response' => array(
                'MessageId' => 'msg-should-not-commit',
                'Error' => array('Message' => 'provider-secret-message'),
                'RequestId' => 'req-provider-nocode-1',
            )));
        case 'http_error':
            $GLOBALS['__se_curl']['http'] = 500;
            return json_encode(array('Response' => array(
                'Error' => array('Code' => 'InternalError', 'Message' => 'provider-secret-message'),
                'RequestId' => 'req-http-1',
            )));
        case 'malformed':
            $GLOBALS['__se_curl']['http'] = 200;
            return 'not-json{{{';
        case 'empty':
            $GLOBALS['__se_curl']['http'] = 200;
            return '';
        case 'no_message_id':
            $GLOBALS['__se_curl']['http'] = 200;
            return json_encode(array('Response' => array('RequestId' => 'req-nomsg-1')));
        case 'network':
            $GLOBALS['__se_curl']['errno'] = 28;
            return false;
        default:
            $GLOBALS['__se_curl']['http'] = 200;
            return json_encode(array('Response' => array(
                'RequestId' => 'req-success-1',
                'MessageId' => 'msg-success-1',
            )));
    }
}

function curl_errno($handle)
{
    return $GLOBALS['__se_curl']['errno'];
}

function curl_error($handle)
{
    return $GLOBALS['__se_curl']['errno'] ? 'stub transport error' : '';
}

function curl_getinfo($handle, $option = null)
{
    $info = array(CURLINFO_HTTP_CODE => $GLOBALS['__se_curl']['http']);
    if ($option === null) {
        return $info;
    }
    return isset($info[$option]) ? $info[$option] : 0;
}

function curl_close($handle)
{
    return true;
}

function __se_cache_file($key)
{
    return getenv('SE_CACHE_DIR') . '/' . md5($key) . '.json';
}

function cache_get($key)
{
    $journal = getenv('SE_CACHE_JOURNAL');
    if (is_string($journal) && $journal !== '') {
        file_put_contents($journal, json_encode(array('op' => 'get', 'key' => $key)) . "\n", FILE_APPEND);
    }
    $file = __se_cache_file($key);
    if (!is_file($file)) {
        return false;
    }
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data) || !isset($data['exp']) || (int)$data['exp'] <= time()) {
        return false;
    }
    return $data['val'];
}

function cache_set($key, $val, $ttl = 60)
{
    $journal = getenv('SE_CACHE_JOURNAL');
    if (is_string($journal) && $journal !== '') {
        file_put_contents($journal, json_encode(array('op' => 'set', 'key' => $key, 'ttl' => (int)$ttl)) . "\n", FILE_APPEND);
    }
    file_put_contents(__se_cache_file($key), json_encode(array(
        'key' => $key,
        'val' => $val,
        'exp' => time() + (int)$ttl,
    )));
    return true;
}

$__seEmail = getenv('SE_GET_EMAIL');
$_GET = array('email' => is_string($__seEmail) ? $__seEmail : '');
$_SERVER['REQUEST_METHOD'] = 'GET';
$__seRemote = getenv('SE_REMOTE_ADDR');
$_SERVER['REMOTE_ADDR'] = is_string($__seRemote) && $__seRemote !== '' ? $__seRemote : '127.0.0.1';
$__seSession = getenv('SE_SESSION_ID');
if (is_string($__seSession) && $__seSession !== '') {
    session_id($__seSession);
}
PHP
    );
}

$repo = dirname(__DIR__, 2);
$root = sys_get_temp_dir() . '/oj-sendemail-test-' . bin2hex(random_bytes(6));
$successText = '验证码已发送至您的邮箱，请查收。';
$failText = '验证码发送失败，请稍后再试。';
$unavailableText = '邮件服务暂时不可用，请稍后再试。';
$rateText = '发送过于频繁，请 3 分钟后再试。';
$holdText = '验证码仍在有效期内';

try {
    mkdir($root, 0700, true);
    se_setup($root);

    // ------------------------------------------------------------------
    // 静态约束：校园网 exec 已删除、无 verbose/原始响应日志、random_int、SES 设置保留
    // ------------------------------------------------------------------
    $source = (string)file_get_contents($repo . '/web/sendemail.php');
    se_check(strpos($source, 'getNetwork.py') === false && strpos($source, 'exec("python3') === false && strpos($source, "exec('python3") === false, 'obsolete campus network exec removed');
    se_check(strpos($source, 'CURLOPT_VERBOSE') === false && strpos($source, 'CURLOPT_STDERR') === false, 'verbose curl logging removed');
    se_check(strpos($source, 'print_r($response') === false, 'raw response logging removed');
    se_check(strpos($source, 'random_int') !== false && strpos($source, 'mt_rand') === false, 'verification code uses random_int');
    se_check(strpos($source, 'session_write_close();') === false, 'session lock held through the bounded send');
    se_check(strpos($source, 'CURLOPT_TIMEOUT => 15') !== false, 'bounded 15s SES timeout kept');
    se_check(strpos($source, 'CURLOPT_SSL_VERIFYPEER => true') !== false && strpos($source, 'CURLOPT_SSL_VERIFYHOST => 2') !== false, 'TLS verification kept');
    se_check(strpos($source, "'TemplateID' => 30733") !== false && strpos($source, 'ap-guangzhou') !== false, 'SES template/region unchanged');
    se_check(strpos($source, "'HNIEOJ <info@mail.hnieacm.com>'") !== false, 'SES from address unchanged');

    // AC1：compose 显式传入凭据；php-fpm pool 在 clear_env=yes 下放行同名 env。
    $compose = (string)file_get_contents($repo . '/compose.yaml');
    se_check(strpos($compose, 'TENCENTCLOUD_SECRET_ID: ${TENCENTCLOUD_SECRET_ID:-}') !== false, 'compose maps TENCENTCLOUD_SECRET_ID');
    se_check(strpos($compose, 'TENCENTCLOUD_SECRET_KEY: ${TENCENTCLOUD_SECRET_KEY:-}') !== false, 'compose maps TENCENTCLOUD_SECRET_KEY');
    $pool = (string)file_get_contents($repo . '/docker/conf/php/www.conf');
    se_check(strpos($pool, 'env[TENCENTCLOUD_SECRET_ID] = $TENCENTCLOUD_SECRET_ID') !== false, 'php-fpm whitelists TENCENTCLOUD_SECRET_ID');
    se_check(strpos($pool, 'env[TENCENTCLOUD_SECRET_KEY] = $TENCENTCLOUD_SECRET_KEY') !== false, 'php-fpm whitelists TENCENTCLOUD_SECRET_KEY');
    printf("PASS static: exec removed, verbose logging removed, random_int, SES settings, env wiring\n");

    // ------------------------------------------------------------------
    // AC1：缺少凭据安全失败，不写 session、不写冷却、不调用 SES
    // ------------------------------------------------------------------
    $missing = se_run($root, array('email' => 'nocreds@example.test', 'session' => 'nocreds-session', 'creds' => false));
    se_check(trim($missing['stdout']) === $unavailableText, 'missing credentials returns friendly unavailable');
    se_check(count($missing['curl']) === 0, 'missing credentials performs no SES call');
    se_check(count(se_cache_sets($missing)) === 0, 'missing credentials writes no cooldown');
    se_check(!isset($missing['session']['code']), 'missing credentials writes no session code');
    se_check(strpos($missing['log'], 'credentials are not configured') !== false, 'missing credentials logged without values');
    se_assert_no_secret_leak($missing, 'missing credentials');
    printf("PASS missing-credentials: no SES call, no session code, no cooldown\n");

    // ------------------------------------------------------------------
    // AC2 + AC3：成功发送——TC3 头/签名、15s/TLS、验证码与冷却仅在成功后提交 180s
    // ------------------------------------------------------------------
    $ip = '203.0.113.10';
    $email = 'alice@example.test';
    $sessionId = 'alice-success-session';
    $ok = se_run($root, array('email' => $email, 'ip' => $ip, 'session' => $sessionId, 'mode' => 'ok'));
    se_check(trim($ok['stdout']) === $successText, 'success response string preserved');
    se_check($ok['exit'] === 0, 'success endpoint exit code');
    $state = $ok['session'];
    se_check(is_array($state) && isset($state['code'], $state['time'], $state['email']), 'success commits session contract keys');
    se_check($state['email'] === $email, 'session email is recipient');
    se_check(preg_match('/^\d{6}$/', $state['code']) === 1, 'session code is 6 digits');
    $remaining = $state['time'] - time();
    se_check($remaining >= 175 && $remaining <= 181, 'session code valid about 180s; got ' . $remaining);
    se_check(count($ok['curl']) === 1, 'exactly one SES call on success');
    $curl = $ok['curl'][0];
    se_check($curl['url'] === 'https://ses.tencentcloudapi.com', 'SES endpoint host unchanged');
    se_check($curl['timeout'] === 15, 'bounded 15s curl timeout');
    se_check($curl['verify_peer'] === true && $curl['verify_host'] === 2, 'TLS verification enabled');
    se_check($curl['verbose'] === false && $curl['stderr'] === false, 'no verbose/stderr curl options');
    se_check($curl['auth_scheme'] === 'TC3-HMAC-SHA256', 'TC3 auth scheme');
    se_check($curl['auth_credential'] === true && $curl['auth_signature'] === true, 'TC3 credential and signature present');
    se_check($curl['signed_headers'] === 'content-type;host;x-tc-action', 'TC3 signed headers unchanged');
    $payload = json_decode($curl['body'], true);
    se_check(is_array($payload), 'SES request body is JSON');
    se_check($payload['Destination'] === array($email), 'request destination is recipient');
    se_check($payload['FromEmailAddress'] === 'HNIEOJ <info@mail.hnieacm.com>', 'SES from address unchanged');
    se_check(isset($payload['Template']['TemplateID']) && $payload['Template']['TemplateID'] === 30733, 'SES template unchanged');
    $templateData = json_decode($payload['Template']['TemplateData'], true);
    se_check(is_array($templateData) && isset($templateData['code']) && $templateData['code'] === $state['code'], 'emailed code matches committed session code');
    $sets = se_cache_sets($ok);
    se_check(count($sets) === 1, 'success writes exactly one cooldown');
    se_check($sets[0]['key'] === 'sendemail_' . md5($ip . '|' . strtolower($email)), 'cooldown key scoped to client IP + normalized recipient');
    se_check($sets[0]['ttl'] === 180, 'cooldown TTL is 180s');
    se_check(trim($ok['log']) === '', 'success writes no error log');
    se_assert_no_secret_leak($ok, 'success');
    printf("PASS success: TC3 headers, 15s/TLS, session 180s, cooldown 180s, no secret logging\n");

    // ------------------------------------------------------------------
    // AC3：同一邮箱重复请求被持有；旧验证码状态保持不变
    // ------------------------------------------------------------------
    $repeat = se_run($root, array('email' => $email, 'ip' => $ip, 'session' => $sessionId, 'mode' => 'ok'));
    se_check(strpos($repeat['stdout'], $holdText) !== false, 'same email repeat is held');
    se_check(count($repeat['curl']) === 0, 'held repeat performs no SES call');
    se_check(count(se_cache_sets($repeat)) === 0, 'held repeat writes no new cooldown');
    se_check(($repeat['session']['code'] ?? null) === $state['code'], 'held repeat keeps existing code');
    se_check(($repeat['session']['time'] ?? null) === $state['time'], 'held repeat keeps existing expiry');
    se_check(($repeat['session']['email'] ?? null) === $email, 'held repeat keeps existing recipient');
    printf("PASS same-email hold: no SES call, session unchanged\n");

    // ------------------------------------------------------------------
    // AC3：旧验证码在有效期内时，更换邮箱（纠正拼写）仍可发送
    // ------------------------------------------------------------------
    $otherEmail = 'bob@example.test';
    $corrected = se_run($root, array('email' => $otherEmail, 'ip' => $ip, 'session' => $sessionId, 'mode' => 'ok'));
    se_check(trim($corrected['stdout']) === $successText, 'different recipient allowed while old code valid');
    se_check(count($corrected['curl']) === 1, 'corrected recipient triggers one SES call');
    $correctedSets = se_cache_sets($corrected);
    se_check(count($correctedSets) === 1 && $correctedSets[0]['key'] === 'sendemail_' . md5($ip . '|' . $otherEmail), 'corrected recipient gets its own cooldown key');
    $correctedState = $corrected['session'];
    se_check(($correctedState['email'] ?? null) === $otherEmail, 'corrected recipient replaces bound email');
    se_check(($correctedState['time'] ?? 0) - time() >= 175, 'corrected recipient gets fresh 180s window');
    // 新验证码只要求是合法的 6 位数字，且与实际发往纠正后邮箱的 TemplateData 一致；
    // 随机码与旧码相同（1/1000000）是合法结果，不能作为断言条件。
    se_check(preg_match('/^\d{6}$/', (string)($correctedState['code'] ?? '')) === 1, 'corrected recipient gets a valid 6-digit code');
    $correctedPayload = json_decode($corrected['curl'][0]['body'], true);
    $correctedTemplateData = json_decode(($correctedPayload['Template']['TemplateData'] ?? ''), true);
    se_check(is_array($correctedTemplateData)
        && ($correctedTemplateData['code'] ?? null) === ($correctedState['code'] ?? null),
        'corrected recipient outgoing TemplateData matches new session code');
    se_assert_no_secret_leak($corrected, 'corrected recipient');
    printf("PASS email correction: valid old code does not block a different recipient\n");

    // ------------------------------------------------------------------
    // AC3：共享出口 IP 下，不同收件邮箱各自独立冷却
    // ------------------------------------------------------------------
    $sharedIp = '198.51.100.7';
    $carol = se_run($root, array('email' => 'carol@example.test', 'ip' => $sharedIp, 'session' => 'carol-session', 'mode' => 'ok'));
    se_check(trim($carol['stdout']) === $successText, 'carol sends from shared IP');
    $carolSets = se_cache_sets($carol);
    se_check(count($carolSets) === 1 && $carolSets[0]['key'] === 'sendemail_' . md5($sharedIp . '|carol@example.test'), 'carol cooldown key');
    $dave = se_run($root, array('email' => 'dave@example.test', 'ip' => $sharedIp, 'session' => 'dave-session', 'mode' => 'ok'));
    se_check(trim($dave['stdout']) === $successText, 'unrelated recipient on shared IP is not blocked');
    $daveSets = se_cache_sets($dave);
    se_check(count($daveSets) === 1 && $daveSets[0]['key'] === 'sendemail_' . md5($sharedIp . '|dave@example.test'), 'dave cooldown key distinct');
    $carolAgain = se_run($root, array('email' => 'carol@example.test', 'ip' => $sharedIp, 'session' => 'carol-session-2', 'mode' => 'ok'));
    se_check(strpos($carolAgain['stdout'], $rateText) !== false, 'carol cooldown still holds after dave sends');
    se_check(count($carolAgain['curl']) === 0 && count(se_cache_sets($carolAgain)) === 0, 'rate-limited request makes no call and writes no cooldown');
    printf("PASS shared-IP: per-recipient cooldown isolation\n");

    // ------------------------------------------------------------------
    // AC2 + AC3：供应商 2xx Error / HTTP 5xx / 空 / 畸形 / 缺 MessageId / 网络失败
    // 全部失败关闭：无 session 验证码、无冷却、无原始供应商错误输出
    // ------------------------------------------------------------------
    $failureLogs = array();
    $modes = array('provider_error', 'provider_error_no_code', 'http_error', 'malformed', 'empty', 'no_message_id', 'network');
    foreach ($modes as $index => $mode) {
        $result = se_run($root, array(
            'email' => 'fail-' . $mode . '@example.test',
            'ip' => '192.0.2.' . (10 + $index),
            'session' => 'fail-' . $mode . '-session',
            'mode' => $mode,
        ));
        $failureLogs[$mode] = $result['log'];
        se_check(trim($result['stdout']) === $failText, $mode . ': friendly failure message');
        se_check($result['exit'] === 0, $mode . ': endpoint exits cleanly');
        se_check(count($result['curl']) === 1, $mode . ': one bounded SES attempt');
        se_check(!isset($result['session']['code']), $mode . ': failure writes no session code');
        se_check(!isset($result['session']['time']), $mode . ': failure writes no session expiry');
        se_check(count(se_cache_sets($result)) === 0, $mode . ': failure writes no cooldown');
        se_check(strpos($result['log'], 'provider-secret-message') === false, $mode . ': raw provider message not logged');
        se_check(strpos($result['stdout'] . $result['stderr'], 'provider-secret-message') === false, $mode . ': raw provider message not echoed');
        se_assert_no_secret_leak($result, $mode);
    }
    se_check(strpos($failureLogs['provider_error'], 'code=FailedOperation.SendEmail') !== false, 'provider error code logged sanitized');
    se_check(strpos($failureLogs['provider_error'], 'requestId=req-provider-1') !== false, 'provider request id logged sanitized');
    // 2xx 且带 MessageId、但 Response.Error 无 Code：必须拒绝，日志只能给出 code=unknown。
    se_check(strpos($failureLogs['provider_error_no_code'], 'code=unknown') !== false, 'Error without Code rejected despite MessageId');
    se_check(strpos($failureLogs['provider_error_no_code'], 'http=200') !== false, 'Error without Code logged with 2xx status');
    se_check(strpos($failureLogs['http_error'], 'http=500') !== false && strpos($failureLogs['http_error'], 'code=InternalError') !== false, 'http failure logged sanitized');
    se_check(strpos($failureLogs['network'], 'code=28') !== false, 'network failure logs only errno');
    se_check(strpos($failureLogs['malformed'], 'code=unknown') !== false, 'malformed response logged without body');
    printf("PASS failures: provider-error/http/malformed/empty/no-message-id/network all fail closed\n");

    // ------------------------------------------------------------------
    // AC3：失败不得刷新/续期既有（已过期）验证码状态
    // ------------------------------------------------------------------
    $expiredSid = 'expired-session';
    $expiredTime = time() - 10;
    $expiredData = array('code' => '000001', 'time' => $expiredTime, 'email' => 'expired@example.test');
    se_seed_session($root, $expiredSid, $expiredData);
    $expired = se_run($root, array('email' => 'expired@example.test', 'ip' => '203.0.113.99', 'session' => $expiredSid, 'mode' => 'provider_error'));
    se_check(trim($expired['stdout']) === $failText, 'expired-code failure message');
    se_check(($expired['session']['code'] ?? null) === '000001', 'failure keeps old code untouched');
    se_check(($expired['session']['time'] ?? null) === $expiredTime, 'failure does not renew old expiry');
    se_check(count(se_cache_sets($expired)) === 0, 'expired-code failure writes no cooldown');
    printf("PASS failure-fail-closed: expired state untouched\n");

    printf("ALL PASS sendemail-registration\n");
} finally {
    se_rmtree($root);
}
