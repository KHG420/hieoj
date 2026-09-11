<?php
$cache_time=10;
$OJ_CACHE_SHARE=false;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');
require_once("./include/const.inc.php");
require_once("./include/my_func.inc.php");
// check user
$user=$_GET['user'];
if (!is_valid_user_name($user)){
	$view_errors= "No such User!";
	require("template/".$OJ_TEMPLATE."/error.php");
	exit(0);
}
$view_title=$user ."@".$OJ_NAME;
$sql="SELECT `school`,`email`,`nick`,`qq` FROM `users` WHERE `user_id`=?";
$result=pdo_query($sql,$user);
$row_cnt=count($result);
if ($row_cnt==0){
	$view_errors= "No such User!";
	require("template/".$OJ_TEMPLATE."/error.php");
	exit(0);
}

$sql="SELECT SUM(c.sim) as sim_num FROM `users` a LEFT JOIN (`solution` b RIGHT JOIN `sim` c ON c.s_id = b.solution_id) on a.user_id = b.user_id WHERE a.user_id=? GROUP BY a.user_id ORDER BY a.solved DESC";
$res=pdo_query($sql,$user);


$row=$result[0];
$school=$row['school'];
//$email=$row['email'];
$email = isset($_SESSION[$OJ_NAME.'_'.'user_id']) ? $row['email'] : '';
$nick=$row['nick'];
$qq_num = $row['qq'];
$chongFu=isset($res[0]['sim_num'])?intval($res[0]['sim_num']):0;

// count solved
$sql="SELECT count(DISTINCT problem_id) as `ac` FROM `solution` WHERE `user_id`=? AND `result`=4";
$result=pdo_query($sql,$user) ;
$row=$result[0];
$AC=$row['ac'];

$chongFu=sprintf ( "%.02lf%%", $AC>0 ? ($chongFu / $AC) : 0 );

// count submission
$sql="SELECT count(solution_id) as `Submit` FROM `solution` WHERE `user_id`=? and  problem_id>0";
$result=pdo_query($sql,$user) ;
$row=$result[0];
$Submit=$row['Submit'];

// update solved (带缓存，避免每次访问用户主页都写库)
$stat_key="userstat_".$user;
if(cache_get($stat_key)===false){
	$sql="UPDATE `users` SET `solved`='".strval($AC)."',`submit`='".strval($Submit)."' WHERE `user_id`=?";
	$result=pdo_query($sql,$user);
	cache_set($stat_key,1,300);
}
$sql="SELECT count(*) as `Rank` FROM `users` WHERE `solved`>?";
$result=pdo_query($sql,$AC);
$row=$result[0];
$Rank=intval($row[0])+1;

if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
	$sql="SELECT user_id,password,ip,`time` FROM `loginlog` WHERE `user_id`=? order by `time` desc LIMIT 0,10";
	$view_userinfo=pdo_query($sql,$user) ;
	echo "</table>";

}
$sql="SELECT result,count(1) FROM solution WHERE `user_id`=? AND result>=4 group by result order by result";
$result=pdo_query($sql,$user);
$view_userstat=array();
$i=0;
foreach($result as $row){
	$view_userstat[$i++]=$row;
}


$sql=	"SELECT UNIX_TIMESTAMP(date(in_date))*1000 md,count(1) c FROM `solution` where  `user_id`=?  group by md order by md desc ";
$result=pdo_query($sql,$user);//mysql_escape_string($sql));
$chart_data_all= array();
//echo $sql;

foreach($result as $row){
	$chart_data_all[$row['md']]=$row['c'];
}

$sql=	"SELECT UNIX_TIMESTAMP(date(in_date))*1000 md,count(1) c FROM `solution` where  `user_id`=? and result=4 group by md order by md desc ";
$result=pdo_query($sql,$user);//mysql_escape_string($sql));
$chart_data_ac= array();
//echo $sql;

foreach($result as $row){
	$chart_data_ac[$row['md']]=$row['c'];
}

/////////////////////////Template
// 按用户要求恢复原始 User Info 页面外观（sta_sty 模板）
require("template/sta_sty/userinfo.php");

?>