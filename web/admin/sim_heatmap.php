<?php
require("admin-header.php");
if (!isset($_SESSION[$OJ_NAME.'_'.'administrator'])) {
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
require_once("../include/memcache.php");

$cid = isset($_GET['cid']) ? intval($_GET['cid']) : 0;

function sh($key, $ttl, $fn) {
    $v = cache_get($key);
    if ($v === false) {
        $v = $fn();
        cache_set($key, $v, $ttl);
    }
    return $v;
}

// 比赛下拉列表
$contestList = sh("adminsim_contests", 300, function () {
    $r = pdo_query("SELECT contest_id, title FROM contest ORDER BY contest_id DESC LIMIT 300");
    return $r ? $r : array();
});
$curTitle = "";
foreach ($contestList as $ct) {
    if (intval($ct['contest_id']) === $cid) { $curTitle = $ct['title']; break; }
}

// 比赛限定条件（仅统计两边提交都属于该比赛的相似对）
$cc1 = $cid > 0 ? " AND b.contest_id=$cid" : "";
$cc2 = $cid > 0 ? " AND c.contest_id=$cid" : "";

// 相似度分布（50% 以上）
$dist = sh("adminsim_dist_" . $cid, 600, function () use ($cc1, $cc2) {
    $sql = "SELECT FLOOR(a.sim/10)*10 b, count(1) c FROM sim a JOIN solution b ON a.s_id=b.solution_id JOIN solution c ON a.sim_s_id=c.solution_id WHERE a.sim>=50$cc1$cc2 GROUP BY b ORDER BY b";
    $r = pdo_query($sql);
    $labels = array(); $data = array();
    if ($r) foreach ($r as $row) {
        $b = intval($row['b']);
        $labels[] = $b . "-" . ($b + 9) . "%";
        $data[] = intval($row['c']);
    }
    return array("labels" => $labels, "data" => $data);
});

// 高风险相似对（sim>=60），取 top 300 用于矩阵与列表
$pairs = sh("adminsim_pairs_" . $cid, 300, function () use ($cc1, $cc2) {
    $sql = "SELECT a.s_id, a.sim_s_id, a.sim,
                   b.user_id u1, b.problem_id p1, b.contest_id bc1,
                   c.user_id u2, c.problem_id p2, c.contest_id bc2
            FROM sim a
            JOIN solution b ON a.s_id = b.solution_id
            JOIN solution c ON a.sim_s_id = c.solution_id
            WHERE a.sim >= 60$cc1$cc2
            ORDER BY a.sim DESC LIMIT 300";
    $r = pdo_query($sql);
    return $r ? $r : array();
});

// 题目风险榜（高相似对最多的题目）
$problemRisk = sh("adminsim_problems_" . $cid, 600, function () use ($cid) {
    $cc = $cid > 0 ? " AND b.contest_id=$cid" : "";
    $sql = "SELECT b.problem_id, count(1) c, MAX(a.sim) mx
            FROM sim a JOIN solution b ON a.s_id = b.solution_id
            WHERE a.sim >= 60$cc GROUP BY b.problem_id ORDER BY c DESC LIMIT 15";
    $r = pdo_query($sql);
    return $r ? $r : array();
});

// 用户×用户相似矩阵（高频用户 Top 15）
$userRank = array();
foreach ($pairs as $p) {
    foreach (array($p['u1'], $p['u2']) as $u) {
        if ($u === null || $u === '') continue;
        $k = 'u' . $u; // 避免 PHP 把数字字符串键转成 int
        if (!isset($userRank[$k])) $userRank[$k] = 0;
        $userRank[$k]++;
    }
}
arsort($userRank);
$topUsers = array();
foreach (array_slice(array_keys($userRank), 0, 15) as $k) $topUsers[] = substr($k, 1);

$matrix = array();
foreach ($pairs as $p) {
    $u1 = $p['u1']; $u2 = $p['u2'];
    if ($u1 === null || $u2 === null) continue;
    if (in_array($u1, $topUsers, true) && in_array($u2, $topUsers, true) && $u1 !== $u2) {
        $s = floatval($p['sim']);
        $key = $u1 . "	" . $u2;
        $key2 = $u2 . "	" . $u1;
        if (!isset($matrix[$key]) || $matrix[$key] < $s) $matrix[$key] = $s;
        if (!isset($matrix[$key2]) || $matrix[$key2] < $s) $matrix[$key2] = $s;
    }
}

// 用户风险榜（姓名 + 相似对数 + 最高相似度）
$userRisk = array();
$nickMap = array();
foreach ($pairs as $p) {
    $uu = array($p['u1'], $p['u2']);
    $unk = array('u' . $p['u1'], 'u' . $p['u2']);
    foreach (array(0, 1) as $idx) {
        $u = $uu[$idx];
        if ($u === null || $u === '') continue;
        if (!isset($userRisk[$unk[$idx]])) $userRisk[$unk[$idx]] = array('cnt' => 0, 'mx' => 0);
        $userRisk[$unk[$idx]]['cnt']++;
        $s = floatval($p['sim']);
        if ($s > $userRisk[$unk[$idx]]['mx']) $userRisk[$unk[$idx]]['mx'] = $s;
    }
}
if (count($userRisk) > 0) {
    $urSql = "SELECT user_id, nick FROM users WHERE user_id IN ('" . implode("','", array_map(function ($x) {
        return substr($x, 1);
    }, array_keys($userRisk))) . "')";
    $ur = pdo_query($urSql);
    if ($ur) foreach ($ur as $row) $nickMap[$row['user_id']] = $row['nick'];
}
$riskRows = array();
foreach ($userRisk as $k => $v) {
    $uid = substr($k, 1);
    $riskRows[] = array('uid' => $uid, 'nick' => isset($nickMap[$uid]) ? $nickMap[$uid] : '', 'cnt' => $v['cnt'], 'mx' => $v['mx']);
}
usort($riskRows, function ($a, $b) { return $b['cnt'] - $a['cnt']; });
$riskRows = array_slice($riskRows, 0, 15);
?>
<title>抄袭检测可视化 - <?php echo $OJ_NAME?></title>
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
.sh h1{font-size:20px;margin:0 0 6px;color:#1f2d3d;}
.sh .sub{font-size:13px;color:#5f6d85;margin-bottom:14px;}
.sh .panel{background:#fff;border:1px solid #e6ebf2;border-radius:10px;box-shadow:0 4px 14px rgba(15,23,42,.06);padding:16px;margin-bottom:14px;}
.sh .panel h3{font-size:15px;margin:0 0 12px;color:#1f2d3d;}
.sh .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.sh .chart{position:relative;height:220px;}
.sh table{width:100%;border-collapse:collapse;font-size:13px;}
.sh th{background:#f8fafc;color:#495057;text-align:left;padding:8px 10px;border-bottom:2px solid #dee2e6;}
.sh td{padding:8px 10px;border-bottom:1px solid #e9ecef;}
.sh tr:hover td{background:#f6f9ff;}
.sh a.uid{color:#2f6ee5;font-weight:600;}
.sh .heat{display:inline-grid;grid-template-columns:auto repeat(<?php echo max(1,count($topUsers))?>, 26px);gap:2px;font-size:11px;}
.sh .heat .cell{width:26px;height:26px;border-radius:4px;background:#f1f3f6;color:#1f2d3d;display:flex;align-items:center;justify-content:center;}
.sh .heat .lab{display:flex;align-items:center;padding-right:6px;color:#5f6d85;white-space:nowrap;}
.sh .legend{display:flex;gap:4px;align-items:center;font-size:12px;color:#5f6d85;margin-top:8px;}
.sh .legend .sw{width:18px;height:12px;border-radius:2px;}
.sh .empty{color:#9aa4af;font-size:13px;padding:8px 0;}
</style>
<div class="sh">
<h1>抄袭检测可视化（代码相似度）</h1>
<div class="sub">
  <label>比赛范围：</label>
  <select id="cidsel" onchange="location.href='sim_heatmap.php?cid='+this.value" style="padding:5px 10px;border:1px solid #cfd8e3;border-radius:6px;">
    <option value="0">全部数据</option>
    <?php foreach ($contestList as $ct) { ?>
    <option value="<?php echo intval($ct['contest_id'])?>" <?php if (intval($ct['contest_id']) === $cid) echo "selected"?>><?php echo "#" . intval($ct['contest_id']) . " " . htmlentities($ct['title'], ENT_QUOTES, "UTF-8")?></option>
    <?php } ?>
  </select>
  <?php if ($cid > 0) echo '<span style="margin-left:10px;">当前：<b>' . htmlentities($curTitle, ENT_QUOTES, "UTF-8") . '</b>（仅统计双方提交均属于该比赛）</span>'; ?>
</div>

<div class="grid2">
  <div class="panel"><h3>相似度分布（≥50%）</h3><div class="chart"><canvas id="c-dist"></canvas></div></div>
  <div class="panel"><h3>高风险题目 Top15（≥60% 相似对数量）</h3><div class="chart"><canvas id="c-problem"></canvas></div></div>
</div>

<div class="grid2">
  <div class="panel">
    <h3>高风险用户 Top15</h3>
    <table>
      <tr><th>#</th><th>学号</th><th>姓名</th><th>相似对数</th><th>最高相似度</th></tr>
      <?php if (count($riskRows) == 0) { ?>
      <tr><td colspan="5" class="empty">暂无数据（该范围内没有 ≥60% 的相似对）</td></tr>
      <?php } $i = 1; foreach ($riskRows as $rk) { ?>
      <tr>
        <td><?php echo $i++?></td>
        <td><a class="uid" href="../userinfo.php?user=<?php echo urlencode($rk['uid'])?>" target="_blank"><?php echo htmlentities($rk['uid'], ENT_QUOTES, "UTF-8")?></a></td>
        <td><?php echo htmlentities($rk['nick'], ENT_QUOTES, "UTF-8")?></td>
        <td><?php echo $rk['cnt']?></td>
        <td><b style="color:<?php echo $rk['mx'] >= 80 ? '#d03050' : '#f0a020'?>"><?php echo $rk['mx']?>%</b></td>
      </tr>
      <?php } ?>
    </table>
  </div>
  <div class="panel">
    <h3>用户 × 用户 相似矩阵（Top <?php echo count($topUsers)?> 高频用户，≥60% 取最高值）</h3>
    <?php if (count($topUsers) == 0) { ?>
    <div class="empty">暂无数据（该范围内没有 ≥60% 的相似对）</div>
    <?php } else { ?>
    <div style="overflow-x:auto;">
    <div class="heat">
      <div class="lab"></div>
      <?php foreach ($topUsers as $u) echo '<div class="lab" style="writing-mode:vertical-rl;transform:rotate(180deg);height:auto;">' . htmlentities($u, ENT_QUOTES, "UTF-8") . '</div>'; ?>
      <?php foreach ($topUsers as $u1) {
          echo '<div class="lab">' . htmlentities($u1, ENT_QUOTES, "UTF-8") . '</div>';
          foreach ($topUsers as $u2) {
              $key = $u1 . "	" . $u2;
              if ($u1 === $u2) { echo '<div class="cell">·</div>'; continue; }
              $v = isset($matrix[$key]) ? $matrix[$key] : 0;
              if ($v <= 0) { echo '<div class="cell"></div>'; continue; }
              $t = min(1, ($v - 60) / 40);
              $r = 255; $g = intval(255 - 200 * $t); $b = intval(255 - 230 * $t);
              echo '<div class="cell" style="background:rgb(' . $r . ',' . $g . ',' . $b . ');color:' . ($t > 0.6 ? '#fff' : '#1f2d3d') . '" title="' . htmlentities($u1 . '↔' . $u2 . ' ' . $v . '%', ENT_QUOTES, "UTF-8") . '">' . intval($v) . '</div>';
          }
      } ?>
    </div>
    </div>
    <div class="legend"><span>60%</span><span class="sw" style="background:rgb(255,255,25)"></span><span class="sw" style="background:rgb(255,155,25)"></span><span class="sw" style="background:rgb(255,55,25)"></span><span class="sw" style="background:rgb(255,25,25)"></span><span>100%</span>&nbsp;|&nbsp;颜色越深风险越高</div>
    <?php } ?>
  </div>
</div>

<div class="panel">
  <h3>高相似提交对（≥60%，Top 300 中取前 50）</h3>
  <table>
    <tr><th>#</th><th>用户 A</th><th>题目</th><th>比赛</th><th>用户 B</th><th>题目</th><th>比赛</th><th>相似度</th></tr>
    <?php if (count($pairs) == 0) { ?>
    <tr><td colspan="8" class="empty">暂无数据（该范围内没有 ≥60% 的相似对）</td></tr>
    <?php } $i = 1; foreach (array_slice($pairs, 0, 50) as $p) { ?>
    <tr>
      <td><?php echo $i++?></td>
      <td><a class="uid" href="../userinfo.php?user=<?php echo urlencode($p['u1'])?>" target="_blank"><?php echo htmlentities($p['u1'], ENT_QUOTES, "UTF-8")?></a></td>
      <td><?php echo intval($p['p1'])?></td>
      <td><?php echo $p['bc1'] ? intval($p['bc1']) : '-'?></td>
      <td><a class="uid" href="../userinfo.php?user=<?php echo urlencode($p['u2'])?>" target="_blank"><?php echo htmlentities($p['u2'], ENT_QUOTES, "UTF-8")?></a></td>
      <td><?php echo intval($p['p2'])?></td>
      <td><?php echo $p['bc2'] ? intval($p['bc2']) : '-'?></td>
      <td><b style="color:<?php echo floatval($p['sim']) >= 80 ? '#d03050' : '#f0a020'?>"><?php echo $p['sim']?>%</b></td>
    </tr>
    <?php } ?>
  </table>
</div>
</div>
        </div>
    </section>
</div>
</div>
<script src="../template/syzoj/css/Chart.min.js"></script>
<script>
var dist = <?php echo json_encode($dist)?>;
var prLabels = [], prData = [];
<?php foreach ($problemRisk as $pr) { ?>
prLabels.push('<?php echo "P".intval($pr['problem_id'])?>');
prData.push(<?php echo intval($pr['c'])?>);
<?php } ?>
new Chart(document.getElementById('c-dist'), { type:'bar', data:{labels:dist.labels,datasets:[{label:'相似对数',data:dist.data,backgroundColor:'#f0a020'}]}, options:{responsive:true,maintainAspectRatio:false,legend:{display:false},scales:{yAxes:[{ticks:{beginAtZero:true,precision:0},gridLines:{color:'#e6ebf2'}}],xAxes:[{gridLines:{display:false}}]}} });
new Chart(document.getElementById('c-problem'), { type:'bar', data:{labels:prLabels,datasets:[{label:'高风险相似对',data:prData,backgroundColor:'#d03050'}]}, options:{responsive:true,maintainAspectRatio:false,legend:{display:false},scales:{yAxes:[{ticks:{beginAtZero:true,precision:0},gridLines:{color:'#e6ebf2'}}],xAxes:[{gridLines:{display:false}}]}} });
</script>
</body>
</html>
