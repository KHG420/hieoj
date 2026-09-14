<?php
$OJ_CACHE_SHARE=false;
$cache_time=300;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');
require_once('./include/memcache.php');
require_once('./include/academic_directory.php');
$view_title= $MSG_RANKLIST;

$rankRange = ($_GET['range'] ?? '') === 'all' ? 'all' : 'year';
$rankSince = date('Y-m-d', strtotime('-1 year'));
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
        if (mb_strlen($xy, 'UTF-8') <= 100) {   // users.xueYuan 保存学院名称
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

// The same period governs solved problems, submissions and similarity totals.
$periodParams = array();
$rankSource = '`users` a';
if ($rankRange === 'year') {
    $rankSource = "(SELECT u.*, COALESCE(y.period_solved,0) period_solved, COALESCE(y.period_submit,0) period_submit
        FROM users u LEFT JOIN (SELECT user_id, COUNT(*) period_submit,
        COUNT(DISTINCT CASE WHEN result=4 THEN problem_id END) period_solved
        FROM solution WHERE problem_id>0 AND in_date>=? AND in_date<=NOW() GROUP BY user_id) y ON y.user_id=u.user_id) a";
    $periodParams[] = $rankSince.' 00:00:00';
}
$solvedColumn = $rankRange === 'year' ? 'a.period_solved' : 'a.solved';
$submitColumn = $rankRange === 'year' ? 'a.period_submit' : 'a.submit';
$where .= " AND a.defunct='N' AND $submitColumn>0";
$subquery = "SELECT a.user_id,a.school,a.nick,$solvedColumn solved,$submitColumn submit,a.reg_time
    FROM $rankSource $where ORDER BY solved DESC,submit,a.reg_time LIMIT $rank,$page_size";
$similarityPeriod = $rankRange === 'year' ? ' AND b.in_date>=? AND b.in_date<=NOW() AND b.problem_id>0' : '';
$sql = "SELECT t.user_id,t.school,t.nick,t.solved,t.submit,IFNULL(SUM(c.sim),0) sim_num
    FROM ($subquery) t LEFT JOIN solution b ON t.user_id=b.user_id $similarityPeriod
    LEFT JOIN sim c ON b.solution_id=c.s_id GROUP BY t.user_id,t.school,t.nick,t.solved,t.submit,t.reg_time
    ORDER BY t.solved DESC,t.submit,t.reg_time";
$queryParams = array_merge($periodParams,$where_params,$periodParams);
$result = mysql_query_cache($sql,...$queryParams);
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

$sql = "SELECT count(1) as `mycount` FROM $rankSource $where";
$result = mysql_query_cache($sql,...array_merge($periodParams,$where_params));
$row=$result[0];
$view_total=$row['mycount'];

$xueYuan = academic_directory_colleges();

// 年级选项来自可识别班级编号前 4 位（19/20xx），不再从班级名称反推
$nianJi = academic_directory_years(8);
if (count($nianJi) === 0) {
    $nianJi = array();
    for ($i = 0; $i < 8; $i ++) {
        $nianJi[] = str_pad((string)(intval(date('y')) - $i), 2, '0', STR_PAD_LEFT);
    }
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
