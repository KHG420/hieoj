<?php
$OJ_CACHE_SHARE=false;
$cache_time=300;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');
require_once('./include/memcache.php');
$view_title= $MSG_RANKLIST;

$where="";
$where_params=array();
if(isset($_GET['prefix']) || isset($_GET['xy']) || isset($_GET['nj']) || isset($_GET['school'])){
    $xy = isset($_GET['xy']) ? trim($_GET['xy']) : '';
    $nj = isset($_GET['nj']) ? trim($_GET['nj']) : '';
    $school = isset($_GET['school']) ? trim($_GET['school']) : '';
    $prefix = isset($_GET['prefix']) ? trim($_GET['prefix']) : '';
    $conds = array();
    // 安全修复：筛选参数一律白名单校验 + 绑定参数，杜绝 SQL 注入
    if ($xy != "") {
        if (preg_match('/^\d{2}$/', $xy)) {   // 学院代码为两位数字
            $conds[] = "a.xueYuan = ?";
            $where_params[] = $xy;
        }
    }
    if ($nj != "") {
        if (preg_match('/^\d{2}$/', $nj)) {   // 年级为两位数字
            $conds[] = "SUBSTR(a.school, -4, 2) = ?";
            $where_params[] = $nj;
        }
    }
    if ($school != "") {
        $conds[] = "a.school LIKE ?";
        $where_params[] = "%".addcslashes($school, "%_")."%";
    }
    if ($prefix != "") {
        $conds[] = "(a.user_id LIKE ? OR a.nick LIKE ?)";
        $where_params[] = "%".addcslashes($prefix, "%_")."%";
        $where_params[] = "%".addcslashes($prefix, "%_")."%";
    }
    if (count($conds) > 0) {
        $where = "where " . implode(" and ", $conds);
    } else {
        $where = "where a.defunct='N' ";
    }
}else{
    $where = "where a.defunct='N' ";
}
$rank = 0;
if(isset( $_GET ['start'] ))
    $rank = intval ( $_GET ['start'] );

if(isset($OJ_LANG)){
    require_once("./lang/$OJ_LANG.php");
}
$page_size=50;
if ($rank < 0)
    $rank = 0;

// 优化后的SQL查询（移除了时间范围查询）
$subquery = "SELECT a.user_id, a.school, a.nick, a.solved, a.submit, a.reg_time 
             FROM `users` a 
             $where 
             ORDER BY a.solved DESC, a.submit, a.reg_time 
             LIMIT " . strval($rank) . ", $page_size";
    
$sql = "SELECT t.user_id, t.school, t.nick, t.solved, t.submit, 
               IFNULL(SUM(c.sim), 0) as sim_num
        FROM ($subquery) t
        LEFT JOIN `solution` b ON t.user_id = b.user_id
        LEFT JOIN `sim` c ON b.solution_id = c.s_id
        GROUP BY t.user_id
        ORDER BY t.solved DESC, t.submit, t.reg_time";

if(count($where_params)>0){
    $result = mysql_query_cache($sql, ...$where_params);
}else{
    $result = mysql_query_cache($sql) ;
}
if($result) $rows_cnt=count($result);
else $rows_cnt=0;
$view_rank=Array();
$i=0;

for ( $i=0;$i<$rows_cnt;$i++ ) {

    $row=$result[$i];

    $rank ++;

    if($row['submit']>0)
    {
        $view_rank[$i][0]= $rank;
        $view_rank[$i][1]=  "<div class=center><a href='userinfo.php?user=" .htmlentities ( $row['user_id'],ENT_QUOTES,"UTF-8") . "'>" . $row['user_id'] . "</a>"."</div>";
        $view_rank[$i][2] = "<div class=center>" . htmlentities ( $row['school'] ,ENT_QUOTES,"UTF-8") ."</div>";
        $view_rank[$i][3]=  "<div class=center>" . htmlentities ( $row['nick'] ,ENT_QUOTES,"UTF-8") ."</div>";
        $view_rank[$i][4]=  "<div class=center><a href='status.php?user_id=" .htmlentities ( $row['user_id'],ENT_QUOTES,"UTF-8") ."&jresult=4'>" . $row['solved']."</a>"."</div>";
        $view_rank[$i][5]=  "<div class=center><a href='status.php?user_id=" . htmlentities ($row['user_id'],ENT_QUOTES,"UTF-8") ."'>" . $row['submit'] . "</a>"."</div>";

        if ($row['submit'] == 0)
            $view_rank[$i][5]= "0.000%";
        else
            $view_rank[$i][6]= sprintf ( "%.02lf%%", 100 * $row['solved'] / $row['submit'] );

        // 避免除以零错误
        $avg_sim = ($row['solved'] > 0) ? $row['sim_num'] / $row['solved'] : 0;
        $row['sim_num'] = sprintf("%.02lf", $avg_sim);

        $text = ($avg_sim >= 50) ? "style='color: red'" : "";
        $view_rank[$i][7] = "<div class='center' $text>" . htmlentities($row['sim_num'] . '%', ENT_QUOTES, "UTF-8") . "</div>";
    }
}

$sql = "SELECT count(1) as `mycount` FROM `users`";
$result = mysql_query_cache($sql);
$row=$result[0];
$view_total=$row['mycount'];

$xueYuan = array(array("电气与信息工程学院", "01"), array("机械工程学院", "02"), array("信息科学与工程学院", "03"), array("外国语学院", "04"),
    array("经济学院", "05"), array("材料与化工学院", "06"), array("商学院", "07"), array("纺织服装学院", "08"), array("智慧建造与能源工程学院", "09"),array("计算科学与电子学院", "10"), array("设计艺术学院", "12"), array("应用技术学院", "13"), array("国际教育学院", "17"), array("卓越工程师学院", "45"), array("体育科学与工程学院", "11"), array("智能科学与工程学院", "14"), array("医学工程技术学院", "47"));

$sql = "SELECT SUBSTR(`value`, -4, 2) as nj FROM schoolList GROUP BY nj ORDER BY nj desc LIMIT 1";
$result = mysql_query_cache($sql);
$row=$result[0][0];

$nianJi = array();
for ($i = 0; $i < 8; $i ++ ) {
    $nianJi[$i] = $row;
    $row --;
}

$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$prefix = isset($_GET['prefix']) ? $_GET['prefix'] : '';
$xy = isset($_GET['xy']) ? $_GET['xy'] : '';
$nj = isset($_GET['nj']) ? $_GET['nj'] : '';
$school = isset($_GET['school']) ? $_GET['school'] : '';

require("template/".$OJ_TEMPLATE."/ranklist.php");

if(file_exists('./include/cache_end.php'))
    require_once('./include/cache_end.php');
?>
