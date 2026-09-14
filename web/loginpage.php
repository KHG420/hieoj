<?php
$cache_time=1;
require_once('./include/cache_start.php');
    require_once("./include/db_info.inc.php");
	require_once("./include/setlang.php");
	require_once("./include/academic_directory.php");
	$view_title= "LOGIN";

	if (isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
	echo "<a href=logout.php>Please logout First!</a>";
	exit(1);
}
$xueYuan = academic_directory_colleges();

// 交互连贯：记录站内来源页，登录成功后回跳（仅限站内相对路径）
$login_target="";
if(isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST'])!==false){
    $ref_path = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    $ref_query = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
    if($ref_path!==false && $ref_path!==null){
        $bn = basename($ref_path);
        if(!in_array($bn, array("loginpage.php","login.php","logout.php","registerpage.php","lostpassword.php"), true)){
            $login_target = $ref_path . ($ref_query ? "?".$ref_query : "");
        }
    }
}
$latest_year = academic_directory_latest_year();
$n_nj = ($latest_year !== null ? intval(substr($latest_year, 2, 2)) : intval(date('y'))) - 4;
$class = academic_directory_recent_classes(2000 + $n_nj);

/////////////////////////Template
require("template/".$OJ_TEMPLATE."/loginpage.php");
//require("template/sta_sty/loginpage.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
	require_once('./include/cache_end.php');
?>

