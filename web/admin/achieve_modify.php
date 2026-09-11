<?php require_once("admin-header.php");?>
<?php if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) )){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
if(isset($_POST['do'])){
    require_once("../include/check_post_key.php");
    require_once("../include/my_func.inc.php");
    require_once("../include/db_info.inc.php");
    $id = trim($_POST['id']);
    $value = trim($_POST['value']);
    $color = trim($_POST['color']);
    $weight = trim($_POST['weight']);
    if ((function_exists('get_magic_quotes_gpc')&&get_magic_quotes_gpc())) {
        $id = stripslashes ( $id);
        $value = stripslashes ( $value);
        $color = stripslashes ( $color);
        $weight = stripslashes ( $weight);
    }
    $sql = "SELECT COUNT(*) as num FROM acmer WHERE id=?";
    $result = pdo_query($sql, $id);
    if ($result[0][0] == 0) {
        echo "<script>alert('不存在。');history.go(-1);</script>";
        exit(0);
    }

    $sql = "UPDATE acmer SET value=?,color=?,weight=? WHERE id=?";

//    echo $sql;

    $result = pdo_query($sql,$value,$color,$weight,$id);

}
?>
<script language=javascript>
    history.go(-1);
</script>
