<?php require_once("admin-header.php");
require_once("../include/check_get_key.php");
$cid=$_GET['cid'];
//echo $cid;
if(!(isset($_SESSION[$OJ_NAME.'_'."m$cid"])||isset($_SESSION[$OJ_NAME.'_'.'administrator']))) exit();
$sql="select `status` FROM `modify` WHERE `id`=?";
$result=pdo_query($sql,$cid);
//echo $result;
$num=count($result);
//echo $num;
if ($num<1){
    echo "No Such User!";
    require_once("../oj-footer.php");
    exit(0);
}
$row=$result[0];
if ($row[0]==0) {
    $sql = "UPDATE `modify` SET `status`=1 WHERE `id`=?";

    $modify_sql = "SELECT user_id,content,thing,`time`,`status` FROM `modify` WHERE id=?";
    $modify_result = pdo_query($modify_sql, $cid);
    $modify_row = $modify_result[0];

    $modify_sql="UPDATE `users` SET `".$modify_row['thing']."`=? WHERE `user_id`=?";
    pdo_query($modify_sql,$modify_row['content'],$modify_row['user_id']);

    pdo_query($sql,$cid);
}

?>
<script language=javascript>
    history.go(-1);
</script>

