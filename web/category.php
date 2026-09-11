<?php
////////////////////////////Common head
$cache_time=60;
$OJ_CACHE_SHARE=false;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
$view_title= "Welcome To Online Judge";
$result=false;
///////////////////////////MAIN

$i = 0;
$title = array();
$category=array();
// 性能优化：一次查询取出全部分类，PHP 内分组，避免逐类别查询
$sql = "SELECT `content-1`,`content-2` FROM `category` WHERE `status`=0 ORDER BY `priority`";
$result = pdo_query($sql);
$tmp_cat = array();
foreach ($result as $row) {
	if(!in_array($row[0], $title, true)) $title[] = $row[0];
	$tmp_cat[$row[0]][] = $row[1];
}
foreach ($title as $t) {
	$category[$i] = isset($tmp_cat[$t]) ? $tmp_cat[$t] : array();
	$i ++;
}

//	$view_category="";
//	$sql=	"SELECT `content-1`,`content-2`,`priority` FROM `category` ";
//	$result=mysql_query_cache($sql);//mysql_escape_string($sql));
//	$i = 0;
//	$category=array();
//	foreach ($result as $row){
//		$category[$i][0] = $row['content-1']."-".$row['content-2'];
//		//$category[$i][1] = $row['COUNT'];
////		echo "$category[$i][0].sasa.$category[0][1]";
////		$cate=explode(" ",$row['source']);
////		foreach($cate as $cat){
////			array_push($category[0],trim($cat));
////		}
////		$cate=explode(" ",$row['COUNT']);
////		foreach($cate as $cat){
////			array_push($category[1],trim($cat));
////		}
//		$i++;
//	}
//	$category=array_unique($category);
//	if (!$result){
//		$view_category= "<h3>No Category Now!</h3>";
//	}else{
//		$view_category.= "<div><p>";
//		foreach ($category as $cat){
//			if(trim($cat)=="") continue;
//			$view_category.= "<a class='btn btn-primary' href='problemset.php?search=".htmlentities($cat,ENT_QUOTES,'UTF-8')."'>".$cat."</a>&nbsp;";
//		}
//
//		$view_category.= "</p></div>";
//	}

/////////////////////////Template
///
require("template/".$OJ_TEMPLATE."/category.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
	require_once('./include/cache_end.php');
?>
