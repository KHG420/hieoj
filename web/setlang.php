<?php require_once("./include/db_info.inc.php");
	require_once("./include/my_func.inc.php");
	$newlang=strval($_GET['lang']);
	if(is_valid_user_name($newlang)&&strlen($newlang)<3){
		$_SESSION[$OJ_NAME.'_'.'OJ_LANG']=$newlang;
	}
	// 交互连贯：服务端回跳，避免 history 回退导致语言切换不生效
	$back="index.php";
	if(isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST'])!==false){
		$back=$_SERVER['HTTP_REFERER'];
	}
	header("Location: ".$back);
	exit(0);
?>
