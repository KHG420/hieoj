 <?php
if(isset($_POST['keyword']))
  $cache_time = 1;
else
  $cache_time = 30;

$OJ_CACHE_SHARE = false;//!(isset($_GET['cid'])||isset($_GET['my']));
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/memcache.php');
require_once('./include/my_func.inc.php');
require_once('./include/const.inc.php');
require_once('./include/setlang.php');
$view_title= $MSG_CONTEST;

function formatTimeLength($length)
{
  $hour = 0;
  $minute = 0;
  $second = 0;
  $result = '';

  if($length >= 60){
    $second = $length%60;
    if($second > 0){ $result = $second.'秒';}
    $length = floor($length/60);
    if($length >= 60){
      $minute = $length%60;
      if($minute == 0){ if($result != ''){ $result = '0分' . $result;}}
      else{ $result = $minute.'分'.$result;}
      $length = floor($length/60);
      if($length >= 24){
      	$hour = $length%24;
        if($hour == 0){ if($result != ''){ $result = '0小时' . $result;}}
        else{ $result = $hour . '小时' . $result;}
        $length = floor($length / 24);
        $result = $length . '天' . $result;
      }
      else{ $result = $length . '小时' . $result;}
    }
    else{ $result = $length . '分' . $result;}
  }
  else{ $result = $length . '秒';
  }
  return $result;
}

if(isset($_GET['cid'])){

  header("Cache-Control: no-cache, must-revalidate");
  header("Pragma: no-cache");

  $cid = intval($_GET['cid']);
  $view_cid = $cid;
  //print $cid;

  //check contest valid
  $sql = "SELECT * FROM `contest` WHERE `contest_id`=?";
  $result = pdo_query($sql,$cid);

  $rows_cnt = count($result);
  $contest_ok = true;
  $password = "";

  if(isset($_POST['password'])) $password = $_POST['password'];
  if((function_exists('get_magic_quotes_gpc')&&get_magic_quotes_gpc())){ $password = stripslashes($password);}

  if($rows_cnt==0){
    http_response_code(404);
    $view_errors = "比赛不存在。";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit;
  }else{
    $row = $result[0];
    $view_private = $row['private'];

    if($password!=""&&hash_equals((string)$row['password'],$password)) $_SESSION[$OJ_NAME.'_'.'c'.$cid] = true;
    if($row['private'] && !isset($_SESSION[$OJ_NAME.'_'.'c'.$cid])) $contest_ok = false;
    if($row['defunct']=='Y') $contest_ok = false;
    if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])) $contest_ok = true;

    $now = time();
    $start_time = strtotime($row['start_time']);
    $end_time = strtotime($row['end_time']);
    $view_description = $row['description'];
    $view_title = $row['title'];
    $view_start_time = $row['start_time'];
    $view_end_time = $row['end_time'];
    $IPprivate = $row['IPprivate'];

    $ip = $_SERVER['REMOTE_ADDR'];
//    echo $ip;
//    echo $IPprivate;

//    header("Content-Type: text/html");
    if ($ip == '172.31.0.156' && $IPprivate == 1 && !(isset($_SESSION[$OJ_NAME.'_'.'administrator']))) {
      $neiIP = "http://172.31.0.96/contest.php";
      $view_errors =  '<h2>当前比赛请使用内网并访问：<a href="https://game.ilotus.top/HNIEOJ" target="_blank">内网链接</a></h2>';
      require("template/".$OJ_TEMPLATE."/contestError.php");
      exit(0);
    } else if ($ip != '172.31.0.156' && $IPprivate == 2 && !(isset($_SESSION[$OJ_NAME.'_'.'administrator']))) {
      $view_errors =  "<h2>当前比赛请使用外网并访问</h2>";
      require("template/".$OJ_TEMPLATE."/contestError.php");
      exit(0);
    } else if ($IPprivate == 3 && !(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])) && $now<=$end_time)  {

      if (!(isset($_SESSION[$OJ_NAME.'_'.'user_id']))) {
        require("loginpage.php");
        exit(0);
      }

      $t = "";  // 生成一个随机数加在后面 要清除浏览器缓存
      for ($i = 0; $i < 10; $i ++)
        $t = $t.strval(rand(0, 9));

      ?>
      <script id='yiyiya' src='http://oj.yiyiya.cc/js/get.js?t=<?php echo $t?>'></script>
      <script>
        if (typeof a === 'undefined') {
          console.log('yes');
        } else {
          alert('请在实验室机房断网访问');
          window.location.href='http://1.1.1.1';
        }
      </script>
      <script>
        function yi(){
          $.ajax({
            // url: "http://oj.yiyiya.cc/js/get.js?" + Math.random(),
              url: "https://baidu.com?t=" + Math.random(),
            dataType: "script",
            success: function() {
              $.ajax({
                url: "http://172.31.0.96/getOnline.php",
                data:{
                  userid: "<?php echo isset($_SESSION[$OJ_NAME.'_'.'user_id']) ? $_SESSION[$OJ_NAME.'_'.'user_id'] : "NoLogin" ?>",
                  cid: <?php echo $cid?>,
                  pid: "None",
                  ip: "<?php echo $ip?>"
                },
                dataType: "script",
                success: function(msg) {
                    $.ajax({
                        url: "http://1.1.1.1:8000/userout.magi",
                        success: function() {
                        }
                    });
                    alert('注意！！！你连接了网络，已经被记录' + msg + '次，已为你断开网络。');
                }
              });
            }
          });
        }
        window.setInterval("yi()",10000);
      </script>
      <?php
    }

    if( !(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])) && $now<$start_time){
      $view_errors =  "<h2>$MSG_PRIVATE_WARNING</h2>";
      require("template/".$OJ_TEMPLATE."/contestError.php");
      exit(0);
    }
  }

  if(!$contest_ok){
    $view_errors =  "<h2>$MSG_PRIVATE_WARNING <br><a href=contestrank.php?cid=$cid>$MSG_WATCH_RANK</a></h2>";
    if(!isset($_SESSION[$OJ_NAME.'_'.'postkey'])){
        if(function_exists('random_bytes')) $k=bin2hex(random_bytes(16));
        elseif(function_exists('openssl_random_pseudo_bytes')) $k=bin2hex(openssl_random_pseudo_bytes(16));
        else $k=bin2hex(mt_rand()).bin2hex(uniqid('',true));
        $_SESSION[$OJ_NAME.'_'.'postkey']=$k;
    }
    $view_errors .=  "<form method=post action='contest.php?cid=$cid'>$MSG_CONTEST $MSG_PASSWORD:<input class=input-mini type=password name=password><input class=btn type=submit>";
    $view_errors .= "<input type=hidden name=postkey value='".htmlentities($_SESSION[$OJ_NAME.'_'.'postkey'],ENT_QUOTES,'UTF-8')."'></form>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
  }

  $sql = "SELECT * FROM (SELECT `problem`.`title` AS `title`,`problem`.`problem_id` AS `pid`,source AS source, contest_problem.num as pnum FROM `contest_problem`,`problem` WHERE `contest_problem`.`problem_id`=`problem`.`problem_id` AND `contest_problem`.`contest_id`=? ORDER BY `contest_problem`.`num`) problem LEFT JOIN (SELECT problem_id pid1,count(distinct(user_id)) accepted FROM solution WHERE result=4 AND contest_id=? GROUP BY pid1) p1 ON problem.pid=p1.pid1 LEFT JOIN (SELECT problem_id pid2,count(1) submit FROM solution WHERE contest_id=? GROUP BY pid2) p2 ON problem.pid=p2.pid2 ORDER BY pnum";//AND `problem`.`defunct`='N'

  $result = pdo_query($sql,$cid,$cid,$cid);
  $view_problemset = Array();

  $cnt = 0;

  // 性能优化：预取当前用户在本题集的 AC/提交状态，替代每行 check_ac() 的两条查询
  $my_ac=array(); $my_sub=array();
  if(isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
      $res_my=pdo_query("SELECT num, result FROM solution WHERE contest_id=? AND user_id=? AND problem_id>0", $cid, $_SESSION[$OJ_NAME.'_'.'user_id']);
      foreach($res_my as $r){
          $n=intval($r['num']);
          if(intval($r['result'])==4) $my_ac[$n]=true;
          else $my_sub[$n]=true;
      }
  }

  foreach($result as $row){
    $view_problemset[$cnt][0] = "";
    if(isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
        if(isset($my_ac[$cnt])) $view_problemset[$cnt][0] = "<font color=green>Y</font>";
        else if(isset($my_sub[$cnt])) $view_problemset[$cnt][0] = "<font color=red>N</font>";
    }

    $view_problemset[$cnt][1] = $row['pid']." Problem &nbsp;".$PID[$cnt];
    $view_problemset[$cnt][2] = "<a href='problem.php?cid=$cid&pid=$cnt'>".$row['title']."</a>";
    $view_problemset[$cnt][3] = $row['source'];
    $view_problemset[$cnt][4] = $row['accepted'];
    $view_problemset[$cnt][5] = $row['submit'] ;
    $cnt++;
  }
}else{
  $page = max(1, intval($_GET['page'] ?? 1));
  $page_cnt = 10;
  $keyword = trim($_GET['keyword'] ?? $_POST['keyword'] ?? '');
  $mycontests = array();
  $prefix = $OJ_NAME.'_';
  foreach ($_SESSION as $key => $value) {
    if (strpos($key, $prefix) !== 0) continue;
    $right = substr($key, strlen($prefix));
    if (preg_match('/^[mc]([1-9][0-9]*)$/', $right, $match)) {
      $mycontests[] = intval($match[1]);
    }
  }
  $where = "contest.defunct='N'";
  $params = array();
  if ($keyword !== '') {
    $where .= " AND contest.title LIKE ?";
    $params[] = "%".$keyword."%";
  }
  if (isset($_GET['my'])) {
    $where .= $mycontests ? " AND contest_id IN (".implode(',', $mycontests).")" : " AND 1=0";
  }
  $rows = pdo_query("SELECT COUNT(*) FROM contest WHERE $where", ...$params);
  $total = intval($rows[0][0]);
  $view_total_page = max(1, intval(ceil($total/$page_cnt)));
  $page = min($page, $view_total_page);
  $pstart = ($page-1)*$page_cnt;
  // One creator cell per contest, even if it has multiple management grants.
  $sql = "SELECT contest.*, p.user_id FROM contest LEFT JOIN
    (SELECT rightstr, MIN(user_id) user_id FROM privilege WHERE rightstr LIKE 'm%' GROUP BY rightstr) p
    ON CONCAT('m', contest_id)=p.rightstr WHERE $where ORDER BY contest_id DESC LIMIT $pstart,$page_cnt";
  $result = pdo_query($sql, ...$params);
  $pagination_query = '';
  if ($keyword !== '') $pagination_query .= '&keyword='.urlencode($keyword);
  if (isset($_GET['my'])) $pagination_query .= '&my=1';

  $view_contest = Array();
  $i = 0;

  foreach($result as $row){
    $view_contest[$i][0] = $row['contest_id'];
    $view_contest[$i][1] = "<a href='contest.php?cid=".$row['contest_id']."'>".$row['title']."</a>";
    $start_time = strtotime($row['start_time']);
    $end_time = strtotime($row['end_time']);
    $now = time();

    $length = $end_time-$start_time;
    $left = $end_time-$now;
	//past

    if($now>$end_time){
      $view_contest[$i][2] = "<span class=green>$MSG_Ended@".$row['end_time']."</span>";
      //pending

    }else if ($now<$start_time){
  	  $view_contest[$i][2] = "<span class=blue>$MSG_Start@".$row['start_time']."</span>&nbsp;";
      $view_contest[$i][2] .= "<span class=green>$MSG_TotalTime".formatTimeLength($length)."</span>";
	  //running
    }else{
  	  $view_contest[$i][2] = "<span class=red> $MSG_Running</span>&nbsp;";
      $view_contest[$i][2] .= "<span class=green> $MSG_LeftTime ".formatTimeLength($left)." </span>";
    }

    $private = intval($row['private']);
    if($private==0) $view_contest[$i][4] = "<span class=blue>$MSG_Public</span>";
    else $view_contest[$i][5] = "<span class=red>$MSG_Private</span>";

    $view_contest[$i][6]= "<a href='userinfo.php?user=".$row['user_id']."'>".$row['user_id']."</a>";

    $i++;
  }
}

/////////////////////////Template
if(isset($_GET['cid'])) require("template/".$OJ_TEMPLATE."/contest.php");
else require("template/".$OJ_TEMPLATE."/contestset_mv.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php')) require_once('./include/cache_end.php');
?>
