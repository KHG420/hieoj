<?php require_once("admin-header.php");?>
<?php if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) )){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
if(isset($_POST['do'])){
    //echo $_POST['user_id'];
    require_once("../include/check_post_key.php");
    //echo $_POST['passwd'];
    require_once("../include/my_func.inc.php");
    require_once("../include/db_info.inc.php");
    $content1 = $_POST['content1'];
    $content2 = $_POST['content2'];
    $priority = $_POST['priority'];
    if ((function_exists('get_magic_quotes_gpc')&&get_magic_quotes_gpc())) {
        $content1 = stripslashes ( $content1);
        $content2 = stripslashes ( $content2);
        $priority = stripslashes ( $priority);
    }

    $sql = "SELECT id,priority FROM `category` WHERE `priority` >= ? ORDER BY `priority`";
    $result = pdo_query($sql, $priority);

    $sql = "UPDATE `category` SET `priority`=? WHERE `id`=?";
    foreach ($result as $row) {
        pdo_query($sql, $row['priority']+1, $row['id']);
    }

//    echo $content1;
//    echo $content2;
//    echo $priority;
//    echo "dsdsdsds";
//    $sql="update `users` set `password`=? where `user_id`=?  and user_id not in( select user_id from privilege where rightstr='administrator') ";
    $sql = "INSERT INTO category(`content-1`,`content-2`,`priority`,`time`) VALUES (?,?,?,now())";

//    echo $sql;

    $result = pdo_query($sql,$content1,$content2,$priority);

//    if (pdo_query($sql,$passwd,$user_id)==1) echo "Password Changed!";
//    else echo "No such user! or He/Her is an administrator!";
}
?>
<script language=javascript>
    history.go(-1);
</script>
