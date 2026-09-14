<?php	$cache_time=10;
	$OJ_CACHE_SHARE=false;
	require_once('./include/cache_start.php');
    require_once('./include/db_info.inc.php');
	require_once('./include/setlang.php');
	require_once('./include/academic_directory.php');
	$view_title= "Welcome To Online Judge";
	if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
		$view_errors= "<a href=./loginpage.php>$MSG_Login</a>";
		
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}

$sql="SELECT `user_id`, `email`, UNIX_TIMESTAMP(reg_time) as time,`nick`,`school`, `xueYuan`,`qq`,`phone` FROM `users` WHERE `user_id`=?";
$result=pdo_query($sql,$_SESSION[$OJ_NAME.'_'.'user_id']);
 $row=$result[0];

$xueYuan = academic_directory_colleges();

$latest_year = academic_directory_latest_year();
$n_nj = ($latest_year !== null ? intval(substr($latest_year, 2, 2)) : intval(date('y'))) - 4;
$class = academic_directory_recent_classes_by_college(2000 + $n_nj);

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

