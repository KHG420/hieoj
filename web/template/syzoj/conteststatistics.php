<?php $show_title="$MSG_STATISTICS - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>
.oj-cs { max-width: 1200px; margin: 0 auto; }
.oj-card { background: #fff; border: 1px solid #e6ebf2; border-radius: 10px; box-shadow: 0 4px 14px rgba(15,23,42,.06); padding: 20px; margin-bottom: 20px; }
.oj-card h3 { margin: 0 0 14px; font-size: 17px; color: #1f2d3d; }
.oj-table-wrap { overflow-x: auto; }
.oj-table-wrap table { width: 100%; border-collapse: collapse; font-size: 13px; }
.oj-table-wrap th, .oj-table-wrap td { padding: 7px 10px; border: 1px solid #e9ecef; text-align: center; white-space: nowrap; }
.oj-table-wrap thead th { background: #f8fafc; font-weight: 600; }
.oj-table-wrap a { color: #2f6ee5; text-decoration: none; }
.oj-table-wrap a:hover { text-decoration: underline; }
</style>
<div class="padding oj-cs">
  <div class="oj-card">
    <center><h3>Contest Statistics</h3>
    <div class="oj-table-wrap">
    <table id=cs>
      <thead>
      <tr class=toprow><th><th>AC<th>PE<th>WA<th>TLE<th>MLE<th>OLE<th>RE<th>CE<th><th>TR<th>Total
      <?php
        $i=0;
        foreach ($language_name as $lang){
          if(isset($R[$pid_cnt][$i+11]))
            echo "<th class='center'>$language_name[$i]</th>";
          else
            echo "<th>";
          $i++;
        }
      ?>
      </tr>
      </thead>
      <tbody>
      <?php
      for ($i=0;$i<$pid_cnt;$i++){
        if(!isset($PID[$i])) $PID[$i]="";
        if ($i&1)
          echo "<tr align=center class=oddrow><td>";
        else
          echo "<tr align=center class=evenrow><td>";
        echo "<a href='problem.php?cid=$cid&pid=$i'>$PID[$i]</a>";
        for ($j=0;$j<count($language_name)+11;$j++) {
          if(!isset($R[$i][$j])) $R[$i][$j]="";
          echo "<td>".$R[$i][$j];
        }
        echo "</tr>";
      }
      echo "<tr align=center class=evenrow><td>Total";
      for ($j=0;$j<count($language_name)+11;$j++) {
        if(!isset($R[$i][$j])) $R[$i][$j]="";
        echo "<td>".$R[$i][$j];
      }
      echo "</tr>";
      ?>
      </tbody>
    </table>
    </div>
    <div style="width:100%;max-width:700px;height:300px;margin:14px auto 0;position:relative;">
        <canvas id="submission"></canvas>
    </div>
    </center>
  </div>
</div>
<script type="text/javascript" src="<?php echo $OJ_CDN_URL?>include/jquery.tablesorter.js"></script>
<script type="text/javascript">
$(document).ready(function(){ $("#cs").tablesorter(); });
</script>
<script type="text/javascript" src="template/<?php echo $OJ_TEMPLATE?>/css/Chart.min.js"></script>
<script type="text/javascript">
// 提交趋势（Excel 风格：提交折线 + 通过柱状）
$(function () {
var ctx = document.getElementById('submission');
if(!ctx || typeof Chart === 'undefined') return;
var d1 = [], d2 = [], labels = [], m1 = {}, m2 = {}, allKeys = {};
<?php foreach($chart_data_all as $k=>$d){ ?> d1.push([<?php echo $k?>, <?php echo $d?>]); <?php } ?>
<?php foreach($chart_data_ac as $k=>$d){ ?> d2.push([<?php echo $k?>, <?php echo $d?>]); <?php } ?>
d1.forEach(function(p){ m1[p[0]] = p[1]; allKeys[p[0]] = 1; });
d2.forEach(function(p){ m2[p[0]] = p[1]; allKeys[p[0]] = 1; });
var keys = Object.keys(allKeys).map(Number).sort(function(a,b){ return a-b; });
var subData = [], acData = [];
keys.forEach(function(k){
  var d = new Date(k);
  labels.push((d.getMonth()+1)+'-'+d.getDate());
  subData.push(m1[k] || 0);
  acData.push(m2[k] || 0);
});
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [
      {
        label: '<?php echo $MSG_SUBMIT?>',
        type: 'line',
        data: subData,
        borderColor: '#2f6ee5',
        backgroundColor: 'rgba(47,110,229,.12)',
        borderWidth: 2,
        fill: true,
        tension: .3,
        pointRadius: 2,
        order: 1
      },
      {
        label: '<?php echo $MSG_AC?>',
        data: acData,
        backgroundColor: '#18a058',
        borderRadius: 3,
        order: 2
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, fontSize: 12 } },
    scales: {
      yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: '#e6ebf2' } }],
      xAxes: [{ gridLines: { display: false }, ticks: { maxTicksLimit: 20 } }]
    }
  }
});
});
</script>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
