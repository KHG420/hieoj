<?php 
    require_once("./include/db_info.inc.php");
    require_once("./include/cache_layer.php");
    require_once('./include/setlang.php');
    $vcode="";
    if(isset($_POST['vcode']))	$vcode=trim($_POST['vcode']);
    if($OJ_VCODE&&(!isset($_SESSION[$OJ_NAME.'_'."vcode"])||$vcode!==$_SESSION[$OJ_NAME.'_'."vcode"]||$vcode==""||$vcode==null) ){
		echo "<script language='javascript'>\n";
		echo "alert('Verify Code Wrong!');\n";
		echo "history.go(-1);\n";
		echo "</script>";
		exit(0);
    }
    $view_errors="";
	require_once("./include/login-".$OJ_LOGIN_MOD.".php");
    $user_id=$_POST['user_id'];
    $password=$_POST['password'];
 
    $user_id= stripslashes ( $user_id);
    $password= stripslashes ( $password);
   
   // 安全修复：登录限流（同一账号+IP 10 分钟内失败 5 次则延时并提示）
   $login_key="loginfail_".md5($user_id."|".($_SERVER['REMOTE_ADDR']));
   $fail_cnt=intval(cache_get($login_key));
   if($fail_cnt>=5){
	   sleep(3);
   }
   $sql = "SELECT COUNT(*) FROM `users` WHERE defunct='N' AND user_id=? AND (register_num=1 OR ISNULL(register_num))";
   $res = pdo_query($sql, $user_id);
   if ($res[0][0] == 0) {
	   $str="用户名或密码错误。"; // 统一错误提示，避免用户枚举
	   cache_set($login_key, $fail_cnt+1, 600);
	   #$str=mb_convert_encoding($str ,"gbk","utf-8");
	   echo "<script language='javascript'>\n";
	   echo "alert('".$str."');\n";
//	   echo "alert('UserName or Password Wrong!');\n";
	   echo "history.go(-1);\n";
	   echo "</script>";
	   exit(0);
   }

    $sql="SELECT `rightstr` FROM `privilege` WHERE `user_id`=?";
    $login=check_login($user_id,$password);
    if ($login)
    {
		$_SESSION[$OJ_NAME.'_'.'user_id']=$login;

		// 安全修复：登录成功后重新生成会话 ID，防止会话固定攻击；清空失败计数
		@session_regenerate_id(true);
		cache_set($login_key, 0, 600);
		$result=pdo_query($sql,$login);
		
		foreach ($result as $row)
			$_SESSION[$OJ_NAME . '_' . $row['rightstr']] = true;

		// 交互连贯：登录成功后回跳到来源页（仅允许站内相对路径，防开放重定向）
		$target="index.php";
		if(isset($_POST['url'])&&is_string($_POST['url'])){
			$u=trim($_POST['url']);
			if($u!='' && strpos($u,':')===false && strpos($u,'//')===false && strpos($u,'..')===false
			   && $u[0]!=='/' && preg_match('#^[a-zA-Z0-9_./?=&%-]+$#',$u)){
				$target=$u;
			}
		}
		echo "<script language='javascript'>\n";
		if($OJ_NEED_LOGIN)
			echo "window.location.href='index.php';\n";
		else
			echo "window.location.href='".htmlentities($target,ENT_QUOTES,"UTF-8")."';\n";
		echo "</script>";
	}else{
		if($view_errors){
			require("template/".$OJ_TEMPLATE."/error.php");
		}else{
			// 安全修复：失败计数与统一提示
			cache_set($login_key, (isset($fail_cnt)?$fail_cnt:0)+1, 600);
			$str="用户名或密码错误。";
			echo "<script language='javascript'>\n";
			echo "alert('".$str."');\n";
//			echo "alert('UserName or Password Wrong!');\n";
			echo "history.go(-1);\n";
			echo "</script>";
		}
	}
?>
