<?php $show_title="题目统计信息 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<script src="template/<?php echo $OJ_TEMPLATE?>/css/Chart.min.js"></script>
<style>
#avatar_container:before {
    content: "";
    display: block;
    padding-top: 100%;
}
</style>


<div class="padding">
<div class="ui grid">
    <div class="row">
        <div class="five wide column">
            <div class="ui card" style="width: 100%; " id="user_card">
                <div class="">
                <div class="column">
                      <h4 class="ui top attached block header">统计</h4>
                      <div class="ui bottom attached segment">
                        <div id="pie_chart_legend"></div>
                        <div style="width: 260px; height: 260px; margin-left: 15.5px; "><canvas style="width: 260px; height: 260px; " id="pie_chart"></canvas></div>
                      </div>
                  </div>
                </div>

            </div>

        </div>
        <div class="eleven wide column">
            <div class="ui grid">
                <div class="row">
                    <div class="column">
                        <h4 class="ui top attached block header">近 30 天提交趋势</h4>
                        <div class="ui bottom attached segment">
                            <div style="height:180px;position:relative;">
                                <canvas id="oj-ps-trend"></canvas>
                            </div>
                        </div>
                        <h4 class="ui top attached block header" style="margin-top:14px;">提交语言分布</h4>
                        <div class="ui bottom attached segment">
                            <div style="height:180px;position:relative;">
                                <canvas id="oj-ps-lang"></canvas>
                            </div>
                        </div>
                        <h4 class="ui top attached block header" style="margin-top:14px;">排序</h4>
                        <div class="ui bottom attached segment">
                            <table class="ui very basic table">
                              <thead>
                                <tr>
                                  <th>名次</th>
                                  <th>RunID</th>
                                  <th>用户名</th>
                                  <th>内存</th>
                                  <th>时间</th>
                                  <th>语言</th>
                                  <th>代码长度</th>
                                  <th>提交时间</th>
                                </tr>
                              </thead>
                            <tbody>
                            <?php
                            foreach($view_solution as $row){
                            echo "<tr>";
                            foreach($row as $table_cell){
                            echo "<td>";
                            echo "\t".$table_cell;
                            echo "</td>";
                            }
                            echo "</tr>";
                            }
                            ?>
                            <!-- <tr>
                            <td colspan="8">
                              <?php
                              echo "<a href='problemstatus.php?id=$id'>[首页]</a>";
                              echo "<a href='status.php?problem_id=$id'>[评测列表]</a>";
                              if ($page>$pagemin){
                              $page--;
                              echo "<a href='problemstatus.php?id=$id&page=$page'>[上一页]</a>";
                              $page++;
                              }
                              if ($page<$pagemax){
                              $page++;
                              echo "<a href='problemstatus.php?id=$id&page=$page'>[下一页]</a>";
                              $page--;
                              }
                              ?>
                              
                            </td>
                            </tr> -->
                            </table>
                            <div style="margin-bottom: 10px; ">
  
                                <div style="text-align: center; ">
                                <div class="ui pagination menu" style="box-shadow: none; ">
                                  <?php
                                    // <a class="icon item" href="status.php?" id="page_prev">  
                                    // 首页
                                    // </a>
                                    echo "<a class=\"item\" href='problemstatus.php?id=$id'>首页</a>";
                                    if ($page>$pagemin){
                                      $page--;
                                      echo "<a class=\"item\" href='problemstatus.php?id=$id&page=$page'>上一页</a>";
                                      $page++;
                                    }
                                    if ($page<$pagemax){
                                      $page++;
                                      echo "<a class=\"item\" href='problemstatus.php?id=$id&page=$page'>下一页</a>";
                                      $page--;
                                      }
                                    // <a class="item" href="status.php?&amp;top=65577">上一页</a>      
                                    // <a class="icon item" href="status.php?&amp;top=65538&amp;prevtop=65557" id="page_next">
                                    //   下一页
                                    // </a>
                                  ?>
                                </div>
                                </div>
                              </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>


<script>
$(function () {
  $('#user_card .image').dimmer({
    on: 'hover'
  });


  var pie = new Chart(document.getElementById('pie_chart').getContext('2d'), {
    aspectRatio: 1,
    type: 'pie',
    data: {
      datasets: [
        {
          data: [
            <?php if(isset($view_problem_number)&&is_array($view_problem_number)) foreach($view_problem_number as $row){
              echo $row.",";
            }
            ?>
          ],
          backgroundColor: [
            "#32CD32",
            "#FA8072",
            "#DC143C",
            "#FF9912",
            "#8A2BE2",
            "#4169E1",
            "#DB7093",
            "#082E54",
            "#FFFF00",
          ]
        }
      ],
      labels: [
        <?php if(isset($view_problem_title)&&is_array($view_problem_title)) foreach($view_problem_title as $row){
              echo "\"".$row."\",";
            }
            ?>
      ]
    },
    options: {
      responsive: true,
      legend: {
        display: false
      },
      legendCallback: function (chart) {
  			var text = [];
        text.push('<ul style="list-style: none; padding-left: 20px; margin-top: 0; " class="' + chart.id + '-legend">');
            text.push('<li style="font-size: 12px; width: 50%; display: inline-block; color: #666; "><span style="width: 10px; height: 10px; display: inline-block; border-radius: 50%; margin-right: 5px; background-color: #32CD32 ; "></span>');
                text.push('<?php echo "总提交: ".$view_problem[0][1]; ?>');
            text.push('</li>');
            text.push('<li style="font-size: 12px; width: 50%; display: inline-block; color: #666; "><span style="width: 10px; height: 10px; display: inline-block; border-radius: 50%; margin-right: 5px; background-color: #32CD32 ; "></span>');
                text.push('<?php echo "用户(提交): ".$view_problem[1][1]; ?>');
            text.push('</li>');
            text.push('<li style="font-size: 12px; width: 50%; display: inline-block; color: #666; "><span style="width: 10px; height: 10px; display: inline-block; border-radius: 50%; margin-right: 5px; background-color: #32CD32 ; "></span>');
                text.push('<?php echo "用户(解决): ".$view_problem[2][1]; ?>');
            text.push('</li>');
        text.push('</ul>');

  			text.push('<ul style="list-style: none; padding-left: 20px; margin-top: 0; " class="' + chart.id + '-legend">');

  			var data = chart.data;
  			var datasets = data.datasets;
  			var labels = data.labels;
        
  			if (datasets.length) {
  				for (var i = 0; i < datasets[0].data.length; ++i) {
  					text.push('<li style="font-size: 12px; width: 50%; display: inline-block; color: #666; "><span style="width: 10px; height: 10px; display: inline-block; border-radius: 50%; margin-right: 5px; background-color: ' + datasets[0].backgroundColor[i] + '; "></span>');
  					if (labels[i]) {
  						text.push(labels[i]);
              text.push(' : ' + datasets[0].data[i]);
  					}
  					text.push('</li>');
  				}
  			}

  			text.push('</ul>');
  			return text.join('');
  		}
    },
  });

  document.getElementById('pie_chart_legend').innerHTML = pie.generateLegend();
});
</script>

<script>
// 近 30 天提交趋势 + 语言分布（Excel 风格）
$(function(){
  var tctx = document.getElementById('oj-ps-trend');
  if(tctx && typeof Chart !== 'undefined'){
    new Chart(tctx, {
      type: 'line',
      data: {
        labels: [<?php if(!empty($trend_series)) foreach($trend_series as $t){ echo "'".$t['label']."',"; } ?>],
        datasets: [{
          label: '提交数',
          data: [<?php if(!empty($trend_series)) foreach($trend_series as $t){ echo $t['c'].","; } ?>],
          borderColor: '#2f6ee5',
          backgroundColor: 'rgba(47,110,229,.12)',
          borderWidth: 2,
          fill: true,
          tension: .3,
          pointRadius: 2
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        legend: { display: false },
        scales: {
          yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: '#e6ebf2' } }],
          xAxes: [{ gridLines: { display: false }, ticks: { maxTicksLimit: 15 } }]
        }
      }
    });
  }
  var lctx = document.getElementById('oj-ps-lang');
  if(lctx && typeof Chart !== 'undefined'){
    new Chart(lctx, {
      type: 'bar',
      data: {
        labels: [<?php if(!empty($lang_data)) foreach($lang_data as $l){ echo "'".$l['name']."',"; } ?>],
        datasets: [{
          label: '提交数',
          data: [<?php if(!empty($lang_data)) foreach($lang_data as $l){ echo $l['c'].","; } ?>],
          backgroundColor: ['#2f6ee5','#18a058','#f0a020','#d03050','#8b5cf6','#0ea5e9','#f59e0b','#14b8a6','#6366f1','#ef4444'],
          borderRadius: 3
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        legend: { display: false },
        scales: {
          yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: '#e6ebf2' } }],
          xAxes: [{ gridLines: { display: false } }]
        }
      }
    });
  }
});
</script>
<?php include("template/$OJ_TEMPLATE/footer.php");?>

