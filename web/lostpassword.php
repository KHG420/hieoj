<?php
require_once './include/db_info.inc.php';
require_once './include/setlang.php';
require_once './include/const.inc.php';
require_once './include/my_func.inc.php';
header('Cache-Control: private, no-store');
$view_title = '找回密码';
$lost_user_id = is_string($_POST['user_id'] ?? null) ? trim($_POST['user_id']) : '';
$lost_email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $view_errors = '';
    $vcode = is_string($_POST['vcode'] ?? null) ? trim($_POST['vcode']) : '';
    if (!is_string($_POST['postkey'] ?? null) || !isset($_SESSION[$OJ_NAME.'_postkey'])
        || !hash_equals($_SESSION[$OJ_NAME.'_postkey'], $_POST['postkey'])) {
        http_response_code(403);
        $view_errors = '页面凭证已失效，请返回找回密码页面后重试。';
    } elseif (!empty($OJ_VCODE) && ($vcode === '' || !isset($_SESSION[$OJ_NAME.'_vcode'])
        || !hash_equals((string)$_SESSION[$OJ_NAME.'_vcode'], $vcode))) {
        http_response_code(400);
        $view_errors = '图形验证码错误，请刷新验证码后重试。';
    } elseif ($lost_user_id === '' || !filter_var($lost_email, FILTER_VALIDATE_EMAIL) || strlen($lost_email) > 100) {
        http_response_code(400);
        $view_errors = '请填写学号和有效的注册邮箱。';
    } else {
        $rows = pdo_query('SELECT email FROM users WHERE user_id=?', $lost_user_id);
        if (!$rows || strcasecmp((string)$rows[0]['email'], $lost_email) !== 0) {
            http_response_code(400);
            $view_errors = '学号与注册邮箱不匹配，请核对后重试。';
        } else {
            $password_reset_email = (string)$rows[0]['email'];
            $password_reset_code = bin2hex(random_bytes(8));
            ob_start();
            require './sendemail.php';
            $send_message = trim(ob_get_clean());
            if ($send_email_ok) {
                // Only a provider-confirmed send creates a usable, short-lived key.
                $_SESSION[$OJ_NAME.'_lost_user_id'] = $lost_user_id;
                $_SESSION[$OJ_NAME.'_lost_key'] = $password_reset_code;
                $_SESSION[$OJ_NAME.'_lost_expires'] = time() + 180;
                require 'template/'.$OJ_TEMPLATE.'/lostpassword2.php';
                exit;
            }
            http_response_code(503);
            $view_errors = htmlspecialchars($send_message ?: '邮件发送失败，请稍后重试。', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }
    require 'template/'.$OJ_TEMPLATE.'/error.php';
    exit;
}

require 'template/'.$OJ_TEMPLATE.'/lostpassword.php';
