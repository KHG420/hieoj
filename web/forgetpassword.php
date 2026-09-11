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
$user_id = trim($_POST['user_id']);
$email = trim($_POST['email']);

$sql = "SELECT email FROM `users` WHERE user_id=?";
$result=pdo_query($sql,$user_id);
$rows_cnt=$result[0]['email'];
if (strcmp($email, $rows_cnt) != 0) {
    $str = "输入邮箱与账号绑定邮箱不一致。";
    print "<script language='javascript'>\n";
    echo "alert('";
    echo $str;
    print "');\n history.go(-1);\n</script>";
    exit(0);
}

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

if ($err_cnt>0){
    print "<script language='javascript'>\n";
    print "alert('";
    print $err_str;
    print "');\n history.go(-1);\n</script>";
    exit(0);
}

$len=strlen($_POST['npassword']);
if ($len<6 && $len>0){
    $err_cnt++;
    $err_str=$err_str."Password should be Longer than 6!\\n";
}else if (strcmp($_POST['npassword'],$_POST['rptpassword'])!=0){
    $err_str=$err_str."Two Passwords Not Same!";
    $err_cnt++;
}
if ($err_cnt>0){
    print "<script language='javascript'>\n";
    echo "alert('";
    echo $err_str;
    print "');\n history.go(-1);\n</script>";
    exit(0);

}
$password=pwGen($_POST['npassword']);
$sql="UPDATE `users` SET"
    ."`password`=?"
    ."WHERE `user_id`=?";
pdo_query($sql,$password,$user_id);
$str = "修改成功。";
print "<script language='javascript'>\n";
echo "alert('";
echo $str;
print "');\n history.go(-1);\n</script>";
exit(0);
?>
