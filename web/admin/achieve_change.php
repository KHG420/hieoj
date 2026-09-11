<?php require_once("admin-header.php");
require_once("../include/check_get_key.php");
require_once('../include/db_info.inc.php');
$id=$_GET['id'];
//echo $cid;
if(!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))) exit();

$sql="select * FROM `acmer` WHERE `id`=?";
$result=pdo_query($sql,$id);
$num=count($result);
//echo $num;
if ($num<1){
    echo "No Such id!";
    require_once("../oj-footer.php");
    exit(0);
}
if ($_GET['num'] == 1) {
    $sql = "UPDATE `acmer` SET `status`='0' WHERE `id`=?";
    pdo_query($sql,$id);
} else if ($_GET['num'] == 0) {
    $sql = "UPDATE `acmer` SET `status`='1' WHERE `id`=?";
    pdo_query($sql,$id);
}
?>
<script language=javascript>
    history.go(-1);
</script>

