<?php
$cache_time=1;
require_once('./include/cache_start.php');
    require_once("./include/db_info.inc.php");
	require_once("./include/setlang.php");
	$view_title= "LOGIN";

	if (isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
	echo "<a href=logout.php>Please logout First!</a>";
	exit(1);
}
$xueYuan = array(array("电气与信息工程学院", "01"), array("机械工程学院", "02"), array("信息科学与工程学院", "03"), array("外国语学院", "04"),
	array("经济学院", "05"), array("材料与化工学院", "06"), array("管理学院", "07"), array("纺织服装学院", "08"), array("建筑工程学院", "09"),
	array("计算科学与电子学院", "10"), array("设计艺术学院", "12"), array("应用技术学院", "13"), array("国际教育学院", "17"), array("卓越工程师学院", "45"));

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
$sql = "SELECT SUBSTR(`value`, -4, 2) as nj FROM schoolList GROUP BY nj ORDER BY nj desc LIMIT 1";
$n_nj = pdo_query($sql)[0][0] - 4;
$school_sql = "SELECT `value` FROM schoolList WHERE SUBSTR(num, 1, 4) > 20".$n_nj." ORDER BY num";
$class = pdo_query($school_sql);

/////////////////////////Template
require("template/".$OJ_TEMPLATE."/loginpage.php");
//require("template/sta_sty/loginpage.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
	require_once('./include/cache_end.php');
?>

