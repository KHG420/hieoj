<?php 
header("Content-Type: text/html; charset=UTF-8");
require_once("./include/db_info.inc.php");
if(isset($OJ_REGISTER)&&!$OJ_REGISTER) exit(0);
require_once("./include/my_func.inc.php");
require_once("./include/academic_directory.php");
require_once("./include/academic_registration.php");
$err_str="";
$err_cnt=0;
$len;
$user_id=trim($_POST['user_id']);
$len=strlen($user_id);
$email=trim($_POST['email']);
// 学院 / 班级按“安全标量”读取：缺失 / 数组 / 非标量输入不会触发 PHP 警告，
// 而是标记为非标量，交由下面的模板校验显式拒绝。
$xueYuan=academic_registration_policy_scalar_request($_POST,'xueYuan',$xueYuan_scalar);
$school=academic_registration_policy_scalar_request($_POST,'school',$school_scalar);
$phone=trim($_POST['phone']);
$qq=trim($_POST['qq']);
$vcode=trim($_POST['vcode']);
$code =  isset($_POST['code'])?$_POST['code']:'';
if($OJ_VCODE&&(!isset($_SESSION[$OJ_NAME.'_'."vcode"])||$vcode!==$_SESSION[$OJ_NAME.'_'."vcode"]||$vcode==""||$vcode==null) ){
	$_SESSION[$OJ_NAME.'_'."vcode"]=null;
	$err_str=$err_str."Verification Code Wrong!\\n";
	$err_cnt++;
}
if($OJ_LOGIN_MOD!="hustoj"){
	$err_str=$err_str."System do not allow register.\\n";
	$err_cnt++;
}

if($len>20){
	$err_str=$err_str."User ID Too Long!\\n";
	$err_cnt++;
}else if ($len<3){
	$err_str=$err_str."User ID Too Short!\\n";
	$err_cnt++;
}
if (!is_valid_user_name($user_id)){
	$err_str=$err_str."User ID can only contain NUMBERs & LETTERs!\\n";
	$err_cnt++;
}
$nick=trim($_POST['nick']);
$len=strlen($nick);
if ($len>100){
	$err_str=$err_str."Nick Name Too Long!\\n";
	$err_cnt++;
}else if ($len==0) $nick=$user_id;
if (strcmp($_POST['password'],$_POST['rptpassword'])!=0){
    $str="两次密码不相同。";
    #$str=mb_convert_encoding($str ,"gbk","utf-8");
    $err_str=$err_str.$str."\\n";
    $err_cnt++;
}
if (strlen($_POST['password'])<6){
    $str="密码需要大于6位。";
    #$str=mb_convert_encoding($str ,"gbk","utf-8");
    $err_str=$err_str.$str."\\n";
    $err_cnt++;
}
$len=strlen($school);
if ($len>100){
	$err_str=$err_str."School Name Too Long!\\n";
	$err_cnt++;
}
$len=strlen($_POST['email']);
if ($len>100){
	$err_str=$err_str."Email Too Long!\\n";
	$err_cnt++;
}
$len=strlen($code);
if ($len != 6) {
    $str="请填写6位验证码。";
    #$str=mb_convert_encoding($str ,"gbk","utf-8");
    $err_str=$err_str.$str."\\n";
    $err_cnt++;
}

if ($email != $_SESSION['email']) {
    $str="与验证邮箱不一致。";
    #$str=mb_convert_encoding($str ,"gbk","utf-8");
    $err_str=$err_str.$str."\\n";
    $err_cnt++;
}

if($_SESSION['time'] >=  time()){ //判断验证码的时间是都大于当前时间
    if($code != $_SESSION['code']){ //验证码进行比对
        $str="验证码错误。";
        #$str=mb_convert_encoding($str ,"gbk","utf-8");
        $err_str=$err_str.$str."\\n";
        $err_cnt++;
    }
}else{ //验证码的时间大于当前时间，代表失效了
    unset($_SESSION['code']);
    $str="无效的验证码。";
    #$str=mb_convert_encoding($str ,"gbk","utf-8");
    $err_str=$err_str.$str."\\n";
    $err_cnt++;
}

// 注册年级范围校验：提交的“学院 + 专业班级”必须匹配当前开放范围内、该学院下确有
// 的合格班级，拒绝已毕业 / 未来 / 任意拼凑 / 不属于该学院的组合。策略读取失败时
// 失败关闭（不写 users）。
// 是否启用只取决于可信的 OJ_TEMPLATE 配置：syzoj / sta_sty 使用“学院 + 专业班级”
// 下拉，因此即使学院 / 班级字段为空、缺失或是数组也必须拒绝，不能凭请求自带字段
// 绕过策略；bs3 / sweet 等自由填写班级的旧模板保持既有行为不受影响。
if (academic_registration_policy_template_requires_selection(isset($OJ_TEMPLATE) ? $OJ_TEMPLATE : '')) {
    $reg_policy = academic_registration_policy_load(
        academic_registration_policy_path(academic_registration_policy_data_dir())
    );
    if (!$reg_policy['ok']) {
        $str="注册年级范围配置异常，请联系管理员。";
        $err_str=$err_str.$str."\\n";
        $err_cnt++;
    } else if (!$xueYuan_scalar || !$school_scalar || $xueYuan === '' || $school === ''
        || !academic_directory_registration_class_allowed(
            $xueYuan,
            $school,
            $reg_policy['effective_min'],
            $reg_policy['effective_max']
        )) {
        $str="学院与专业班级不匹配，或该班级已不在当前开放年级范围内，请返回重新选择。";
        $err_str=$err_str.$str."\\n";
        $err_cnt++;
    }
}

$ip = ($_SERVER['REMOTE_ADDR']);

$sql = "SELECT COUNT(*) as num FROM `users` WHERE email=?";
$result=pdo_query($sql,$email);
$rows_cnt=$result[0][0];
#if ($rows_cnt > 0) {
#    $str="使用该邮箱已经注册过了。";
    #$str=mb_convert_encoding($str ,"gbk","utf-8");
#    $err_str=$err_str.$str."\\n";
#    $err_cnt++;
#}

if ($err_cnt>0){
	print "<script language='javascript'>\n";
	print "alert('";
	print $err_str;
	print "');\n history.go(-1);\n</script>";
	exit(0);
	
}
$password=pwGen($_POST['password']);
$sql="SELECT `user_id` FROM `users` WHERE `users`.`user_id` = ?";
$result=pdo_query($sql,$user_id);
$rows_cnt=count($result);
if ($rows_cnt == 1){
	print "<script language='javascript'>\n";
	print "alert('User Existed!\\n');\n";
	print "history.go(-1);\n</script>";
	exit(0);
}
$nick=(htmlentities ($nick,ENT_QUOTES,"UTF-8"));
$school=(htmlentities ($school,ENT_QUOTES,"UTF-8"));
$email=(htmlentities ($email,ENT_QUOTES,"UTF-8"));
if( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
    $REMOTE_ADDR = $_SERVER['HTTP_X_FORWARDED_FOR'];
    $tmp_ip=explode(',',$REMOTE_ADDR);
    $ip =(htmlentities($tmp_ip[0],ENT_QUOTES,"UTF-8"));
}

if(isset($OJ_REG_NEED_CONFIRM)&&$OJ_REG_NEED_CONFIRM){
	$defunct="Y";
	$register_num = 0;
}
else{
	$defunct="N";
	$register_num = 1;
}
$sql="INSERT INTO `users`("
."`user_id`,`email`,`ip`,`password`,`reg_time`,`nick`,`school`,`defunct`,`xueYuan`,`qq`,`phone`,`register_num`)"
."VALUES(?,?,?,?,NOW(),?,?,?,?,?,?,?)";
$rows=pdo_query($sql,$user_id,$email,$ip,$password,$nick,$school,$defunct,$xueYuan,$qq,$phone,$register_num);// or die("Insert Error!\n");

$sql="INSERT INTO `loginlog` VALUES(?,?,?,NOW())";
pdo_query($sql,$user_id,"no save",$ip);

if(!isset($OJ_REG_NEED_CONFIRM)||!$OJ_REG_NEED_CONFIRM){
		$_SESSION[$OJ_NAME.'_'.'user_id']=$user_id;
		$sql="SELECT `rightstr` FROM `privilege` WHERE `user_id`=?";
		//echo $sql."<br />";
		$result=pdo_query($sql,$_SESSION[$OJ_NAME.'_'.'user_id']);
		foreach ($result as $row){
			$_SESSION[$OJ_NAME.'_'.$row['rightstr']]=true;
			//echo $_SESSION[$OJ_NAME.'_'.$row['rightstr']]."<br />";
		}
		$_SESSION[$OJ_NAME.'_'.'ac']=Array();
		$_SESSION[$OJ_NAME.'_'.'sub']=Array();
}
// 交互连贯：提示与实际行为一致（免审自动登录跳首页；需审核提示等待通知）
if(!isset($OJ_REG_NEED_CONFIRM)||!$OJ_REG_NEED_CONFIRM){
    $str="注册成功，已自动登录。";
}else{
    $str="提交成功，等待管理员审核通过邮箱通知。";
}
?>
<script>
    alert('<?php echo $str; ?>');
    <?php if(!isset($OJ_REG_NEED_CONFIRM)||!$OJ_REG_NEED_CONFIRM){ ?>
    window.location.href='index.php';
    <?php }else{ ?>
    history.go(-2);
    <?php } ?>
</script>
