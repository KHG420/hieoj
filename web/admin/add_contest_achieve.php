<?php require_once("admin-header.php");?>
<?php if(!(isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'contest_creator']))){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
if(isset($_POST['addAchieve'])){
    //echo $_POST['user_id'];
    require_once("../include/check_post_key.php");
    //echo $_POST['passwd'];
    require_once("../include/my_func.inc.php");
    require_once("../include/db_info.inc.php");

    $contest_id = $_POST['contest_id'];
    $from = $_POST['from'];
    $to = $_POST['to'];
    if ((function_exists('get_magic_quotes_gpc')&&get_magic_quotes_gpc())) {
        $contest_id = stripslashes ( $contest_id);
        $from = stripslashes ( $from);
        $to = stripslashes ( $to);
    }

    class TM{
        var $solved=0;
        var $time=0;
        var $p_wa_num;
        var $p_ac_sec;
        var $user_id;
        var $nick;
        function __construct(){
            $this->solved=0;
            $this->time=0;
            $this->p_wa_num=array(0);
            $this->p_ac_sec=array(0);
        }
        function Add($pid,$sec,$res){
            global $OJ_CE_PENALTY;
//              echo "Add $pid $sec $res<br>";
            if (isset($this->p_ac_sec[$pid])&&$this->p_ac_sec[$pid]>0)
                return;
            if ($res!=4){
                if(isset($OJ_CE_PENALTY)&&!$OJ_CE_PENALTY&&$res==11) return;  // ACM WF punish no ce

                if(isset($this->p_wa_num[$pid])){
                    $this->p_wa_num[$pid]++;
                }else{
                    $this->p_wa_num[$pid]=1;
                }
            }else{
                $this->p_ac_sec[$pid]=$sec;
                $this->solved++;
                if(!isset($this->p_wa_num[$pid])) $this->p_wa_num[$pid]=0;
                $this->time+=$sec+$this->p_wa_num[$pid]*1200;
//                      echo "Time:".$this->time."<br>";
//                      echo "Solved:".$this->solved."<br>";
            }
        }
    }

    function s_cmp($A,$B){
//      echo "Cmp....<br>";
        if ($A->solved!=$B->solved) return $A->solved<$B->solved;
        else return $A->time>$B->time;
    }

    $cid=$contest_id;

    if($OJ_MEMCACHE){
        $sql="SELECT `start_time`,`title`,`end_time` FROM `contest` WHERE `contest_id`=$cid";
        require("./include/memcache.php");
        $result = mysql_query_cache($sql);
        if($result) $rows_cnt=count($result);
        else $rows_cnt=0;
    }else{
        $sql="SELECT `start_time`,`title`,`end_time` FROM `contest` WHERE `contest_id`=?";
        $result = pdo_query($sql,$cid);
        if($result) $rows_cnt=count($result);
        else $rows_cnt=0;
    }


    $start_time=0;
    $end_time=0;
    if ($rows_cnt>0){
//       $row=$result[0];

        if($OJ_MEMCACHE)
            $row=$result[0];
        else
            $row=$result[0];
        $start_time=strtotime($row['start_time']);
        $end_time=strtotime($row['end_time']);
        $title=$row['title'];

    }
    if(!$OJ_MEMCACHE)
        if ($start_time==0){
            $view_errors= "No Such Contest";
            require("template/".$OJ_TEMPLATE."/error.php");
            exit(0);
        }

    if ($start_time>time()){
        $view_errors= "Contest Not Started!";
        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }
    if(!isset($OJ_RANK_LOCK_PERCENT)) $OJ_RANK_LOCK_PERCENT=0;
    $lock=$end_time-($end_time-$start_time)*$OJ_RANK_LOCK_PERCENT;

//echo $lock.'-'.date("Y-m-d H:i:s",$lock);

    if($OJ_MEMCACHE){
        $sql="SELECT count(1) as pbc FROM `contest_problem` WHERE `contest_id`='$cid'";
//        require("./include/memcache.php");
        $result = mysql_query_cache($sql);
        if($result) $rows_cnt=count($result);
        else $rows_cnt=0;
    }else{
        $sql="SELECT count(1) as pbc FROM `contest_problem` WHERE `contest_id`=?";
        $result = pdo_query($sql,$cid);
        if($result) $rows_cnt=count($result);
        else $rows_cnt=0;
    }

    if($OJ_MEMCACHE)
        $row=$result[0];
    else
        $row=$result[0];

// $row=$result[0];
    $pid_cnt=intval($row['pbc']);

    if($OJ_MEMCACHE){
        $sql="SELECT
        users.user_id,users.school,users.nick,solution.result,solution.num,solution.in_date
                FROM
                        (select * from solution where solution.contest_id='$cid' and num>=0 and problem_id>0) solution
                inner join users
                on users.user_id=solution.user_id and users.defunct='N'
        ORDER BY users.user_id,in_date";
        $result = mysql_query_cache($sql);
        if($result) $rows_cnt=count($result);
        else $rows_cnt=0;
    }else{
        $sql="SELECT
        users.user_id,users.school,users.nick,solution.result,solution.num,solution.in_date
                FROM
                        (select * from solution where solution.contest_id=? and num>=0 and problem_id>0) solution
                inner join users
                on users.user_id=solution.user_id and users.defunct='N'
        ORDER BY users.user_id,in_date";
        $result = pdo_query($sql,$cid);
        if($result) $rows_cnt=count($result);
        else $rows_cnt=0;
    }

    $user_cnt=0;
    $user_name='';
    $U=array();
//$U[$user_cnt]=new TM();
    for ($i=0;$i<$rows_cnt;$i++){
        $row=$result[$i];
        $n_user=$row['user_id'];
        if (strcmp($user_name,$n_user)){
            $user_cnt++;
            $U[$user_cnt]=new TM();

            $U[$user_cnt]->user_id=$row['user_id'];
            $U[$user_cnt]->nick=$row['nick'];
            $U[$user_cnt]->school=$row['school'];
            $user_name=$n_user;
        }
        if(time()<$end_time+3600&&$lock<strtotime($row['in_date']))
            $U[$user_cnt]->Add($row['num'],strtotime($row['in_date'])-$start_time,0);
        else
            $U[$user_cnt]->Add($row['num'],strtotime($row['in_date'])-$start_time,intval($row['result']));

    }
    usort($U,"s_cmp");

////firstblood
    $first_blood=array();
    for($i=0;$i<$pid_cnt;$i++){
        $first_blood[$i]="";
    }


    if($OJ_MEMCACHE){
        $sql="select num,user_id from
        (select num,user_id from solution where contest_id=$cid and result=4 order by solution_id ) contest
        group by num";
        $fb = mysql_query_cache($sql);
        if($fb) $rows_cnt=count($fb);
        else $rows_cnt=0;
    }else{
        $sql="select num,user_id from
        (select num,user_id from solution where contest_id=? and result=4 order by solution_id ) contest
        group by num";
        $fb = pdo_query($sql,$cid);
        if($fb) $rows_cnt=count($fb);
        else $rows_cnt=0;
    }
    for ($i=0;$i<$rows_cnt;$i++){
        $row=$fb[$i];
        $first_blood[$row['num']]=$row['user_id'];
    }

    function numToWord($num)
    {
        $chiNum = array('零', '一', '二', '三', '四', '五', '六', '七', '八', '九');
        $chiUni = array('','十', '百', '千', '万', '亿', '十', '百', '千');

        $chiStr = '';

        $num_str = (string)$num;

        $count = strlen($num_str);
        $last_flag = true; //上一个 是否为0
        $zero_flag = true; //是否第一个
        $temp_num = null; //临时数字

        $chiStr = '';//拼接结果
        if ($count == 2) {//两位数
            $temp_num = $num_str[0];
            $chiStr = $temp_num == 1 ? $chiUni[1] : $chiNum[$temp_num].$chiUni[1];
//当以1开头 都是十一，十二，以十开头的 我们就取$chiUni[i]也就是十当不是以1开头时，而是以2,3,4,我们取这个数字相应的中文并拼接上十
            $temp_num = $num_str[1];
            $chiStr .= $temp_num == 0 ? '' : $chiNum[$temp_num];
//取得第二个值并的到他的中文
        }else if($count > 2){
            $index = 0;
            for ($i=$count-1; $i >= 0 ; $i--) {
                $temp_num = $num_str[$i];         //获取的个位数
                if ($temp_num == 0) {
                    if (!$zero_flag && !$last_flag ) {
                        $chiStr = $chiNum[$temp_num]. $chiStr;
                        $last_flag = true;
                    }
                }else{
                    $chiStr = $chiNum[$temp_num].$chiUni[$index%9] .$chiStr;
//$index%9 index原始值为0，所以开头为0 后面根据循环得到：0,1,2,3...（不知道为什么直接用$index而是选择$index%9  毕竟两者结果是一样的）
//当输入的值为：1003 ，防止输出一千零零三的错误情况，$last_flag就起到作用了当翻译倒数第二个值时，将$last_flag设定为true;翻译第三值时在if(!$zero&&!$last_flag)的判断中会将其拦截，从而跳过
                    $zero_flag = false;
                    $last_flag = false;
                }
                $index ++;
            }
        }else{
            $chiStr = $chiNum[$num_str[0]];    //单个数字的7a64e58685e5aeb931333431336230直接取中文
        }
        return $chiStr;
    }


    $contest_sql = "SELECT start_time, end_time, title FROM contest WHERE contest_id=?";
    $contest_result = pdo_query($contest_sql, $contest_id);

    $content = $contest_result[0]['title'];
    $year = date('Y', strtotime($contest_result[0]['start_time']));
//    echo $year;

    $sql = "insert INTO achieve(`user_id`,`contest_id`,`year`,`content`,`award`,`inspector`,`time`,`status`)
            VALUES(?,?,?,?,?,?,now(),?)";
    for ($i = $from - 1; $i < $to; $i++ ){
        $uuid=$U[$i]->user_id;
        $school=$U[$i]->school;
        $nick=$U[$i]->nick;
        $usolved=$U[$i]->solved;
//        echo $uuid.$nick."<br>";
        $award = "第".numToWord($i+1)."名";
//        echo $award;

        pdo_query($sql, $uuid, $contest_id, $year, $content, $award, "admin", 1);
    }

    echo "<script>alert('添加成功')</script>";

//    echo $nick;
//    echo $user_id;
//    echo $school;
//    $sql="update `users` set `password`=? where `user_id`=?  and user_id not in( select user_id from privilege where rightstr='administrator') ";
//    $contest_sql = "SELECT start_time, end_time FROM contest WHERE contest_id=?";
//    $contest_result = pdo_query($contest_sql, $contest_id);
//
//
//    $qurey_ac_sql = "select user_id, count(distinct problem_id) as result from solution where contest_id=? AND result=4 GROUP BY user_id ORDER BY result desc";
//    $ac_result = pdo_query($qurey_ac_sql, $contest_id);
//
//    $user_time = array();
//    $user_ac_time = array();
//    $qurey_time_sql = "SELECT user_id, in_date, problem_id FROM (SELECT user_id, in_date, problem_id FROM solution WHERE contest_id=? AND result=4 ) as a GROUP BY problem_id, user_id ORDER BY user_id";
//    $time_result = pdo_query($qurey_time_sql, $contest_id);
//    foreach ($time_result as $row) {
//        $user_time[$row['user_id']] += strtotime($row['in_date'] - $contest_result['start_time'], 0);
//        $user_ac_time[$row['user_id']][$row['problem_id']] = $row['in_date'];
////        echo $user_time[$row['user_id']]."\n";
//    }
//
//    $wa_sql = "SELECT user_id, in_date, problem_id FROM solution WHERE contest_id='1035' AND result!=4";
//    $wa_result = pdo_query($wa_sql, $contest_id);
//    foreach ($wa_result as $row) {
//        if ($row['in_date'] < $user_ac_time[$row['user_id']][$row['problem_id']]) {
//            if ($row['user_id'] == "202009140227") echo $row['problem_id']."<br>";
//            $user_time[$row['user_id']] += 1200;
//        }
//    }
//
//    foreach ($ac_result as $row) {
//        $row['time'] = $user_time[$row['user_id']];
//    }
//
//    function s_cmp($A,$B){
//        global $user_time;
////      echo "Cmp....<br>";
//        if ($A['result'] != $B['result']) return $A['result'] < $B['result'];
//        else return $user_time[$A['user_id']] > $user_time[$B['user_id']];
//    }
//
//    usort($ac_result, "s_cmp");
//
//    foreach ($ac_result as $row) {
//        echo $row['user_id']."&nbsp".$row['result']."&nbsp".$user_time[$row['user_id']]."<br>";
//    }

//    if (pdo_query($sql,$passwd,$user_id)==1) echo "Password Changed!";
//    else echo "No such user! or He/Her is an administrator!";
}
?>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include("navbar.php") ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="row">
                <div class="col-sm-1">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <div class="modal-dialog" style="margin-top: 10%;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title text-center">添加成就</h4>
                        </div>
                        <form action='add_contest_achieve.php' method=post>
                            <?php require_once("../include/set_post_key.php");?>
                            <div class="modal-body" id = "model-body">
                                <div class="form-group">
                                    <input type=text size=10 name="contest_id" class="form-control" placeholder="比赛编号" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="from" class="form-control" placeholder="开始名次" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="to" class="form-control" placeholder="结束名次" autocomplete="off">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="form-group">
                                    <input type='hidden' name='addAchieve' value='addAchieve'>
                                    <input type=submit value='添加' class="btn btn-primary form-control">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
    document.getElementById('menu4').classList.remove("menu");
    document.getElementById('menu4').classList.add("menu-open");
    document.getElementById('a20').classList.remove("bg-primary");
    document.getElementById('a20').classList.add("bg-secondary");
</script>
</body>