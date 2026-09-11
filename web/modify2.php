<?php
$cache_time=10;
$OJ_CACHE_SHARE=false;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');
$view_title= "Welcome To Online Judge";
require_once("./include/check_post_key.php");
require_once("./include/my_func.inc.php");
if(
    (isset($OJ_EXAM_CONTEST_ID)&&$OJ_EXAM_CONTEST_ID>0)||
    (isset($OJ_ON_SITE_CONTEST_ID)&&$OJ_ON_SITE_CONTEST_ID>0)
){
    $view_errors= $MSG_MODIFY_NOT_ALLOWED_FOR_EXAM;
    require("template/".$OJ_TEMPLATE."/error.php");
    exit ();
}

$user_id=$_SESSION[$OJ_NAME.'_'.'user_id'];

$type = trim($_POST['type']);
if (strcmp($type, "class") == 0) {

    $xueYuan = trim($_POST['xueYuan']);
    $school = trim($_POST['school']);
    $reason = trim($_POST['reason']);
    $sql = "SELECT `xueYuan`,`school` FROM `users` WHERE `user_id`=?";
    $result = pdo_query($sql, $user_id);
    $row = $result[0];

    if (strlen($reason) > 100 || strlen($reason) < 1) {
        $str = "理由过长或过短。";
        print "<script language='javascript'>\n";
        echo "alert('";
        echo $str;
        print "');\n history.go(-1);\n</script>";
        exit(0);
    }

    if (strcmp($row['xueYuan'], $xueYuan) != 0) {
        $sql = "insert INTO `modify`(`user_id`,`content`,`thing`,`reason`,`time`)
            VALUES(?,?,?,?,now())";
        pdo_query($sql, $user_id, $xueYuan, "xueYuan", $reason);
    }

    if ($school != "" && strcmp($row['school'], $school) != 0) {
        $sql = "insert INTO `modify`(`user_id`,`content`,`thing`,`reason`,`time`)
            VALUES(?,?,?,?,now())";
        pdo_query($sql, $user_id, $school, "school", $reason);
    }
    $str = "提交成功，等待审核通过。";

} else if (strcmp($type, "name") == 0) {
    $nuser_id = trim($_POST['user_id']);
    $nick = trim($_POST['nick']);
    $reason = trim($_POST['reason']);
    $sql = "SELECT `user_id`,`nick` FROM `users` WHERE `user_id`=?";
    $result = pdo_query($sql, $user_id);
    $row = $result[0];

    if (strlen($reason) > 100 || strlen($reason) < 1) {
        $str = "理由过长或过短。";
        print "<script language='javascript'>\n";
        echo "alert('";
        echo $str;
        print "');\n history.go(-1);\n</script>";
        exit(0);
    }

    if (strcmp($row['user_id'], $nuser_id) != 0) {
        $sql = "insert INTO `modify`(`user_id`,`content`,`thing`,`reason`,`time`)
            VALUES(?,?,?,?,now())";
        pdo_query($sql, $user_id, $nuser_id, "user_id", $reason);
    }

    if ($nick != "" && strcmp($row['nick'], $nick) != 0) {
        $sql = "insert INTO `modify`(`user_id`,`content`,`thing`,`reason`,`time`)
            VALUES(?,?,?,?,now())";
        pdo_query($sql, $user_id, $nick, "nick", $reason);
    }
    $str = "提交成功，等待审核通过。";

} else if (strcmp($type, "email") == 0) {
    $email = trim($_POST['email']);
    $code = trim($_POST['code']);
    $err_cnt=0;
    $err_str="";
    $len=strlen($email);
    if ($len>100){
        $err_str=$err_str."Email Too Long!\\n";
        $err_cnt++;
    }
    $len=strlen($code);
    if ($len != 6) {
        $str="请填写6位验证码。";
        $err_str=$err_str.$str."\\n";
        $err_cnt++;
    }

    if ($email != $_SESSION['email']) {
        $str="与验证邮箱不一致。";
        $err_str=$err_str.$str."\\n";
        $err_cnt++;
    }

    if($_SESSION['time'] >=  time()){ //判断验证码的时间是都大于当前时间
        if($code != $_SESSION['code']){ //验证码进行比对
            $str="验证码错误。";
            $err_str=$err_str.$str."\\n";
            $err_cnt++;
        }
    }else{ //验证码的时间大于当前时间，代表失效了
        unset($_SESSION['code']);
        $str="无效的验证码。";
        $err_str=$err_str.$str."\\n";
        $err_cnt++;
    }

    $sql = "SELECT COUNT(*) as num FROM `users` WHERE email=?";
    $result=pdo_query($sql,$email);
    $rows_cnt=$result[0][0];
    if ($rows_cnt > 0) {
        $str="使用该邮箱已经注册过了。";
        $err_str=$err_str.$str."\\n";
        $err_cnt++;
    }
    if ($err_cnt>0){
        print "<script language='javascript'>\n";
        print "alert('";
        print $err_str;
        print "');\n history.go(-1);\n</script>";
        exit(0);
    }
    $sql="UPDATE `users` SET"
        ."`email`=?"
        ."WHERE `user_id`=?";
    pdo_query($sql,$email,$user_id);
    $str = "修改成功。";

} else if (strcmp($type, "other") == 0) {
    $phone = trim($_POST['phone']);
    $qq = trim($_POST['qq']);

    $sql="UPDATE `users` SET"
        ."`phone`=?,"
        ."`qq`=?"
        ."WHERE `user_id`=?";
    pdo_query($sql,$phone,$qq,$user_id);
    $str = "修改成功。";
}


print "<script language='javascript'>\n";
echo "alert('";
echo $str;
print "');\n history.go(-1);\n</script>";
exit(0);
?>
