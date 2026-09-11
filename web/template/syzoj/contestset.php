<?php $show_title="Contest".$view_cid." - ".$view_title." - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>

<style>
/* 自定义样式增强 */
.contest-container {
  background-color: #f8f9fa;
  min-height: calc(100vh - 100px);
  padding: 20px 0;
}

.contest-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  padding: 25px 30px;
  color: white;
  margin-bottom: 25px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  position: relative;
  overflow: hidden;
}

.contest-header::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
  background-size: cover;
}

.contest-header h1 {
  margin: 0 0 10px 0;
  font-size: 2.2rem;
  font-weight: 700;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.contest-time-info {
  display: flex;
  justify-content: space-between;
  margin-top: 15px;
  font-size: 1.1rem;
  font-weight: 500;
}

.contest-time-label {
  background: rgba(255,255,255,0.2);
  padding: 8px 15px;
  border-radius: 20px;
  backdrop-filter: blur(5px);
}

.contest-status-section {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
  margin-bottom: 25px;
}

.contest-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 15px;
}

.contest-buttons .ui.button {
  border-radius: 8px;
  font-weight: 500;
  transition: all 0.3s ease;
}

.contest-buttons .ui.button:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.contest-status-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: flex-end;
}

.contest-status-badges .ui.button {
  border-radius: 20px;
  font-weight: 500;
  min-width: 100px;
}

.contest-description {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
  margin-bottom: 25px;
}

.contest-description .header {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  color: white;
  font-weight: 600;
  padding: 15px 20px;
}

.contest-description .segment {
  padding: 20px;
  line-height: 1.6;
}

.contest-problems-table {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
  margin-bottom: 25px;
}

.contest-problems-table table {
  border-collapse: collapse;
  width: 100%;
}

.contest-problems-table thead th {
  background-color: #f1f3f5;
  color: #495057;
  font-weight: 600;
  padding: 15px 12px;
  border-bottom: 2px solid #e9ecef;
}

.contest-problems-table tbody tr {
  transition: background-color 0.2s ease;
  border-bottom: 1px solid #f1f3f5;
}

.contest-problems-table tbody tr:hover {
  background-color: #f8f9fa;
}

.contest-problems-table tbody td {
  padding: 15px 12px;
  vertical-align: middle;
}

.problem-status {
  text-align: center;
}

.status-solved {
  color: #198754;
  font-weight: bold;
}

.status-attempted {
  color: #dc3545;
  font-weight: bold;
}

.status-unsolved {
  color: #6c757d;
}

.problem-id {
  font-weight: 600;
  color: #495057;
  text-align: center;
}

.problem-title {
  font-weight: 500;
  color: #212529;
  transition: color 0.2s ease;
}

.problem-title:hover {
  color: #0d6efd;
  text-decoration: none;
}

.problem-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.problem-tag {
  font-size: 0.75rem;
  padding: 4px 8px;
  border-radius: 4px;
  font-weight: 500;
}

.problem-stats {
  text-align: center;
  font-weight: 500;
}

.timer-progress-container {
  margin: 20px 0;
  padding: 0 10px;
}

.timer-progress {
  height: 10px;
  border-radius: 5px;
  overflow: hidden;
  background-color: #e9ecef;
}

.timer-progress-bar {
  height: 100%;
  border-radius: 5px;
  transition: width 0.5s ease;
}

.timer-progress-remaining {
  background: linear-gradient(90deg, #28a745, #20c997);
}

.timer-progress-running {
  background: linear-gradient(90deg, #007bff, #6f42c1);
}

.timer-progress-upcoming {
  background: linear-gradient(90deg, #6c757d, #adb5bd);
}

.current-time {
  background: #495057;
  color: white;
  border-radius: 20px;
  padding: 8px 15px;
  font-weight: 500;
  font-family: 'Courier New', monospace;
}

@media (max-width: 768px) {
  .contest-time-info {
    flex-direction: column;
    gap: 10px;
  }
  
  .contest-status-section {
    flex-direction: column;
  }
  
  .contest-buttons {
    justify-content: center;
  }
  
  .contest-status-badges {
    justify-content: center;
    margin-top: 15px;
  }
  
  .contest-problems-table thead th:nth-child(4),
  .contest-problems-table tbody td:nth-child(4) {
    display: none;
  }
}

/* 美化进度条 */
#timer-progress {
  height: 12px;
  border-radius: 6px;
  margin: 20px 0;
  background-color: #e9ecef;
  overflow: hidden;
}

#timer-progress .bar {
  border-radius: 6px;
  transition: width 1s ease-in-out;
}

/* 美化标签 */
.ui.label.pointing.below.left::before {
    left: 12%;
}

.ui.label.pointing.below.right::before {
    left: 88%;
}

.ui.label.pointing.below.left {
    margin-bottom: 0;
}

.ui.label.pointing.below.right {
    margin-bottom: 0;
    float: right;
}

#back_to_contest {
    display: none;
}
</style>

<div class="contest-container">
  <div class="ui container">
    <!-- 比赛标题和时间信息 -->
    <div class="contest-header">
      <h1>Contest<?php echo $view_cid?> - <?php echo $view_title ?></h1>
      <div class="contest-time-info">
        <div class="contest-time-label"><?php echo $view_start_time?></div>
        <div class="contest-time-label"><?php echo $view_end_time?></div>
      </div>
    </div>

    <!-- 进度条 -->
    <div class="timer-progress-container">
      <div id="timer-progress" class="ui tiny indicating progress" data-percent="50">
        <div class="bar" style="width: 0%; transition-duration: 300ms;"></div>
      </div>
    </div>

    <!-- 状态和控制按钮 -->
    <div class="contest-status-section">
      <div class="ui grid">
        <div class="row">
          <div class="sixteen wide column">
            <div class="contest-buttons">
              <a class="ui blue button" href="contestrank.php?cid=<?php echo $view_cid?>">
                <i class="trophy icon"></i>ACM排行榜
              </a>
              <a class="ui yellow button" href="contestrank-oi.php?cid=<?php echo $view_cid?>">
                <i class="chart bar icon"></i>OI排行榜
              </a>
              <a class="ui green button" href="status.php?cid=<?php echo $view_cid?>">
                <i class="list icon"></i>提交记录
              </a>
              <!-- <a class="ui pink button" href="conteststatistics.php?cid=<?php echo $view_cid?>">
                <i class="chart pie icon"></i>比赛统计
              </a> -->
            </div>
            
            <div class="contest-status-badges">
              <?php
                if ($now > $end_time)
                  echo "<span class=\"ui grey button\"><i class=\"flag checkered icon\"></i>已结束</span>";
                else if ($now < $start_time)
                  echo "<span class=\"ui red button\"><i class=\"hourglass start icon\"></i>未开始</span>";
                else
                  echo "<span class=\"ui green button\"><i class=\"hourglass half icon\"></i>进行中</span>";
              ?>
              <?php
                if ($view_private == '0')
                  echo "<span class=\"ui blue button\"><i class=\"unlock icon\"></i>公开</span>";
                else
                  echo "<span class=\"ui pink button\"><i class=\"lock icon\"></i>私有</span>";
              ?>
              <span class="current-time">
                <i class="clock icon"></i><span id="nowdate"><?php echo date("Y-m-d H:i:s")?></span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if($view_description){ ?>
    <!-- 比赛描述和公告 -->
    <div class="contest-description">
      <h4 class="ui top attached header">
        <i class="info circle icon"></i>
        信息与公告
      </h4>
      <div class="ui attached segment font-content">
        <?php echo $view_description?>
      </div>
    </div>
    <?php } ?>

    <!-- 题目列表 -->
    <div class="contest-problems-table">
      <table class="ui selectable celled table">
        <thead>
          <tr>
            <th class="one wide center aligned">
              <?php if(isset($_SESSION[$OJ_NAME.'_'.'user_id'])) echo "状态" ?>
            </th>
            <th class="two wide center aligned">题目编号</th>
            <th class="six wide">题目</th>
            <th class="four wide">分类</th>
            <th class="one wide center aligned">正确</th>
            <th class="one wide center aligned">提交</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $color = array("blue", "teal", "orange", "pink", "olive", "red", "violet", "yellow", "green", "purple");
            foreach($view_problemset as $row){
              echo "<tr>";
              $i = 0;
              foreach($row as $table_cell){
                if($i == 0) {
                  // 状态列
                  if(isset($_SESSION[$OJ_NAME.'_'.'user_id'])) {
                    if($table_cell == "<div class='label label-success'>Y</div>") {
                      echo "<td class='problem-status status-solved'><i class='check icon'></i></td>";
                    } else if($table_cell == "<div class='label label-danger'>N</div>") {
                      echo "<td class='problem-status status-attempted'><i class='times icon'></i></td>";
                    } else {
                      echo "<td class='problem-status status-unsolved'><i class='minus icon'></i></td>";
                    }
                  }
                } else if($i == 1) {
                  // 题目ID
                  echo "<td class='problem-id'>".$table_cell."</td>";
                } else if($i == 2) {
                  // 题目标题
                  echo "<td class='problem-title-cell'>".$table_cell."</td>";
                } else if($i == 3) {
                  // 分类标签
                  if($table_cell) {
                    $cats = explode(" ", $table_cell);
                    echo "<td><div class='problem-tags'>";
                    $tcolor = 0;
                    foreach ($cats as $cat) {
                      if(trim($cat) == "") continue;
                      $label_theme = $color[$tcolor % count($color)];
                      $tcolor++;
                      echo '<a href="problemset.php?search=' . urlencode($cat) . '" class="ui mini '.$label_theme.' label problem-tag">';
                      echo htmlentities($cat, ENT_QUOTES, 'utf-8');
                      echo "</a>";
                    }
                    echo "</div></td>";
                  } else {
                    echo "<td></td>";
                  }
                } else if($i == 4) {
                  // 正确数
                  echo "<td class='problem-stats'>".$table_cell."</td>";
                } else if($i == 5) {
                  // 提交数
                  echo "<td class='problem-stats'>".$table_cell."</td>";
                }
                $i++;
              }
              echo "</tr>";
            }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
$(function() {
    // 初始化进度条
    var totalTime = <?php echo (strtotime($view_end_time)- strtotime($view_start_time))?>;
    var elapsedTime = Date.now() / 1000 - <?php echo strtotime($view_start_time)?>;
    var progressPercent = Math.min(100, Math.max(0, (elapsedTime / totalTime) * 100));
    
    $('#timer-progress').progress({
        value: elapsedTime,
        total: totalTime
    });
    
    // 更新进度条颜色
    var progressBar = $('#timer-progress .bar');
    if (progressPercent >= 100) {
        progressBar.addClass('timer-progress-remaining');
    } else if (progressPercent > 0) {
        progressBar.addClass('timer-progress-running');
    } else {
        progressBar.addClass('timer-progress-upcoming');
    }

    // 定期更新进度条
    setInterval(function() {
        elapsedTime = Date.now() / 1000 - <?php echo strtotime($view_start_time)?>;
        progressPercent = Math.min(100, Math.max(0, (elapsedTime / totalTime) * 100));
        
        $('#timer-progress').progress({
            value: elapsedTime,
            total: totalTime
        });
        
        // 更新进度条颜色
        if (progressPercent >= 100) {
            progressBar.removeClass('timer-progress-running timer-progress-upcoming').addClass('timer-progress-remaining');
        } else if (progressPercent > 0) {
            progressBar.removeClass('timer-progress-remaining timer-progress-upcoming').addClass('timer-progress-running');
        } else {
            progressBar.removeClass('timer-progress-remaining timer-progress-running').addClass('timer-progress-upcoming');
        }
    }, 5000);
});

// 实时时钟
var diff = new Date("<?php echo date("Y/m/d H:i:s")?>").getTime() - new Date().getTime();

function clock() {
    var x, h, m, s, n, xingqi, y, mon, d;
    var x = new Date(new Date().getTime() + diff);
    y = x.getYear() + 1900;
    if (y > 3000) y -= 1900;
    mon = x.getMonth() + 1;
    d = x.getDate();
    xingqi = x.getDay();
    h = x.getHours();
    m = x.getMinutes();
    s = x.getSeconds();
    n = y + "-" + mon + "-" + d + " " + (h >= 10 ? h : "0" + h) + ":" + (m >= 10 ? m : "0" + m) + ":" + (s >= 10 ? s : "0" + s);
    document.getElementById('nowdate').innerHTML = n;
    setTimeout("clock()", 1000);
}
clock();
</script>

<script src="include/sortTable.js"></script>

<?php include("template/$OJ_TEMPLATE/footer.php");?>
