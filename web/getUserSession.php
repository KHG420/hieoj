<?php
require_once("./include/db_info.inc.php");
header('Content-Type:application/json; charset=utf-8');
$res = [
    "status" => "unlogin",
    "user_id" => "",
];
if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])) {
    $res['status'] = "unlogin";
} else {
    $res['status'] = "login";
    $res['user_id'] = $_SESSION[$OJ_NAME.'_'.'user_id'];
}

exit(json_encode($res,256));
?>