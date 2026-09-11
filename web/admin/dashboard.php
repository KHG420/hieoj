<?php
require("admin-header.php");
if (!isset($_SESSION[$OJ_NAME.'_'.'administrator'])) {
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
require_once("../include/memcache.php");
require_once("../include/const.inc.php");

// 全部统计走查询缓存（内存/文件缓存层），避免每次刷新打库
function dash($key, $ttl, $fn) {
    $v = cache_get($key);
    if ($v === false) {
        $v = $fn();
        cache_set($key, $v, $ttl);
    }
    return $v;
}

// 近 30 天提交趋势（每日 全部/AC）
$trend = dash("admindash_trend30", 60, function () {
    $days = array();
    $all = array(); $ac = array();
    for ($i = 29; $i >= 0; $i--) {
        $ts = strtotime(date("Y-m-d", strtotime("-" . $i . " day")));
        $days[$ts] = array("all" => 0, "ac" => 0);
    }
    $sql = "SELECT UNIX_TIMESTAMP(date(in_date)) md, count(1) c, SUM(result=4) ac FROM solution WHERE in_date>NOW()-INTERVAL 30 DAY GROUP BY md";
    $r = pdo_query($sql);
    if ($r) foreach ($r as $row) {
        $k = intval($row['md']);
        if (isset($days[$k])) $days[$k] = array("all" => intval($row['c']), "ac" => intval($row['ac']));
    }
    $labels = array(); $a = array(); $b = array();
    foreach ($days as $ts => $v) {
        $labels[] = date("m-d", $ts);
        $a[] = $v['all']; $b[] = $v['ac'];
    }
    return array("labels" => $labels, "all" => $a, "ac" => $b);
});

// 近 12 个月提交量
$monthly = dash("admindash_month12", 600, function () {
    $sql = "SELECT DATE_FORMAT(in_date,'%Y-%m') m, count(1) c FROM solution WHERE in_date>NOW()-INTERVAL 12 MONTH GROUP BY m ORDER BY m";
    $r = pdo_query($sql);
    $labels = array(); $data = array();
    if ($r) foreach ($r as $row) { $labels[] = $row['m']; $data[] = intval($row['c']); }
    return array("labels" => $labels, "data" => $data);
});

// 结果分布（只统计已判定的）
$results = dash("admindash_results", 600, function () {
    global $judge_result;
    $sql = "SELECT result, count(1) c FROM solution WHERE result>=4 GROUP BY result";
    $r = pdo_query($sql);
    $labels = array(); $data = array();
    if ($r) foreach ($r as $row) {
        $res = intval($row['result']);
        $labels[] = isset($judge_result[$res]) ? $judge_result[$res] : ("结果" . $res);
        $data[] = intval($row['c']);
    }
    return array("labels" => $labels, "data" => $data);
});

// 语言分布（const.inc.php 提供 $language_name 映射）
$langs = dash("admindash_langs", 3600, function () {
    global $language_name;
    $sql = "SELECT language, count(1) c FROM solution GROUP BY language ORDER BY c DESC";
    $r = pdo_query($sql);
    $labels = array(); $data = array();
    if ($r) foreach ($r as $row) {
        $li = intval($row['language']);
        $labels[] = isset($language_name[$li]) ? $language_name[$li] : ("语言" . $li);
        $data[] = intval($row['c']);
    }
    return array("labels" => $labels, "data" => $data);
});

// 近 30 天活跃用户
$active = dash("admindash_active30", 300, function () {
    $sql = "SELECT DATE(in_date) d, count(distinct user_id) c FROM solution WHERE in_date>NOW()-INTERVAL 30 DAY GROUP BY d";
    $r = pdo_query($sql);
    $map = array();
    if ($r) foreach ($r as $row) $map[$row['d']] = intval($row['c']);
    $labels = array(); $data = array();
    for ($i = 29; $i >= 0; $i--) {
        $d = date("Y-m-d", strtotime("-" . $i . " day"));
        $labels[] = date("m-d", strtotime($d));
        $data[] = isset($map[$d]) ? $map[$d] : 0;
    }
    return array("labels" => $labels, "data" => $data);
});

// 总览卡片
$stats = dash("admindash_stats", 300, function () {
    $s = array();
    $r = pdo_query("SELECT count(1) FROM users");
    $s['users'] = $r ? intval($r[0][0]) : 0;
    $r = pdo_query("SELECT count(1) FROM problem WHERE defunct='N'");
    $s['problems'] = $r ? intval($r[0][0]) : 0;
    $r = pdo_query("SELECT table_rows FROM information_schema.tables WHERE table_schema='jol' AND table_name='solution'");
    $s['submits'] = $r ? intval($r[0][0]) : 0;
    $r = pdo_query("SELECT count(1) FROM solution WHERE result=4");
    $s['ac'] = $r ? intval($r[0][0]) : 0;
    $r = pdo_query("SELECT count(1) FROM contest WHERE defunct='N'");
    $s['contests'] = $r ? intval($r[0][0]) : 0;
    $r = pdo_query("SELECT count(1) FROM reply WHERE status<=1");
    $s['replies'] = $r ? intval($r[0][0]) : 0;
    return $s;
});

// 判题负载（文本指标 + 24 小时逐小时判题量图表）
$judge = dash("admindash_judge", 60, function () {
    $j = array();
    $r = pdo_query("SELECT count(1) FROM solution WHERE result<4");
    $j['queue'] = $r ? intval($r[0][0]) : 0;
    $r = pdo_query("SELECT AVG(TIMESTAMPDIFF(SECOND,in_date,judgetime)) FROM solution WHERE result>=4 AND judgetime>NOW()-INTERVAL 1 DAY");
    $j['avg'] = ($r && $r[0][0] !== null) ? round(floatval($r[0][0]), 1) : 0;
    $r = pdo_query("SELECT count(1) FROM solution WHERE result>=4 AND judgetime>NOW()-INTERVAL 1 DAY");
    $j['today'] = $r ? intval($r[0][0]) : 0;
    return $j;
});
$judgeHour = dash("admindash_judgehour", 60, function () {
    $sql = "SELECT HOUR(judgetime) h, count(1) c FROM solution WHERE result>=4 AND judgetime>NOW()-INTERVAL 1 DAY GROUP BY h";
    $r = pdo_query($sql);
    $map = array();
    if ($r) foreach ($r as $row) $map[intval($row['h'])] = intval($row['c']);
    $labels = array(); $data = array();
    for ($i = 0; $i < 24; $i++) {
        $labels[] = $i . "时";
        $data[] = isset($map[$i]) ? $map[$i] : 0;
    }
    return array("labels" => $labels, "data" => $data);
});

// Top10 活跃用户（近30天，含姓名，可点击）
$topusers = dash("admindash_topusers", 600, function () {
    $sql = "SELECT s.user_id, u.nick, count(1) c, SUM(s.result=4) ac FROM solution s JOIN users u ON u.user_id=s.user_id WHERE s.in_date>NOW()-INTERVAL 30 DAY GROUP BY s.user_id, u.nick ORDER BY c DESC LIMIT 10";
    $r = pdo_query($sql);
    return $r ? $r : array();
});
?>
<title>数据驾驶舱 - <?php echo $OJ_NAME?></title>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
<?php include("navbar.php");?>
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
<style>
.dash h1{font-size:20px;margin:0 0 16px;color:#1f2d3d;}
.dash .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px;}
.dash .card{background:#fff;border:1px solid #e6ebf2;border-radius:10px;box-shadow:0 4px 14px rgba(15,23,42,.06);padding:16px;}
.dash .card .num{font-size:26px;font-weight:700;color:#2f6ee5;}
.dash .card .lbl{font-size:13px;color:#5f6d85;margin-top:4px;}
.dash .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.dash .panel{background:#fff;border:1px solid #e6ebf2;border-radius:10px;box-shadow:0 4px 14px rgba(15,23,42,.06);padding:16px;margin-bottom:14px;}
.dash .panel h3{font-size:15px;margin:0 0 12px;color:#1f2d3d;}
.dash .chart{position:relative;height:230px;}
.dash table{width:100%;border-collapse:collapse;font-size:13px;}
.dash th{background:#f8fafc;color:#495057;text-align:left;padding:8px 10px;border-bottom:2px solid #dee2e6;}
.dash td{padding:8px 10px;border-bottom:1px solid #e9ecef;}
.dash tr:hover td{background:#f6f9ff;}
.dash a.uid{color:#2f6ee5;font-weight:600;}
</style>
<div class="dash">
<h1>数据驾驶舱</h1>
<div class="cards">
  <div class="card"><div class="num"><?php echo number_format($stats['users'])?></div><div class="lbl">注册用户</div></div>
  <div class="card"><div class="num"><?php echo number_format($stats['problems'])?></div><div class="lbl">公开题目</div></div>
  <div class="card"><div class="num"><?php echo number_format($stats['submits'])?></div><div class="lbl">总提交</div></div>
  <div class="card"><div class="num"><?php echo number_format($stats['ac'])?></div><div class="lbl">AC 总数</div></div>
  <div class="card"><div class="num"><?php echo number_format($stats['contests'])?></div><div class="lbl">比赛/作业</div></div>
  <div class="card"><div class="num"><?php echo number_format($stats['replies'])?></div><div class="lbl">讨论回复</div></div>
</div>

<div class="panel">
  <h3>判题负载 &nbsp;<span style="font-size:13px;color:#5f6d85;font-weight:400;">待判队列：<b style="color:#f0a020"><?php echo $judge['queue']?></b> &nbsp;|&nbsp; 24h 判题数：<?php echo $judge['today']?> &nbsp;|&nbsp; 24h 平均判题耗时：<b><?php echo $judge['avg']?>s</b></span></h3>
  <div class="chart"><canvas id="c-judge"></canvas></div>
</div>

<div class="grid2">
  <div class="panel"><h3>近 30 天提交趋势</h3><div class="chart"><canvas id="c-trend"></canvas></div></div>
  <div class="panel"><h3>近 12 个月提交量</h3><div class="chart"><canvas id="c-month"></canvas></div></div>
  <div class="panel"><h3>判定结果分布</h3><div class="chart"><canvas id="c-result"></canvas></div></div>
  <div class="panel"><h3>提交语言分布</h3><div class="chart"><canvas id="c-lang"></canvas></div></div>
  <div class="panel"><h3>近 30 天活跃用户</h3><div class="chart"><canvas id="c-active"></canvas></div></div>
  <div class="panel"><h3>近 30 天最活跃用户 Top10</h3>
    <table>
      <tr><th>#</th><th>学号</th><th>姓名</th><th>提交</th><th>AC</th></tr>
      <?php $i=1; foreach($topusers as $u){ ?>
      <tr>
        <td><?php echo $i++?></td>
        <td><a class="uid" href="../userinfo.php?user=<?php echo urlencode($u['user_id'])?>" target="_blank"><?php echo htmlentities($u['user_id'],ENT_QUOTES,"UTF-8")?></a></td>
        <td><?php echo htmlentities(isset($u['nick'])?$u['nick']:'',ENT_QUOTES,"UTF-8")?></td>
        <td><?php echo $u['c']?></td>
        <td><?php echo $u['ac']?></td>
      </tr>
      <?php } ?>
    </table>
  </div>
</div>
</div>
        </div>
    </section>
</div>
</div>
<script src="../template/syzoj/css/Chart.min.js"></script>
<script>
var BLUE='#2f6ee5', GREEN='#18a058', AMBER='#f0a020', RED='#d03050', GRID='#e6ebf2';
var PALETTE=['#2f6ee5','#18a058','#f0a020','#d03050','#8b5cf6','#0ea5e9','#f59e0b','#14b8a6','#6366f1','#ef4444'];
var trend = <?php echo json_encode($trend)?>;
var monthly = <?php echo json_encode($monthly)?>;
var results = <?php echo json_encode($results)?>;
var langs = <?php echo json_encode($langs)?>;
var active = <?php echo json_encode($active)?>;
var judgeHour = <?php echo json_encode($judgeHour)?>;
function base(){ return { yAxes:[{ticks:{beginAtZero:true,precision:0},gridLines:{color:GRID}}], xAxes:[{gridLines:{display:false},ticks:{maxTicksLimit:15}}] }; }
new Chart(document.getElementById('c-judge'), { type:'bar', data:{labels:judgeHour.labels,datasets:[{label:'判题数',data:judgeHour.data,backgroundColor:AMBER}]}, options:{responsive:true,maintainAspectRatio:false,legend:{display:false},title:{display:true,text:'近 24 小时逐小时判题量'},scales:base()} });
new Chart(document.getElementById('c-trend'), { data:{ labels:trend.labels, datasets:[
  {label:'提交',type:'line',data:trend.all,borderColor:BLUE,backgroundColor:'rgba(47,110,229,.12)',borderWidth:2,fill:true,tension:.3,pointRadius:2,order:1},
  {label:'AC',data:trend.ac,backgroundColor:GREEN,borderRadius:3,order:2}
]}, type:'bar', options:{responsive:true,maintainAspectRatio:false,legend:{position:'top',labels:{usePointStyle:true,boxWidth:8,fontSize:12}},scales:base()} });
new Chart(document.getElementById('c-month'), { type:'bar', data:{labels:monthly.labels,datasets:[{label:'提交',data:monthly.data,backgroundColor:BLUE}]}, options:{responsive:true,maintainAspectRatio:false,legend:{display:false},scales:base()} });
new Chart(document.getElementById('c-result'), { type:'doughnut', data:{labels:results.labels,datasets:[{data:results.data,backgroundColor:PALETTE,borderWidth:1}]}, options:{responsive:true,maintainAspectRatio:false,legend:{position:'right',labels:{usePointStyle:true,boxWidth:8,fontSize:11}}} });
new Chart(document.getElementById('c-lang'), { type:'bar', data:{labels:langs.labels,datasets:[{label:'提交',data:langs.data,backgroundColor:PALETTE}]}, options:{responsive:true,maintainAspectRatio:false,legend:{display:false},scales:base()} });
new Chart(document.getElementById('c-active'), { type:'line', data:{labels:active.labels,datasets:[{label:'活跃用户',data:active.data,borderColor:GREEN,backgroundColor:'rgba(24,160,88,.12)',borderWidth:2,fill:true,tension:.3,pointRadius:2}]}, options:{responsive:true,maintainAspectRatio:false,legend:{display:false},scales:base()} });
// 大屏模式：60 秒自动刷新
setTimeout(function(){ location.reload(); }, 60000);
</script>
</body>
</html>
