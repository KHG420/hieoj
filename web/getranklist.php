<?php
header('Content-Type:application/json; charset=utf-8');
require_once('./include/db_info.inc.php');
require_once('./include/cache_start.php');
require_once('./include/memcache.php');
@session_write_close(); // 释放会话锁，避免轮询阻塞其他请求

// 安全修复：日期格式白名单校验 + 绑定参数，防止 SQL 注入；非法值回退为当月
$start = isset($_GET['start']) ? $_GET['start'] : '';
$end   = isset($_GET['end'])   ? $_GET['end']   : '';
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-01');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = date('Y-m-t');
if(strtotime($start)===false) $start = date('Y-m-01');
if(strtotime($end)===false)   $end   = date('Y-m-t');
if(strtotime($start)>strtotime($end)) { $t=$start; $start=$end; $end=$t; }
$num  = max(1, min(200, intval(isset($_GET['limit'])?$_GET['limit']:20)));
$page = max(1, intval(isset($_GET['page'])?$_GET['page']:1));


$sql_users = "SELECT users.user_id,school,nick,s.solved,t.submit FROM users
                                        inner join
                                        (select count(distinct problem_id) solved ,user_id from solution 
					where TO_DAYS(in_date)>=TO_DAYS(?) and TO_DAYS(in_date)<=TO_DAYS(?) and result=4 
					group by user_id order by solved desc ) s 
				on users.user_id=s.user_id
                                        inner join
                                        (select count( problem_id) submit ,user_id from solution 
					where TO_DAYS(in_date)>=TO_DAYS(?) and TO_DAYS(in_date)<=TO_DAYS(?)
					group by user_id order by submit desc ) t 
				on users.user_id=t.user_id
                                ORDER BY s.solved DESC,t.submit,reg_time  ";
//echo $sql_users;
$result_users = mysql_query_cache( $sql_users, $start, $end, $start, $end);
$data = array();

$i = 1;
$l = ($page - 1) * $num + 1;
$r = $l + $num - 1;
$cnt = 0;
if ( $result_users ) {
    foreach ( $result_users as $row ) {
        if ($i >= $l && $i <= $r) {
            $row = [
                "rank" => $i,
                "user_id" => "<a href=\"userinfo.php?user=".htmlentities($row["user_id"],ENT_QUOTES,"UTF-8")."\">".htmlentities($row["user_id"],ENT_QUOTES,"UTF-8")."</a>",
                "school" => htmlentities($row["school"],ENT_QUOTES,"UTF-8"),
                "nick" => htmlentities($row["nick"],ENT_QUOTES,"UTF-8"),
                "solved" => $row["solved"],
                "submit" => $row["submit"],
            ];
            $data[$cnt ++] = $row;
        }
        $i ++;
    }
}
$res = [
    "code" => 0,
    "msg" => "success",
    "count" => $i - 1,
    "data" => $data,
];
exit(json_encode($res,256));
