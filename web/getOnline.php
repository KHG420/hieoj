<?php
if(isset($_GET['userid'])) {
    require_once("include/db_info.inc.php");
    $userid = substr((string)$_GET['userid'], 0, 64);
    $pid = intval($_GET['pid']);
    $cid = intval($_GET['cid']);
    $ip = substr((string)$_GET['ip'], 0, 40);
    // 安全修复：输入长度/类型限制 + 按 IP 限流（考试防作弊页面为跨域脚本调用，无 Cookie，不能要求登录）
    require_once("include/cache_layer.php");
    $rate_key = "linki_".md5(isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'0');
    if(cache_get($rate_key)!==false){
        exit(0);
    }
    cache_set($rate_key, 1, 5); // 同一 IP 每 5 秒最多记录一次

    $sql = "insert INTO linkI(`userid`,`pid`,`time`,`cid`,`ip`) 
            VALUES(?,?,now(),?,?)";
    $result = pdo_query($sql, $userid, $pid, $cid,$ip);

    $sql = "SELECT count(userid) FROM `linkI` WHERE userid=?";
    $result = pdo_query($sql, $userid);

    echo $result[0][0];
}
?>