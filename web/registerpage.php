<?php
////////////////////////////Common head
	$cache_time=10;
	$OJ_CACHE_SHARE=false;
	require_once('./include/cache_start.php');
    require_once('./include/db_info.inc.php');
if(isset($OJ_REGISTER)&&!$OJ_REGISTER) exit(0);
	require_once('./include/setlang.php');
	require_once('./include/academic_directory.php');
	$view_title= "Registe a new account";
	
///////////////////////////MAIN

// 学院与班级统一来自 collegiate / schoolList，年级只按可识别编号前 4 位派生
$xueYuan = academic_directory_colleges();
$latest_year = academic_directory_latest_year();
$n_nj = ($latest_year !== null ? intval(substr($latest_year, 2, 2)) : intval(date('y'))) - 4;
$class = academic_directory_recent_classes(2000 + $n_nj);

////////从教务系统爬取班级信息导入数据库中
//$url = 'http://59.71.0.16/jwweb/ZNPK/KBFB_ClassSel.aspx';
//$str =  file_get_contents($url);
//
//$len = strlen($str);
//
//$i = strpos($str, "Sel_XZBJ");
//$str = substr($str, $i+10, $len);
//$len = strlen($str);
//
//$i = strpos($str, "Sel_XZBJ");
//$str = substr($str, $i+10, $len);
//$len = strlen($str);
//
//$i = strpos($str, "Sel_XZBJ");
//$str = substr($str, $i+10, $len);
//$len = strlen($str);
//
//$str = substr($str, 17, $len-17);
//
//$ss = $str;
//$num = array();
//$ii = 0;
//while (($j = strpos($ss, "value")) != false) {
//	$len = $ss;
//	$ss = substr($ss, $j + 6, $len - 6);
//	$num[$ii] = substr($ss, 0, 10);
////	echo $num[$ii]."<br>";
//	if (strcmp($num[$ii], "2021010205") == 0) break;
//	$ii ++;
//}
//echo $ii;
//
//$i = strpos($str, "input");
//$str = substr($str, 0, $i-1);
//$len = strlen($str);
//
//$str = str_replace("<option", ",<option", $str);
//
//$str = strip_tags($str);
//$str = mb_convert_encoding($str ,"utf-8","gbk");
//
//$classes = explode(",", $str);
//
//$class = array();
//$i = 0;
//$len = count($classes);
//foreach ($classes as $row) {
//	if ($i == $len - 1) {
//		$class[$i] = str_replace(PHP_EOL, "", str_replace("\t", "", $row));
//	}else if ($i != 0) {
//		$class[$i] = $row;
//	}
////	echo $class[$i];
//	$i++;
//}
//$len = count($classes);
//echo $len;
//for ($i = 0, $j = 1; $j < $len; $i ++, $j ++ ) {
//	echo $num[$i]." ".$classes[$j]."<br>";
//
//	$sql="INSERT INTO `schoolList`("
//		."`num`,`value`,`join_time`)"
//		."VALUES(?,?,NOW())";
//	$rows=pdo_query($sql,$num[$i],$classes[$j]);
//
//}

/////////////////////////Template
require("template/".$OJ_TEMPLATE."/registerpage.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
	require_once('./include/cache_end.php');
?>
