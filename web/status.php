<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

////////////////////////////Common head
$cache_time=2;
$OJ_CACHE_SHARE=false;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
$view_title= "$MSG_STATUS";



require_once("./include/my_func.inc.php");
if(isset($OJ_LANG)){
    require_once("./lang/$OJ_LANG.php");
}
require_once("./include/const.inc.php");

if($OJ_TEMPLATE!="classic")
    $judge_color=Array(
        "btn gray",       // 0
        "btn btn-info",   // 1
        "btn btn-warning",// 2
        "btn btn-warning",// 3
        "btn btn-success",// 4: Accepted
        "btn btn-danger", // 5: Presentation Error
        "btn btn-danger", // 6: Wrong Answer
        "btn btn-warning",// 7: Time Limit Exceeded
        "btn btn-warning",// 8: Memory Limit Exceeded
        "btn btn-warning",// 9: Output Limit Exceeded
        "btn btn-warning",// 10: Runtime Error
        "btn btn-warning",// 11: Compile Error
        "btn btn-warning",// 12
        "btn btn-info",   // 13
       "btn btn-secondary", // 14: 新增状态
        "btn btn-secondary", // 15: 新增状态
        "btn btn-secondary", // 16: 新增状态
        "btn btn-secondary", // 17: 新增状态
        "btn btn-secondary", // 18: 新增状态
        "btn btn-secondary", // 19: 新增状态
        "btn btn-secondary", // 20: 新增状态
        "btn btn-secondary", // 21: 新增状态
        "btn btn-secondary"  // 22: 新增状态
    );

$str2="";
$lock=false;
$lock_time=date("Y-m-d H:i:s",time());
$sql="WHERE solution.problem_id>0 ";

// ============================================================
// 反枚举（2026-09-10，详见 /opt/hnieoj-docker/README-operations.md）
//   背景：爬虫靠 /status.php?user_id=<学号> 逐个遍历，把提交记录全量抓走。
//         实测 GPTBot 枚举 1510 个不同 user_id、ClaudeBot 234 个，
//         而正常访客与合法搜索引擎只有个位数到十位数。
//   策略：匿名访客不得按他人 user_id 过滤；非特权用户限制翻页深度。
//         本人 / 管理员 / source_browser 不受影响。
// ============================================================
$OJ_ANON_MAX_SCAN = 20000;
$__oj_self = isset($_SESSION[$OJ_NAME.'_'.'user_id']) ? $_SESSION[$OJ_NAME.'_'.'user_id'] : "";
$__oj_logged_in = ($__oj_self !== "" && $__oj_self !== "guest");
$__oj_privileged = $__oj_logged_in
    || isset($_SESSION[$OJ_NAME.'_'.'administrator'])
    || isset($_SESSION[$OJ_NAME.'_'.'source_browser']);
if (isset($_GET['cid'])){
    $cid=intval($_GET['cid']);
    $sql=$sql." AND `contest_id`='$cid' and num>=0 ";
    $str2=$str2."&cid=$cid";
    $sql_lock="SELECT `start_time`,`title`,`end_time` FROM `contest` WHERE `contest_id`=?";
    $result=pdo_query($sql_lock,$cid) ;
    $rows_cnt=count($result);
    $start_time=0;
    $end_time=0;
    if ($rows_cnt>0){
        $row=$result[0];
        $start_time=strtotime($row[0]);
        $title=$row[1];
        $end_time=strtotime($row[2]);
    }
    $lock_time=$end_time-($end_time-$start_time)*$OJ_RANK_LOCK_PERCENT;
    //$lock_time=date("Y-m-d H:i:s",$lock_time);
    $time_sql="";
    //echo $lock.'-'.date("Y-m-d H:i:s",$lock);
    if(time()>$lock_time&&time()<$end_time){
        //$lock_time=date("Y-m-d H:i:s",$lock_time);
        //echo $time_sql;
        $lock=true;
    }else{
        $lock=false;
    }

    //require_once("contest-header.php");
}else{
    //require_once("oj-header.php");
    if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])
        ||isset($_SESSION[$OJ_NAME.'_'.'source_browser'])
        ||(isset($_SESSION[$OJ_NAME.'_'.'user_id'])
            &&(isset($_GET['user_id'])&&$_GET['user_id']==$_SESSION[$OJ_NAME.'_'.'user_id']))
    ){
        if ($_SESSION[$OJ_NAME.'_'.'user_id']!="guest")
            $sql="WHERE 1 ";
    }else{
        $sql="WHERE solution.problem_id>0 ";
    }
}
$start_first=true;
$order_str=" ORDER BY `solution_id` DESC ";



// check the top arg
if (isset($_GET['top'])){
    $top=strval(intval($_GET['top']));
    // 反枚举：非特权用户限制翻页深度，阻断逐页全量拉取
    if ($top != -1 && !$__oj_privileged) {
        $__oj_max_id = 0;
        $__oj_r = pdo_query("SELECT MAX(`solution_id`) FROM `solution`");
        if ($__oj_r && isset($__oj_r[0][0])) $__oj_max_id = intval($__oj_r[0][0]);
        $__oj_floor = $__oj_max_id - $OJ_ANON_MAX_SCAN;
        if ($__oj_floor > 0 && intval($top) < $__oj_floor) {
            $top = strval($__oj_floor);
            $_GET['top'] = $top;
        }
    }
    if ($top!=-1) $sql=$sql."AND `solution_id`<='".$top."' ";
}

// check the problem arg
$problem_id="";
if (isset($_GET['problem_id'])&&$_GET['problem_id']!=""){

    if(isset($_GET['cid'])){
        $problem_id=htmlentities($_GET['problem_id'],ENT_QUOTES,'UTF-8');
        $num=strpos($PID,$problem_id);
        $sql=$sql."AND solution.num='".$num."' ";
        $str2=$str2."&problem_id=".$problem_id;

    }else{
        $problem_id=strval(intval($_GET['problem_id']));
        if ($problem_id!='0'){
            $sql=$sql."AND solution.problem_id='".$problem_id."' ";
            $str2=$str2."&problem_id=".$problem_id;
        }
        else $problem_id="";
    }
}
// check the user_id arg
$user_id="";
if(isset($OJ_ON_SITE_CONTEST_ID)&&$OJ_ON_SITE_CONTEST_ID>0&&!isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
    $_GET['user_id']=$_SESSION[$OJ_NAME.'_'.'user_id'];
}
if (isset($_GET['user_id'])){
    $user_id=trim($_GET['user_id']);
    // 反枚举：只有本人 / 管理员 / source_browser 能按 user_id 过滤。
    // 匿名访客 $__oj_self 为空，任何 user_id 都会被清空，无法再逐个学号遍历。
    if (!isset($_SESSION[$OJ_NAME.'_'.'administrator'])
        && !isset($_SESSION[$OJ_NAME.'_'.'source_browser'])
        && $user_id !== $__oj_self) {
        $user_id = "";
    }
    if (is_valid_user_name($user_id) && $user_id!=""){
        // 安全修复：统一使用绑定参数，不再拼接 SQL
        $sql=$sql."AND solution.user_id=? ";
        if ($str2!="") $str2=$str2."&";
        $str2=$str2."user_id=".urlencode($user_id);
    }else $user_id="";
}
if (isset($_GET['language'])) $language=intval($_GET['language']);
else $language=-1;

if ($language>count($language_ext) || $language<0) $language=-1;
if ($language!=-1){
    $sql=$sql."AND solution.language='".($language)."' ";
    $str2=$str2."&language=".$language;
}
if (isset($_GET['jresult'])) $result=intval($_GET['jresult']);
else $result=-1;

if ($result>22 || $result<0) $result=-1;
if ($result!=-1&&!$lock){
    $sql=$sql."AND `result`='".($result)."' ";
    $str2=$str2."&jresult=".$result;
}



if($OJ_SIM){
    // $old=$sql;
    $sql="select solution.*,users.nick,sim.*,problem.title from solution solution left JOIN `users` users on solution.user_id = users.user_id left join `sim` sim on solution.solution_id=sim.s_id left join `problem` problem on solution.problem_id=problem.problem_id ".$sql;
//    echo $sql;
    if(isset($_GET['showsim'])&&intval($_GET['showsim'])>0){
        $showsim=intval($_GET['showsim']);
        $sql.=" and sim.sim>=$showsim";
        $str2.="&showsim=$showsim";
    }

    //$sql=$sql.$order_str." LIMIT 20";
}else{
    $sql="select * from `solution` left join `users` users on solution.user_id=users.user_id left join `problem` problem on solution.problem_id=problem.problem_id".$sql;
}
//echo $sql;






$sql=$sql.$order_str." LIMIT 20";
//echo $sql;




if ($user_id !== ""){
    $result = pdo_query($sql,$user_id);
}else{
    $result = mysql_query_cache($sql);
}

if($result) $rows_cnt=count($result);
else $rows_cnt=0;
// 性能优化：预取本页涉及的比赛结束时间，避免每行一次 is_running() 查询
$contest_times=array();
$now_ts=time();
if($rows_cnt>0){
    $cids=array();
    foreach($result as $row){ $c=intval($row['contest_id']); if($c>0) $cids[$c]=1; }
    if(!empty($cids)){
        $cid_list=implode(",", array_keys($cids));
        $ct=pdo_query("SELECT contest_id, end_time FROM contest WHERE contest_id IN ($cid_list)");
        foreach($ct as $crow){ $contest_times[$crow['contest_id']]=strtotime($crow['end_time']); }
    }
}
$top=$bottom=-1;
$cnt=0;
if ($start_first){
    $row_start=0;
    $row_add=1;
}else{
    $row_start=$rows_cnt-1;
    $row_add=-1;
}

$view_status=Array();

$last=0;
for ($i=0;$i<$rows_cnt;$i++){

    $row=$result[$i];

    if ($row['user_id'] == "ACTest" && $_SESSION[$OJ_NAME.'_'.'user_id'] != "ACTest" && !isset($_SESSION[$OJ_NAME.'_'.'administrator']) )
        continue;

    //$view_status[$i]=$row;
    if($i==0&&$row['result']<4) $last=$row['solution_id'];


    if ($top==-1) $top=$row['solution_id'];
    $bottom=$row['solution_id'];
    $cid_row=intval($row['contest_id']);
    $is_running_flag=($cid_row>0&&isset($contest_times[$cid_row]))?($contest_times[$cid_row]>$now_ts):false;
    $flag=(!$is_running_flag) ||
        isset($_SESSION[$OJ_NAME.'_'.'source_browser']) ||
        isset($_SESSION[$OJ_NAME.'_'.'administrator']) ||
        (isset($_SESSION[$OJ_NAME.'_'.'user_id'])&&!strcmp($row['user_id'],$_SESSION[$OJ_NAME.'_'.'user_id']));

    $cnt=1-$cnt;


    $view_status[$i][0]=$row['solution_id'];

    // 安全修复：用户可控字段（user_id/nick/title/ip）统一转义输出，防止存储型 XSS
    $oj_uid = htmlentities($row['user_id'], ENT_QUOTES, "UTF-8");
    $oj_nick = htmlentities($row['nick'], ENT_QUOTES, "UTF-8");
    $oj_title = htmlentities($row['title'], ENT_QUOTES, "UTF-8");
    $oj_ip = htmlentities($row['ip'], ENT_QUOTES, "UTF-8");
    if ($row['contest_id']>0) {

        if (isset($_SESSION[$OJ_NAME.'_'.'administrator']))
            $view_status[$i][1]= "<a href='contestrank.php?cid=".$row['contest_id']."&user_id=".$oj_uid."#".$oj_uid."' title='".$oj_ip."'>".$oj_uid."</a>";
        else
            $view_status[$i][1]= "<a href='contestrank.php?cid=".$row['contest_id']."&user_id=".$oj_uid."#".$oj_uid."'>".$oj_uid."</a>";
    }else{
        if (isset($_SESSION[$OJ_NAME.'_'.'administrator']))
            $view_status[$i][1]= "<a href='userinfo.php?user=".$oj_uid."' title='".$oj_ip."'>".$oj_uid."</a>";
        else
            $view_status[$i][1]= "<a href='userinfo.php?user=".$oj_uid."'>".$oj_uid."</a>";
    }

    $view_status[$i][2]=$oj_nick;

    if ($row['contest_id']>0) {
        $view_status[$i][3]= "<div class=center><a href='problem.php?cid=".$row['contest_id']."&pid=".$row['num']."'>";
        if(isset($cid)){
            $view_status[$i][3].= $PID[$row['num']];
        }else{
            $view_status[$i][3].= $row['problem_id'];
        }
        $view_status[$i][3].="</div></a>";
    }else{
        $view_status[$i][3]= "<div class=center><a href='problem.php?id=".$row['problem_id']."'>".$row['problem_id']."</a></div>";
    }

    if ($row['contest_id']>0) {
        $view_status[$i][4]= "<div class=center><a href='problem.php?cid=".$row['contest_id']."&pid=".$row['num']."'>";

        $view_status[$i][4].= $oj_title;

        $view_status[$i][4].="</div></a>";
    }else{
        $view_status[$i][4]= "<div class=center><a href='problem.php?id=".$row['problem_id']."'>".$oj_title."</a></div>";
    }
    switch($row['result']){
        case 4:
            $MSG_Tips=$MSG_HELP_AC;break;
        case 5:
            $MSG_Tips=$MSG_HELP_PE;break;
        case 6:
            $MSG_Tips=$MSG_HELP_WA;break;
        case 7:
            $MSG_Tips=$MSG_HELP_TLE;break;
        case 8:
            $MSG_Tips=$MSG_HELP_MLE;break;
        case 9:
            $MSG_Tips=$MSG_HELP_OLE;break;
        case 10:
            $MSG_Tips=$MSG_HELP_RE;break;
        case 11:
            $MSG_Tips=$MSG_HELP_CE;break;
        default: $MSG_Tips="";

    }

    $view_status[$i][5]="<span class='hidden' style='display:none' result='".$row['result']."' ></span>";
    if (intval($row['result'])==11 && ((isset($_SESSION[$OJ_NAME.'_'.'user_id'])&&$row['user_id']==$_SESSION[$OJ_NAME.'_'.'user_id']) || isset($_SESSION[$OJ_NAME.'_'.'source_browser']))){
        $view_status[$i][5].= "<a href='ceinfo.php?sid=".$row['solution_id']."' class='".$judge_color[$row['result']]."'  title='$MSG_Tips'>".$MSG_Compile_Error."";

        if ($row['result']!=4&&isset($row['pass_rate'])&&$row['pass_rate']>0&&$row['pass_rate']<.98)
            $view_status[$i][5].= (100-$row['pass_rate']*100)."%</a>";
        else
            $view_status[$i][5].="</a>";

    }else if ((((intval($row['result'])==5||intval($row['result'])==6||intval($row['result'])==7)&&($OJ_SHOW_DIFF||isset($_SESSION[$OJ_NAME.'_'.'source_browser'])))||$row['result']==10||$row['result']==13) && ((isset($_SESSION[$OJ_NAME.'_'.'user_id'])&&$row['user_id']==$_SESSION[$OJ_NAME.'_'.'user_id']) || isset($_SESSION[$OJ_NAME.'_'.'source_browser']))){
        $view_status[$i][5].= "<a href='reinfo.php?sid=".$row['solution_id']
            ."' class='".$judge_color[$row['result']]."' title='$MSG_Tips'>".$judge_result[$row['result']]."";
        if ($row['result']!=4&&isset($row['pass_rate'])&&$row['pass_rate']>0&&$row['pass_rate']<.98)
            $view_status[$i][5].= (100-$row['pass_rate']*100)."%</a>";
        else
            $view_status[$i][5].= "</a>";

    }else{
        if(!$lock||$lock_time>$row['in_date']||$row['user_id']==$_SESSION[$OJ_NAME.'_'.'user_id']){
            /* OJ_STATUS_SIMILARITY_BEGIN (bounded render block for web/tests/status_similarity_test.php) */
            if($OJ_SIM&&$row['sim']>50&&$row['sim_s_id']!=$row['s_id']) {
                if ($OJ_TEMPLATE == 'syzoj') {
                    // syzoj: plain result badge (no redundant similarity asterisk) plus a muted second line.
                    $view_status[$i][5].= "<span class='".$judge_color[$row['result']]."'  title='$MSG_Tips'>".$judge_result[$row['result']]."";
                    if ($row['result']!=4&&isset($row['pass_rate'])&&$row['pass_rate']>0&&$row['pass_rate']<.98)
                        $view_status[$i][5].= (100-$row['pass_rate']*100)."%</span>";
                    else
                        $view_status[$i][5].="</span>";

                    $oj_similarity_label = "相似度 ".intval($row['sim'])."%";
                    if(isset($_SESSION[$OJ_NAME.'_'.'source_browser'])){
                        $oj_similarity_title = "查看代码相似度详情（原提交 #".intval($row['sim_s_id'])."）";
                        $view_status[$i][5].= "<a href='comparesource.php?left=".intval($row['sim_s_id'])."&right=".intval($row['solution_id'])."' class='oj-status-similarity' target=original title='".htmlspecialchars($oj_similarity_title, ENT_QUOTES, 'UTF-8')."'>".$oj_similarity_label."</a>";
                    }else{
                        $view_status[$i][5].= "<span class='oj-status-similarity'>".$oj_similarity_label."</span>";
                    }
                    if(isset($_GET['showsim'])&&isset($row['sim_s_id'])){
                        $view_status[$i][5].= "<span sid='".intval($row['sim_s_id'])."' class='original'></span>";
                    }
                }else{
                    // Legacy templates keep the original asterisk + btn-info markup unchanged.
                    $view_status[$i][5].= "<span class='".$judge_color[$row['result']]."'  title='$MSG_Tips'>*".$judge_result[$row['result']]."";
                    if ($row['result']!=4&&isset($row['pass_rate'])&&$row['pass_rate']>0&&$row['pass_rate']<.98)
                        $view_status[$i][5].= (100-$row['pass_rate']*100)."%</span>";
                    else
                        $view_status[$i][5].="</span>";

                    if( isset($_SESSION[$OJ_NAME.'_'.'source_browser'])){

                        $view_status[$i][5].= "<a href=comparesource.php?left=".$row['sim_s_id']."&right=".$row['solution_id']."  class='btn-info'  target=original>".$row['sim_s_id']."(".$row['sim']."%)</a>";
                    }else{

                        $view_status[$i][5].= "<span class='btn-info'>(".$row['sim']."%)</span>";

                    }
                    if(isset($_GET['showsim'])&&isset($row['sim_s_id'])){
                        $view_status[$i][5].= "<span sid='".$row['sim_s_id']."' class='original'></span>";

                    }
                }
            }else{

                $view_status[$i][5].= "<span class='".(isset($judge_color[$row['result']])?$judge_color[$row['result']]:"btn btn-secondary")."'  title='$MSG_Tips'>".(isset($judge_result[$row['result']])?$judge_result[$row['result']]:"结果".$row['result'])."";
                if ($row['result']!=4&&isset($row['pass_rate'])&&$row['pass_rate']>0&&$row['pass_rate']<.98)
                    $view_status[$i][5].= (100-$row['pass_rate']*100)."%</span>";
                else
                    $view_status[$i][5].="</span>";
            }
            /* OJ_STATUS_SIMILARITY_END */
        }else{
            $view_status[$i][5]="----";
        }


    }
    if(isset($_SESSION[$OJ_NAME.'_'.'http_judge'])) {
        $view_status[$i][5].="<form class='http_judge_form form-inline' >
					<input type=hidden name=sid value='".$row['solution_id']."'>";
        $view_status[$i][5].="</form>";
    }




    if ($flag){


        if ($row['result']>=4){
            $view_status[$i][6]= "<div id=center class=red>".$row['memory']."</div>";
            $view_status[$i][7]= "<div id=center class=red>".$row['time']."</div>";
            //echo "=========".$row['memory']."========";
        }else{
            $view_status[$i][6]= "---";
            $view_status[$i][7]= "---";

        }
        //echo $row['result'];
        if (!(isset($_SESSION[$OJ_NAME.'_'.'user_id'])&&strtolower($row['user_id'])==strtolower($_SESSION[$OJ_NAME.'_'.'user_id']) || isset($_SESSION[$OJ_NAME.'_'.'source_browser']))){
            $view_status[$i][8]=$language_name[$row['language']];
        }else{

            $view_status[$i][8]= "<a target=_blank href=showsource.php?id=".$row['solution_id'].">".$language_name[$row['language']]."</a>";
            if($row["problem_id"]>0){
                if ($row['contest_id']>0) {
                    $view_status[$i][8].= "/<a target=_self href=\"submitpage.php?cid=".$row['contest_id']."&pid=".$row['num']."&sid=".$row['solution_id']."\">Edit</a>";
                }else{
                    $view_status[$i][8].= "/<a target=_self href=\"submitpage.php?id=".$row['problem_id']."&sid=".$row['solution_id']."\">Edit</a>";
                }
            }
        }
        $view_status[$i][9]= $row['code_length']." B";

    }else
    {
        $view_status[$i][6]="----";
        $view_status[$i][7]="----";
        $view_status[$i][8]="----";
        $view_status[$i][9]="----";
    }
    $view_status[$i][10]= $row['in_date'];
    if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])) {
        $view_status[$i][11]= htmlentities($row['judger'], ENT_QUOTES, "UTF-8");
    }

//    $view_status[$i][2]= $row['nick'];
    if ($row['result'] == 20) {
        $view_status[$i][5] = "<span class='".$judge_color[0]."'  title='$MSG_Tips'>".$judge_result[0]."</span>";
    } else if ($row['result'] == 21) {
        $view_status[$i][5] = "<span class='".$judge_color[3]."'  title='$MSG_Tips'>".$judge_result[3]."</span>";
    } else if ($row['result'] == 22) {
        $view_status[$i][5] = "<span class='".$judge_color[3]."'  title='你之前提交过相同的代码'>"."提交过相同代码"."</span>";
    }

//    if(strpos($row['judger'],"acwing") !== false){
//        $view_status[$i][6]="----";
//        $view_status[$i][7]="----";
//
//    }
}

?>

<?php
/////////////////////////Template
if (isset($_GET['cid']))
    require("template/".$OJ_TEMPLATE."/conteststatus.php");
else
    require("template/".$OJ_TEMPLATE."/status.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
    require_once('./include/cache_end.php');
?>

