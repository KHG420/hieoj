<?php
////////////////////////////Common head
	$cache_time=10;
	$OJ_CACHE_SHARE=false;
	require_once('./include/cache_start.php');
    require_once('./include/db_info.inc.php');
if(isset($OJ_REGISTER)&&!$OJ_REGISTER) exit(0);
	require_once('./include/setlang.php');
	$view_title= "Registe a new account";
	
///////////////////////////MAIN

$xueYuan = array(array("电气与信息工程学院", "01"), array("机械工程学院", "02"), array("信息科学与工程学院", "03"), array("外国语学院", "04"),
    array("经济学院", "05"), array("材料与化工学院", "06"), array("商学院", "07"), array("纺织服装学院", "08"), array("智慧建造与能源工程学院", "09"),array("计算科学与电子学院", "10"), array("设计艺术学院", "12"), array("应用技术学院", "13"), array("国际教育学院", "17"), array("卓越工程师学院", "45"), array("体育科学与工程学院", "11"), array("智能科学与工程学院", "14"), array("医学工程技术学院", "47"));

$sql = "SELECT SUBSTR(`value`, -4, 2) as nj FROM schoolList GROUP BY nj ORDER BY nj desc LIMIT 1";
$n_nj = pdo_query($sql)[0][0] - 4;
$school_sql = "SELECT `value` FROM schoolList WHERE SUBSTR(num, 1, 4) > 20".$n_nj." ORDER BY num";
$class = pdo_query($school_sql);

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
