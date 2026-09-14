<?php	$cache_time=10;
	$OJ_CACHE_SHARE=false;
	require_once('./include/cache_start.php');
    require_once('./include/db_info.inc.php');
	require_once('./include/setlang.php');
	$view_title= "Welcome To Online Judge";
	if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
		$view_errors= "<a href=./loginpage.php>$MSG_Login</a>";
		
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}

$sql="SELECT `user_id`, `email`, UNIX_TIMESTAMP(reg_time) as time,`nick`,`school`, `xueYuan`,`qq`,`phone` FROM `users` WHERE `user_id`=?";
$result=pdo_query($sql,$_SESSION[$OJ_NAME.'_'.'user_id']);
 $row=$result[0];

$xueYuan = array(array("电气与信息工程学院", "01"), array("机械工程学院", "02"), array("信息科学与工程学院", "03"), array("外国语学院", "04"),
	array("经济学院", "05"), array("材料与化工学院", "06"), array("商学院", "07"), array("纺织服装学院", "08"), array("智慧建造与能源工程学院", "09"),
	array("计算科学与电子学院", "10"), array("设计艺术学院", "12"), array("应用技术学院", "13"), array("国际教育学院", "17"), array("卓越工程师学院", "45"), array("体育科学与工程学院", "11"), array("智能科学与工程学院", "14"), array("医学工程技术学院", "47"));

$sql = "SELECT SUBSTR(`value`, -4, 2) as nj FROM schoolList GROUP BY nj ORDER BY nj desc LIMIT 1";
$n_nj = pdo_query($sql)[0][0] - 4;
$school_sql = "SELECT SUBSTR(num, 5, 2) as xy_num, `value` FROM schoolList WHERE SUBSTR(num, 1, 4) > 20".$n_nj." ORDER BY num";
$class = pdo_query($school_sql);

$classes = array();
foreach ($xueYuan as $x) {
	$classes[$x[1]] = array();
	foreach ($class as $c) {
		if (strcmp($c["xy_num"], $x[1]) == 0) {
			$len = count($classes[$x[1]]);
			$classes[$x[1]][$len] = $c['value'];
		}
	}
}

//
//foreach ($classes['03'] as $j)
//	echo $j."<br>";


/////////////////////////Template
require("template/".$OJ_TEMPLATE."/modifypage2.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
	require_once('./include/cache_end.php');
?>

