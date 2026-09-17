<?php 
header("Content-Type: text/html; charset=UTF-8");
require_once("./include/db_info.inc.php");
if(isset($OJ_REGISTER)&&!$OJ_REGISTER) exit(0);
require_once("./include/my_func.inc.php");
require_once("./include/academic_directory.php");
require_once("./include/academic_registration.php");

/**
 * 注册请求指纹：顶层 POST 字段按 key 排序后规范化，取 SHA-256。
 *
 * 成功回执只保存该单向哈希，绝不保存明文密码 / 验证码；同一会话内两次完全
 * 相同的提交得到同一哈希，任一字段（含密码、验证码）变化都会得到不同哈希，
 * 因而不会误命中回执。
 */
function register_request_payload_hash($post)
{
	if(!is_array($post)) return '';
	ksort($post,SORT_STRING);
	return hash('sha256',serialize($post));
}

/**
 * 输出注册成功提示 / 跳转。首次注册与同会话重放共用，保证重放返回与首次
 * 完全一致的 JS 行为（免审自动登录跳首页；需审核提示等待通知）。
 */
function register_success_response($pending_approval)
{
	if($pending_approval){
		$str="提交成功，等待管理员审核通过邮箱通知。";
		$action="history.go(-2);";
	}else{
		$str="注册成功，已自动登录。";
		$action="window.location.href='index.php';";
	}
	echo "<script>\r\n";
	echo "    alert('".$str."');\r\n";
	echo "        ".$action."\r\n";
	echo "    </script>\r\n";
}

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
// vcode 为可选字段：沿用学院 / 班级的安全标量读取，缺失 / 数组 / 非标量输入
// 不再触发 PHP 警告，而是按空串走既有的验证码校验分支。
$vcode=academic_registration_policy_scalar_request($_POST,'vcode',$vcode_scalar);
$code =  isset($_POST['code'])?$_POST['code']:'';

// 同会话成功回执重放：请求载荷指纹与上一次成功注册完全一致时，直接返回相同
// 的成功响应并结束，避免响应丢失后的重试再次 SELECT 命中已建用户而误报
// UserExisted。重放不查库、不写库、不重新登录：
//   - 免审模式必须先确认当前会话仍登录在同一用户名下，登出 / 换号后不得重放；
//   - 审核模式本就只写库并提示等待，重放只返回相同提示、不登录；
//   - 任何字段变化都会改变指纹，新会话也没有该回执，因而不会命中。
$register_payload_hash=register_request_payload_hash($_POST);
$register_receipt_key=$OJ_NAME.'_'.'register_success_receipt';
$register_receipt=isset($_SESSION[$register_receipt_key])&&is_array($_SESSION[$register_receipt_key])
	?$_SESSION[$register_receipt_key]:null;
if($register_receipt!==null
	&&isset($register_receipt['hash'])
	&&is_string($register_receipt['hash'])
	&&hash_equals($register_receipt['hash'],$register_payload_hash)){
	if(!empty($register_receipt['confirm'])){
		register_success_response(true);
		exit(0);
	}
	$register_logged_in=isset($_SESSION[$OJ_NAME.'_'.'user_id'])
		&&is_string($_SESSION[$OJ_NAME.'_'.'user_id'])
		?$_SESSION[$OJ_NAME.'_'.'user_id']:'';
	if($register_logged_in!==''&&isset($register_receipt['user_id'])
		&&(string)$register_receipt['user_id']===$register_logged_in){
		register_success_response(false);
		exit(0);
	}
}
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
// 成功回执：仅在用户 / 登录日志确实写入（免审时还已建立登录会话）之后保存，
// 校验失败或重复用户不会走到这里。只存请求指纹、成功用户与确认模式，绝无明文
// 密码 / 验证码；供同一会话在响应丢失后重放同一 POST 时幂等返回成功。
$_SESSION[$register_receipt_key]=array(
	'hash'=>$register_payload_hash,
	'user_id'=>$user_id,
	'confirm'=>(isset($OJ_REG_NEED_CONFIRM)&&$OJ_REG_NEED_CONFIRM)?1:0,
);
// 交互连贯：提示与实际行为一致（免审自动登录跳首页；需审核提示等待通知）。
// 同一函数供首次注册与同会话重放共用，保证重放返回完全相同的提示 / 跳转。
register_success_response(isset($OJ_REG_NEED_CONFIRM)&&$OJ_REG_NEED_CONFIRM);
?>
