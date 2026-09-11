<?php require_once("admin-header.php");?>
<?php if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) )){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
if(isset($_POST['do'])){
    require_once("../include/check_post_key.php");
    require_once("../include/my_func.inc.php");
    require_once("../include/db_info.inc.php");
    $user_id = trim($_POST['user_id']);
    $value = trim($_POST['value']);
    $color = trim($_POST['color']);
    $weight = trim($_POST['weight']);
    if ((function_exists('get_magic_quotes_gpc')&&get_magic_quotes_gpc())) {
        $user_id = stripslashes ( $user_id);
        $value = stripslashes ( $value);
        $color = stripslashes ( $color);
        $weight = stripslashes ( $weight);
    }

    $str = "";

    $sql = "SELECT COUNT(nick) FROM `users` WHERE user_id=?";
    $res = pdo_query($sql, $user_id)[0][0];
    if ($res[0][0] == 0) {
        $str = $str.$user_id."不存在，";
        echo "<script>alert('".$str."');history.go(-1);</script>";
        exit(0);
    }

    $sql = "INSERT INTO acmer(`user_id`,`value`,`color`,`weight`,`join_time`) VALUES (?,?,?,?,now())";

//    echo $sql;

    $result = pdo_query($sql,$user_id,$value,$color,$weight);

}
?>
<script language=javascript>
    history.go(-1);
</script>
