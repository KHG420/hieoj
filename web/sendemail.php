<?php
// session_start() 会持有会话文件排他锁直到脚本结束：整个有界 SES 调用期间同一会话的
// 并发请求被串行化，避免同一会话重复发送；因此这里不提前 session_write_close()。
@session_start();
require_once("./include/cache_layer.php");
require_once("./include/my_func.inc.php");

// 安全修复：邮箱格式校验 + 反滥用限流（同一客户端 IP + 同一收件邮箱 3 分钟 1 封）
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
if(!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>100){
    echo "邮箱格式不正确。";
    exit(0);
}
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
$normalized_email = strtolower($email);
$rate_key = "sendemail_".md5($ip."|".$normalized_email);

// 腾讯云 API 配置（凭据由运行环境注入，不得写入源码库）
$secretId = getenv('TENCENTCLOUD_SECRET_ID');
$secretKey = getenv('TENCENTCLOUD_SECRET_KEY');
if ($secretId === false || $secretId === '' || $secretKey === false || $secretKey === '') {
    error_log('Tencent Cloud SES credentials are not configured.');
    echo "邮件服务暂时不可用，请稍后再试。";
    exit(0);
}

// 同一收件邮箱仍持有有效验证码时复用等待提示；换成其它邮箱（例如纠正拼写）时放行，
// 仅受下方“客户端 IP + 收件邮箱”限流约束，避免旧的 IP 级限流误伤共享出口 IP 的学生。
if (isset($_SESSION['code'], $_SESSION['time'], $_SESSION['email'])
    && $_SESSION['time'] > time()
    && strtolower(trim((string)$_SESSION['email'])) === $normalized_email) {
    $tt = $_SESSION['time'] - time();
    echo "验证码仍在有效期内，请等待 {$tt} 秒后再试。";
    exit(0);
}

// 反滥用限流：同一客户端 IP + 同一收件邮箱 3 分钟内只允许成功发送一次。
// 仅在腾讯云确认接收后写入，失败既不占用额度也不产生冷却。
if(cache_get($rate_key)!==false){
    echo "发送过于频繁，请 3 分钟后再试。";
    exit(0);
}

// 生成6位验证码（加密安全随机数）
$code = str_pad((string)random_int(0, 999999), 6, "0", STR_PAD_LEFT);

$service = "ses";
$host = "ses.tencentcloudapi.com";
$action = "SendEmail";
$region = "ap-guangzhou";
$version = "2020-10-02";
$timestamp = time();
$algorithm = "TC3-HMAC-SHA256";

// 构建请求体
$payload = [
    'FromEmailAddress' => 'HNIEOJ <info@mail.hnieacm.com>',
    'Destination' => [$email],
    'Subject' => '【验证码】HnieOJ算法设计在线评测系统注册验证码',
    'Template' => [
        'TemplateID' => 30733,
        'TemplateData' => json_encode(["code" => $code]) // 单次 JSON 编码
    ]
];
$jsonPayload = json_encode($payload);

// 1. 构建规范请求
$canonicalHeaders = implode("\n", [
    "content-type:application/json",
    "host:" . $host,
    "x-tc-action:" . strtolower($action),
    ""
]);
$signedHeaders = "content-type;host;x-tc-action";
$hashedPayload = hash('sha256', $jsonPayload);

$canonicalRequest = "POST\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$hashedPayload}";

// 2. 拼接待签名字符串
$date = gmdate('Y-m-d', $timestamp);
$credentialScope = "{$date}/{$service}/tc3_request";
$hashedCanonicalRequest = hash('sha256', $canonicalRequest);

$stringToSign = "{$algorithm}\n{$timestamp}\n{$credentialScope}\n{$hashedCanonicalRequest}";

// 3. 计算签名
$secretDate = hash_hmac('sha256', $date, "TC3" . $secretKey, true);
$secretService = hash_hmac('sha256', $service, $secretDate, true);
$secretSigning = hash_hmac('sha256', "tc3_request", $secretService, true);
$signature = hash_hmac('sha256', $stringToSign, $secretSigning);

// 4. 构建 Authorization
$authorization = "{$algorithm} Credential={$secretId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

// 发送请求
$headers = [
    'Content-Type: application/json',
    'Host: ' . $host,
    'X-TC-Action: ' . $action,
    'X-TC-Timestamp: ' . $timestamp,
    'X-TC-Version: ' . $version,
    'X-TC-Region: ' . $region,
    'Authorization: ' . $authorization
];

$ch = curl_init();
if ($ch === false) {
    error_log('sendemail: SES transport failure code=curl_init_failed');
    echo "验证码发送失败，请稍后再试。";
    exit(0);
}

// 有界调用：15 秒超时 + TLS 校验（生产环境保持验证）
$curlOptions = [
    CURLOPT_URL => "https://{$host}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonPayload,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
];

curl_setopt_array($ch, $curlOptions);

$response = curl_exec($ch);
$errorNo = curl_errno($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// 只记录脱敏后的失败码 / 请求 ID：不输出凭据、签名、原始响应或供应商错误详情。
$send_email_ok = false;
if ($errorNo) {
    error_log('sendemail: SES transport failure code=' . (int)$errorNo);
} else {
    $responseData = json_decode((string)$response, true);
    $requestId = '';
    if (is_array($responseData) && isset($responseData['Response']['RequestId'])) {
        $requestId = substr(preg_replace('/[^A-Za-z0-9._-]/', '', (string)$responseData['Response']['RequestId']), 0, 64);
    }
    $providerError = '';
    if (is_array($responseData) && isset($responseData['Response']['Error']['Code'])) {
        $providerError = substr(preg_replace('/[^A-Za-z0-9._-]/', '', (string)$responseData['Response']['Error']['Code']), 0, 64);
    }
    // 成功必须同时满足：HTTP 2xx、合法 JSON、Response 为数组、不含 Error（无论是否有 Code）、
    // 非空 MessageId。失败判定直接基于 Response.Error 是否存在，绝不依赖脱敏后的日志串。
    if ($httpCode < 200 || $httpCode >= 300
        || !is_array($responseData)
        || !isset($responseData['Response'])
        || !is_array($responseData['Response'])
        || array_key_exists('Error', $responseData['Response'])
        || !isset($responseData['Response']['MessageId'])
        || !is_string($responseData['Response']['MessageId'])
        || trim($responseData['Response']['MessageId']) === '') {
        error_log('sendemail: SES send rejected http=' . (int)$httpCode
            . ' code=' . ($providerError !== '' ? $providerError : 'unknown')
            . ' requestId=' . ($requestId !== '' ? $requestId : 'unknown'));
    } else {
        $send_email_ok = true;
    }
}

if (!$send_email_ok) {
    // 失败关闭：不写入验证码、不延长有效期、不占用限流额度。
    echo "验证码发送失败，请稍后再试。";
    exit(0);
}

// 仅在腾讯云确认接收后写入验证码与限流额度（保持既有 session 键约定）。
$_SESSION['code'] = $code;
$_SESSION['time'] = time() + 180; // 有效期为3分钟
$_SESSION['email'] = $email;
cache_set($rate_key, 1, 180);

echo "验证码已发送至您的邮箱，请查收。";

?>
