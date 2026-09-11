<?php require_once("admin-header.php");
require_once("../include/check_get_key.php");
$cid=$_GET['cid'];
//echo $cid;
if(!(isset($_SESSION[$OJ_NAME.'_'."m$cid"])||isset($_SESSION[$OJ_NAME.'_'.'administrator']))) exit();

$sql = "select * FROM `team` WHERE `team_id`=?";
$result = pdo_query($sql, $cid);
//echo $result;
$num = count($result);
//echo $num;
if ($num < 1) {
    echo "No Such Team!";
    require_once("../oj-footer.php");
    exit(0);
}

$sql = "DELETE FROM team WHERE team_id=?";
$result = pdo_query($sql, $cid);

?>
<script language=javascript>
    history.go(-1);
</script>