<?php
require_once(dirname(__FILE__) . "/include/db_info.inc.php");
if (isset($_SESSION[$OJ_NAME . "_user_id"])) {
    $user_id = $_SESSION[$OJ_NAME . "_user_id"];
    $defunct = pdo_query("select defunct from users where user_id=?", $user_id);
    if (!empty($defunct) && $defunct[0][0] == "Y") {
        unset($_SESSION[$OJ_NAME . '_' . 'user_id']);
        setcookie($OJ_NAME . "_user", "", time() - 42000, "/");
        setcookie($OJ_NAME . "_check", "", time() - 42000, "/");
        session_destroy();
    }
}
// $OJ_LIP_URL="http://192.168.2.36/lip.php";  //如果希望穿透NAT网络，识别用户的内网IP地址，可以在内网部署一个LIP服务，用内网的lip.php向公网服务器传递用户的内网IP。
if (!empty($OJ_LIP_URL)) {
    if (isset($_GET['lip'])) {
        $lip = intval($_GET['lip']);
        setcookie("lip", $lip);
    } else if (!isset($_COOKIE['lip'])) {
        echo "<script> window.setTimeout(\"window.location.href='$OJ_LIP_URL';\",1000);</script>";
    }
}
// 体验优化：移除原先每 300 秒强制刷新页面的脚本（状态页已有按需轮询）
?>
