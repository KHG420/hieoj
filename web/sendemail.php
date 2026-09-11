<?php
@session_start();
require_once("./include/cache_layer.php");
require_once("./include/my_func.inc.php");

exec("python3 /home/admin123/getNetwork.py", $output, $return_var); // 连接网络

// 安全修复：邮箱格式校验 + 按 IP 限流（3 分钟 1 封），防止滥用校园邮箱服务轰炸他人邮箱
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
if(!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>100){
    echo "邮箱格式不正确。";
    exit(0);
}
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
$rate_key = "sendemail_".md5($ip);
if(cache_get($rate_key)!==false){
    echo "发送过于频繁，请 3 分钟后再试。";
    exit(0);
}
cache_set($rate_key, 1, 180);

// 检查验证码是否仍在有效期内
if (isset($_SESSION['code']) && ($_SESSION['time'] > time())) {
    $tt = $_SESSION['time'] - time();
    echo "验证码仍在有效期内，请等待 {$tt} 秒后再试。";
    exit(0);
}

// 生成6位验证码
$code = str_pad(mt_rand(0, 999999), 6, "0", STR_PAD_BOTH);

// 将验证码和有效期存入 Session（修正有效期）
$_SESSION['code'] = $code;
$_SESSION['time'] = time() + 180; // 有效期为3分钟
$_SESSION['email'] = $email;

// 腾讯云 API 配置（凭据由运行环境注入，不得写入源码库）
$secretId = getenv('TENCENTCLOUD_SECRET_ID');
$secretKey = getenv('TENCENTCLOUD_SECRET_KEY');
if ($secretId === false || $secretId === '' || $secretKey === false || $secretKey === '') {
    error_log('Tencent Cloud SES credentials are not configured.');
    echo "邮件服务暂时不可用，请稍后再试。";
    exit(0);
}
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

// 构建 cURL 请求（增加详细错误处理）
$ch = curl_init();
if ($ch === false) {
    die('curl_init 初始化失败，请检查 PHP curl 扩展是否安装');
}

$curlOptions = [
    CURLOPT_URL => "https://{$host}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonPayload,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_VERBOSE => true,    // 开启调试信息
    CURLOPT_STDERR => fopen(sys_get_temp_dir().'/sendemail_curl.log', 'w+'), // 输出调试日志到 webroot 之外
    CURLOPT_SSL_VERIFYPEER => true, // 生产环境保持验证
    CURLOPT_SSL_VERIFYHOST => 2,
];

// 如果是开发环境可临时关闭 SSL 验证（生产环境务必开启）
// $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
// $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;

curl_setopt_array($ch, $curlOptions);

$response = curl_exec($ch);
$errorNo = curl_errno($ch);
$errorMsg = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// 输出调试信息（正式环境应记录到日志）
error_log("cURL 错误码: {$errorNo}, 错误信息: {$errorMsg}");
error_log("HTTP 状态码: {$httpCode}");
error_log("原始响应: " . print_r($response, true));

// 处理响应
if ($errorNo) {
    echo "请求失败: ({$errorNo}) ". htmlspecialchars($errorMsg);
} else {
    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "响应解析失败: " . json_last_error_msg();
    } elseif ($httpCode >= 400) {
        echo "API 请求异常，HTTP 状态码: {$httpCode}";
        if (isset($responseData['Response']['Error'])) {
            echo " 错误信息: " . $responseData['Response']['Error']['Message'];
        }
    } elseif (isset($responseData['Response']['Error'])) {
        echo "业务逻辑错误: " . $responseData['Response']['Error']['Message'];
    } else {
        echo "验证码已发送至您的邮箱，请查收。";
    }
}

?>
