<?php
require_once './include/db_info.inc.php';
require_once './include/setlang.php';
require_once './include/const.inc.php';
require_once './include/my_func.inc.php';
header('Cache-Control: private, no-store');
$view_title = '确认重置密码';
$lost_user_id = is_string($_POST['user_id'] ?? null) ? trim($_POST['user_id']) : '';
$lost_key = is_string($_POST['lost_key'] ?? null) ? trim($_POST['lost_key']) : '';
$vcode = is_string($_POST['vcode'] ?? null) ? trim($_POST['vcode']) : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    $view_errors = '请从找回密码页面开始操作。';
} elseif (!is_string($_POST['postkey'] ?? null) || !isset($_SESSION[$OJ_NAME.'_postkey'])
    || !hash_equals($_SESSION[$OJ_NAME.'_postkey'], $_POST['postkey'])) {
    http_response_code(403);
    $view_errors = '页面凭证已失效，请重新申请重置邮件。';
} elseif (!empty($OJ_VCODE) && ($vcode === '' || !isset($_SESSION[$OJ_NAME.'_vcode'])
    || !hash_equals((string)$_SESSION[$OJ_NAME.'_vcode'], $vcode))) {
    http_response_code(400);
    $view_errors = '图形验证码错误，请刷新验证码后重试。';
} elseif ($lost_user_id === '' || $lost_key === ''
    || !isset($_SESSION[$OJ_NAME.'_lost_user_id'], $_SESSION[$OJ_NAME.'_lost_key'], $_SESSION[$OJ_NAME.'_lost_expires'])
    || $_SESSION[$OJ_NAME.'_lost_user_id'] !== $lost_user_id
    || !hash_equals($_SESSION[$OJ_NAME.'_lost_key'], $lost_key)
    || $_SESSION[$OJ_NAME.'_lost_expires'] <= time()) {
    http_response_code(400);
    $view_errors = '重置密钥错误或已过期，请重新申请邮件。';
} else {
    $updated = pdo_query('UPDATE users SET password=? WHERE user_id=?', pwGen($lost_key), $lost_user_id);
    if ($updated === 1) {
        unset($_SESSION[$OJ_NAME.'_lost_user_id'], $_SESSION[$OJ_NAME.'_lost_key'], $_SESSION[$OJ_NAME.'_lost_expires']);
        $view_errors = '密码已重置为邮件中的密钥。请<a href="loginpage.php">登录</a>后立即修改密码。';
    } else {
        http_response_code(503);
        $view_errors = '密码保存失败，请稍后重试。';
    }
}
require 'template/'.$OJ_TEMPLATE.'/error.php';
