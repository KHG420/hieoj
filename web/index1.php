<?php
////////////////////////////Common head
$cache_time=300;
$OJ_CACHE_SHARE=true;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
$view_title= "Welcome To Online Judge";
$result=false;
if(isset($OJ_ON_SITE_CONTEST_ID)){
    header("location:contest.php?cid=".$OJ_ON_SITE_CONTEST_ID);
    exit();
}
///////////////////////////MAIN

$view_news="";
$sql=	"select * "
    ."FROM `news` "
    ."WHERE `defunct`!='Y'"
    ."ORDER BY `importance` ASC,`time` DESC "
    ."LIMIT 50";
$result=mysql_query_cache($sql);//mysql_escape_string($sql));
if (!$result){
    $view_news= "<h3>No News Now!</h3>";
}else{
    $view_news.= "<table width=96%>";

    foreach ($result as $row){
        $view_news.= "<tr><td><td><big><b>".htmlentities($row['title'],ENT_QUOTES,"UTF-8")."</b></big>-<small>[".htmlentities($row['user_id'],ENT_QUOTES,"UTF-8")."]</small></tr>";
        $view_news.= "<tr><td><td>".$row['content']."</tr>";
    }

    $view_news.= "<tr><td width=20%><td>This <a href=http://cm.baylor.edu/welcome.icpc>ACM/ICPC</a> OnlineJudge is a GPL product from <a href=https://github.com/zhblue/hustoj>hustoj</a></tr>";
    $view_news.= "</table>";
}
$view_apc_info="";

// 首页可视化：近 7 天提交趋势（按日聚合，走 in_date 索引 + 查询缓存；缺天补零）
$chart_data_all=array(); $chart_data_ac=array();
$sql="SELECT UNIX_TIMESTAMP(date(in_date))*1000 md,count(1) c FROM `solution` WHERE in_date>NOW()-INTERVAL 7 DAY GROUP BY md";
$result=mysql_query_cache($sql);
if($result) foreach($result as $row){ $chart_data_all[intval($row['md'])]=intval($row['c']); }
$sql="SELECT UNIX_TIMESTAMP(date(in_date))*1000 md,count(1) c FROM `solution` WHERE in_date>NOW()-INTERVAL 7 DAY AND result=4 GROUP BY md";
$result=mysql_query_cache($sql);
if($result) foreach($result as $row){ $chart_data_ac[intval($row['md'])]=intval($row['c']); }
$chart_days=array();
for($i=6;$i>=0;$i--){
    $ts=strtotime(date("Y-m-d", strtotime("-".$i." day")));
    $md=$ts*1000;
    $chart_days[$md]=array(
        "label"=>date("m-d",$ts),
        "all"=>isset($chart_data_all[$md])?$chart_data_all[$md]:0,
        "ac"=>isset($chart_data_ac[$md])?$chart_data_ac[$md]:0,
    );
}

/////////////////////////Template
require("template/".$OJ_TEMPLATE."/index.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
    require_once('./include/cache_end.php');
?>