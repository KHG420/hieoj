<?php
require_once(dirname(__FILE__) . "/include/db_info.inc.php");
// 安全修复：仅接受 POST 注销（防 CSRF 强制登出）；令牌校验由 db_info 全局 CSRF 防护完成
if($_SERVER['REQUEST_METHOD']!=='POST'){
    header("Location:index.php");
    exit(0);
}
$_SESSION=array();
if(ini_get("session.use_cookies")){
    $p=session_get_cookie_params();
    setcookie(session_name(),'',time()-42000,$p["path"],$p["domain"],$p["secure"],$p["httponly"]);
}
if(isset($_COOKIE[$OJ_NAME."_user"])) setcookie($OJ_NAME."_user","",time()-42000,"/");
if(isset($_COOKIE[$OJ_NAME."_check"])) setcookie($OJ_NAME."_check","",time()-42000,"/");
@session_destroy();
header("Location:index.php");
exit(0);
?>
