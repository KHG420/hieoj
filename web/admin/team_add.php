<?php require_once("admin-header.php");?>
<?php if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) )){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
if(isset($_POST['do'])){
    //echo $_POST['user_id'];
    echo "asdasdas";
    require_once("../include/check_post_key.php");
    echo "asdasdas";
    //echo $_POST['passwd'];
    require_once("../include/my_func.inc.php");
    require_once("../include/db_info.inc.php");
    $team_name = trim($_POST['team_name']);
    $user_id1 = trim($_POST['user_id1']);
    $user_id2 = trim($_POST['user_id2']);
    $user_id3 = trim($_POST['user_id3']);
    if ((function_exists('get_magic_quotes_gpc')&&get_magic_quotes_gpc())) {
        $team_name = stripslashes ( $team_name);
        $user_id1 = stripslashes ( $user_id1);
        $user_id2 = stripslashes ( $user_id2);
        $user_id3 = stripslashes ( $user_id3);
    }
    $sql = "SELECT COUNT(*) FROM `team` WHERE user_id1=?";
    $result = pdo_query($sql, $user_id1);
    if ($result[0][0] > 0) {
        echo "<script>alert('该队长已存在，无法重复创建队伍。');history.go(-1);</script>";
        exit(0);
    }

    $str = "";
    $count = 0;

    $sql = "SELECT nick FROM `users` WHERE user_id=?";
    $nick1 = pdo_query($sql, $user_id1)[0][0];
    if ($nick1 == "") {
        $str = $str.$user_id1."不存在，";
        $count ++;
    }
    $nick2 = pdo_query($sql, $user_id2)[0][0];
    if ($nick2 == "") {
        $str = $str.$user_id2."不存在，";
        $count ++;
    }
    $nick3 = pdo_query($sql, $user_id3)[0][0];
    if ($nick3 == "") {
        $str = $str.$user_id3."不存在，";
        $count ++;
    }
    if ($count > 0) {
        echo "<script>alert('".$str."');history.go(-1);</script>";
        exit(0);
    }

    $sql = "INSERT INTO team(`team_name`,`user_id1`,`nick1`,`user_id2`,`nick2`,`user_id3`,`nick3`,`create_time`) VALUES (?,?,?,?,?,?,?,now())";

//    echo $sql;

    $result = pdo_query($sql,$team_name,$user_id1,$nick1,$user_id2,$nick2,$user_id3,$nick3);

}
?>
<script language=javascript>
    history.go(-1);
</script>
