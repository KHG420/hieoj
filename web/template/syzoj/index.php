<?php $show_title="首页 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<link rel="stylesheet" href="./template/sta_sty/layui/css/layui.css">
<script defer src="./template/sta_sty/layui/layui.js"></script>

<style>
    /* 全局样式优化 */
    .syzoj-container {
        padding: 20px 0;
        background-color: #f8f9fa;
        min-height: calc(100vh - 120px);
    }
    
    .syzoj-card {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
        margin-bottom: 20px;
    }
    
    
    .syzoj-card-header {
        padding: 14px 20px;
        border-bottom: 1px solid #e6ebf2;
        background: #f8fafc;
        color: #1f2d3d;
        font-weight: 600;
        border-radius: 10px 10px 0 0;
        display: flex;
        align-items: center;
    }
    
    .syzoj-card-header i {
        margin-right: 8px;
        font-size: 18px;
        color: #2f6ee5;
    }
    
    .syzoj-card-body {
        padding: 20px;
    }
    
    .syzoj-section-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    /* 表格样式优化 */
    .syzoj-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .syzoj-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
        padding: 12px 15px;
        text-align: left;
        border-bottom: 2px solid #dee2e6;
    }
    
    .syzoj-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #e9ecef;
        transition: background-color 0.2s;
    }
    
    .syzoj-table tr:hover td {
        background-color: #f8f9fa;
    }
    
    /* 排名下拉菜单样式 */
    .syzoj-rank-selector {
        display: inline-flex;
        align-items: center;
        margin-left: 10px;
    }
    
    .syzoj-rank-selector .dropdown {
        margin-left: 8px;
    }
    
    /* 响应式调整 */
    @media (max-width: 768px) {
        .syzoj-container .grid {
            flex-direction: column;
        }
        
        .syzoj-card-body {
            padding: 15px;
        }
        
        .syzoj-table {
            font-size: 14px;
        }
        
        .syzoj-table th, 
        .syzoj-table td {
            padding: 8px 10px;
        }
    }
    
    /* 克制风格：移除卡片入场动画 */
    
    /* 链接样式 */
    a {
        color: #4a6ee0;
        transition: color 0.2s;
    }
    
    a:hover {
        color: #2c4ec7;
        text-decoration: underline;
    }
</style>

<?php $has_recent_submissions = array_sum(array_column($chart_days, 'all')) > 0; ?>
<div class="syzoj-container">
    <div class="ui three column grid">
        <!-- 左侧内容区域 -->
        <div class="eleven wide column">
            <section class="syzoj-card">
                <div class="syzoj-card-header"><i class="ui compass icon" aria-hidden="true"></i><h2>今日冒险</h2></div>
                <div class="syzoj-card-body">
                    <p>重战一道旧题，或用三道题走完一段新路。还有影子挑战、反例猎人和本周校园接力。</p>
                    <a class="ui primary button" href="adventure.php">重战一道旧题</a>
                    <a class="ui basic button" href="adventure.php?tab=route">开启三题远征</a>
                </div>
            </section>
            <!-- 公告卡片 -->
            <div class="syzoj-card">
                <div class="syzoj-card-header">
                    <i class="ui info icon"></i><h2>公告</h2>
                </div>
                <div class="syzoj-card-body">
                    <table class="syzoj-table" lay-filter="demo-new">
                        <thead>
                            <tr>
                                <th lay-data="{field:'0', width:'80%', align: 'left'}"><?php echo $MSG_TITLE;?></th>
                                <th lay-data="{field:'1', align: 'center'}">时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql_news = "select * FROM `news` WHERE `defunct`!='Y' AND `title`!='faqs.cn' ORDER BY `importance` ASC,`time` DESC";
                            $result_news = mysql_query_cache( $sql_news );
                            if ( $result_news ) {
                                foreach ( $result_news as $row ) {
                                    echo "<tr>"."<td>"
                                        ."<a href=\"viewnews.php?id=".$row["news_id"]."\">"
                                        .$row["title"]."</a></td>"
                                        ."<td>".explode(" ",$row["time"])[0]."</td>"."</tr>";
                                }
                            }
                            $today = date("Y-m-01", time());
                            $month = date("n", time());
                            $l_month = $month - 5;
                            ?>
                        </tbody>
                    </table>
                    <script>
                    // 2026-09-10 性能优化：layui.js 已改 defer，等 DOM 就绪后再初始化表格
                    window.addEventListener("DOMContentLoaded", function(){
                        var table = layui.table;

                        //转换静态表格
                        table.init('demo-new', {
                            height: 'full', //设置高度
                            limit: 3, //注意：请务必确保 limit 参数（默认：10）是与你服务端限定的数据条数一致
                            //支持所有基础参数
                            page: true,
                            skin: "line",
                            limits: [3, 10, 20],
                            done: function(){document.querySelectorAll('.layui-laypage-skip input').forEach(function(input){input.setAttribute('aria-label','跳转到页码')});
            document.querySelectorAll('.layui-laypage-limits select').forEach(function(select){select.setAttribute('aria-label','每页条数')});},
                        });
                    });
                    </script>
                </div>
            </div>
            
            <!-- 近 7 天提交趋势卡片（Excel 风格组合图） -->
            <div class="syzoj-card">
                <div class="syzoj-card-header">
                    <i class="ui chart line icon"></i><h2>近 7 天提交趋势</h2>
                </div>
                <div class="syzoj-card-body">
                    <?php if ($has_recent_submissions) { ?><div style="height:220px;position:relative;">
                        <canvas id="oj-trend-chart" role="img" aria-label="近七天每日提交和通过数量"></canvas>
                    </div><?php } else { ?><p class="oj-empty">近 7 天暂无提交记录。<a href="problemset.php">开始练习</a>，完成你的下一道题。</p><?php } ?>
                </div>
            </div>
            
            <!-- 排名卡片 -->
            <div class="syzoj-card">
                <div class="syzoj-card-header">
                    <i class="ui signal icon"></i>
                    <div class="syzoj-rank-selector">
                        <span id="month"><?php echo $month ?></span>月排名
                        <div class="ui simple dropdown item">
                            <i class="dropdown icon"></i>
                            <div class="menu">
                                <?php
                                    for ($i = 0; $i < 5; $i ++ ) {
                                        echo "<div class=\"item\" id='rank-".$i."'>".date('n', strtotime("-$i month"))."月</div>";
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="syzoj-card-body">
                    <table id="demo" lay-filter="test"></table>
                </div>
            </div>            
            <!-- 近期比赛卡片（移至左栏，平衡左右高度） -->
            <div class="syzoj-card">
                <div class="syzoj-card-header">
                    <i class="ui calendar icon"></i><h2>近期比赛&作业</h2>
                </div>
                <div class="syzoj-card-body">
                    <table class="syzoj-table">
                        <thead>
                            <tr>
                                <th><?php echo $MSG_CONTEST_NAME;?></th>
                                <th><?php echo $MSG_START_TIME;?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $sql_contests = "select * FROM `contest` where defunct='N' ORDER BY `contest_id` DESC LIMIT 5";
                                $result_contests = mysql_query_cache( $sql_contests );
                                if ( $result_contests ) {
                                    $i = 1;
                                    foreach ( $result_contests as $row ) {
                                        echo "<tr>"."<td>"
                                            ."<a href=\"contest.php?cid=".$row["contest_id"]."\">"
                                            .$row["title"]."</a></td>"
                                            ."<td>".$row["start_time"]."</td>"."</tr>";
                                    }
                                }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- 右侧边栏 -->
        <div class="right floated five wide column">
            <!-- 提交榜卡片 -->
            <div class="syzoj-card">
                <div class="syzoj-card-header">
                    <i class="ui rss icon"></i> <?php echo $month ?>月提交榜
                </div>
                <div class="syzoj-card-body">
                    <table class="syzoj-table">
                        <thead>
                            <tr>
                                <th width="60%"><?php echo $MSG_TITLE;?></th>
                                <th width="40%">尝试数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql_problems="SELECT problem.problem_id, problem.title, s.submit FROM `problem`
                                RIGHT JOIN
                                (SELECT problem_id,COUNT(problem_id) as submit FROM solution
                                WHERE solution.in_date>=?
                                GROUP BY problem_id order by submit desc LIMIT 5 ) s
                                ON problem.problem_id = s.problem_id
                                WHERE problem.`defunct`='N'";
                            $result_problems = mysql_query_cache($sql_problems, $today);
                            if (!$result_problems) echo '<tr><td colspan="2" class="oj-empty">本月暂无提交记录</td></tr>';
                            if ( $result_problems ) {
                                $i = 1;
                                foreach ( $result_problems as $row ) {
                                    echo "<tr>"."<td>"
                                        ."<a href=\"problem.php?id=".$row["problem_id"]."\">"
                                        .$row["title"]."</a></td>"
                                        ."<td>".$row["submit"]."</td>"."</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 上月做题榜卡片 -->
            <div class="syzoj-card">
                <div class="syzoj-card-header">
                    <i class="ui signal icon"></i><h2>上月做题榜</h2>
                </div>
                <div class="syzoj-card-body">
                    <table class="syzoj-table">
                        <thead>
                            <tr>
                                <th width="20%">名次</th>
                                <th width="40%">姓名</th>
                                <th width="40%">做题数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $firstday=date('Y-m-d', strtotime(date('Y-m-01') . ' -1 month'));
                            $lastday=date('Y-m-d', strtotime(date('Y-m-01') . ' -1 day'));
                            $end_exclusive=date('Y-m-d', strtotime($lastday . ' +1 day'));
                            $sql_users = "SELECT users.`user_id`,`school`,`nick`,s.`solved`,s.`submit` FROM `users`
                                                inner join
                                                (select count(distinct case when result=4 then problem_id end) solved,
                                                        count(problem_id) submit,user_id
                                from solution
                                where in_date>=? and in_date<?
                                group by user_id
                                having solved>0
                                order by solved desc limit 50) s
                            on users.user_id=s.user_id
                                        ORDER BY s.`solved` DESC,s.submit,reg_time  LIMIT  3";
                            $result_users = mysql_query_cache($sql_users, $firstday, $end_exclusive);
                            if (!$result_users) echo '<tr><td colspan="3" class="oj-empty">上月暂无做题记录</td></tr>';
                            if ( $result_users ) {
                                $i = 1;
                                foreach ( $result_users as $row ) {
                                    echo "<tr>"."<td>".$i++."</td>"."<td>"
                                        ."<a href=\"userinfo.php?user=".$row["user_id"]."\">"
                                        .$row["nick"]."</a></td>"
                                        ."<td>".$row["solved"]."</td>"."</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <section class="syzoj-card" aria-labelledby="lab-home-title">
                <div class="syzoj-card-header"><i class="ui university icon" aria-hidden="true"></i><h2 id="lab-home-title">ACM 实验室</h2></div>
                <div class="syzoj-card-body"><p>一起学习算法、参与训练和竞赛，也一起完善我们的在线评测平台。</p>
                <?php require_once './include/editorial.inc.php'; $labRecruitment=null; try { $labRecruitment=editorial_query('SELECT recruitment_open FROM acm_lab_settings WHERE id=1')->fetchColumn(); } catch(Throwable $e) {} ?>
                <?php if($labRecruitment!==null && $labRecruitment!==false){ ?><p><?php echo $labRecruitment?'招新进行中 · 欢迎零基础同学申请':'暂未开放申请 · 可查看介绍与联系方式'; ?></p><?php } ?><div class="ui wrapping buttons" style="display:flex;flex-wrap:wrap;gap:8px">
                    <a class="ui basic button" href="lab.php">了解实验室</a><a class="ui primary button" href="lab.php?tab=join">加入我们</a><a class="ui basic button" href="lab.php?tab=feedback&amp;kind=bug">发现 Bug</a><a class="ui basic button" href="lab.php?tab=feedback&amp;kind=suggestion">意见建议</a>
                </div></div>
            </section>

            <!-- 友情链接卡片 -->
            <style>
            .oj-friend-links { display: flex; flex-direction: column; gap: 9px; }
            .oj-friend-links .group { font-size: 13px; font-weight: 600; color: #333; margin-bottom: 1px; }
            .oj-friend-links a { display: flex; align-items: center; gap: 6px; color: #2f6ee5; font-size: 14px; text-decoration: none; }
            .oj-friend-links a:hover { text-decoration: underline; }
            .oj-friend-links .tag { font-size: 12px; color: #8a93a6; }
            </style>
            <div class="syzoj-card">
                <div class="syzoj-card-header">
                    <i class="ui linkify icon"></i><h2>友情链接</h2>
                </div>
                <div class="syzoj-card-body">
                    <div class="oj-friend-links">
                        <div class="group">新版 OJ（开发中）</div>
                        <a href="https://haoran37.github.io/HnieOJ/" target="_blank" rel="noopener">在线演示<span class="tag">haoran37.github.io/HnieOJ</span></a>
                        <a href="https://github.com/haoran37/HnieOJ" target="_blank" rel="noopener">前端仓库<span class="tag">github.com/haoran37/HnieOJ</span></a>
                        <a href="https://github.com/haoran37/HnieOJ-backend" target="_blank" rel="noopener">后端仓库<span class="tag">github.com/haoran37/HnieOJ-backend</span></a>
                        <a href="https://github.com/haoran37/go-judge" target="_blank" rel="noopener">判题机仓库<span class="tag">github.com/haoran37/go-judge</span></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 2026-09-10 性能优化：layui.js 已改 defer，等 DOM 就绪后再初始化表格
window.addEventListener("DOMContentLoaded", function(){
    var table = layui.table;
    var tableIns = table.render({
        url: '/getranklist.php'
        ,method: "get"
        ,where: {
            start: "<?php echo date('Y-m-01', strtotime("-0 month"));?>",
            end: "<?php echo date('Y-m-t', strtotime("-0 month"));?>",
        }
        ,elem: '#demo' //指定原始表格元素选择器（推荐id选择器）
        ,skin: "line"
        ,text: {none: "所选月份暂无排名记录"}
        ,limit: 20
        ,id: "query"
        ,height: '' //容器高度
        ,page: true
        ,limits: [20, 50, 100, 200]
        ,cols: [[
            {field: 'rank', title: '名次', width: '10%', align: 'center'}
            ,{field: 'user_id', title: '学号', width: '20%', align: 'center'}
            ,{field: 'school', title: '专业班级',  align: 'center'}
            ,{field: 'nick', title: '姓名', width: '17%', align: 'center'}
            ,{field: 'solved', title: '解决数', width: '14%', align: 'center'}
            ,{field: 'submit', title: '提交数', width: '14%', align: 'center'}
        ]] //设置表头
        //,…… //更多参数参考右侧目录：基本参数选项
        ,done: function(res){
            $("#query-msg").html(res.message);
            document.querySelectorAll('.layui-laypage-skip input').forEach(function(input){input.setAttribute('aria-label','跳转到页码')});
            document.querySelectorAll('.layui-laypage-limits select').forEach(function(select){select.setAttribute('aria-label','每页条数')});
        }
    });
    
    <?php for ($i = 0; $i < 5; $i ++) {?>
    $("#rank-<?php echo $i;?>").click(function () {
        $("#month").html("<?php echo date('n', strtotime("-$i month"));?>");
        //执行渲染
        tableIns.reload({
            where: {
                start: "<?php echo date('Y-m-01', strtotime("-$i month"));?>",
                end: "<?php echo date('Y-m-t', strtotime("-$i month"));?>",
                }
                ,page: {
                    curr: 1 //重新从第 1 页开始
                }
        });
    });
    <?php }?>

    //注意：选项卡 依赖 element 模块，否则无法进行功能性操作
    layui.use('element', function(){
        var element = layui.element;
        //…
    });
});
</script>

<?php if ($has_recent_submissions) { ?><script defer src="template/<?php echo $OJ_TEMPLATE?>/css/Chart.min.js"></script>
<script>
// 近 7 天提交趋势（Excel 风格：提交折线 + 通过柱状）
$(function(){
  var ctx = document.getElementById('oj-trend-chart');
  if(!ctx || typeof Chart === 'undefined') return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: [<?php foreach($chart_days as $md=>$x){ echo "'".$x['label']."',"; } ?>],
      datasets: [
        {
          label: '提交',
          type: 'line',
          data: [<?php foreach($chart_days as $md=>$x){ echo $x['all'].","; } ?>],
          borderColor: '#2f6ee5',
          backgroundColor: 'rgba(47,110,229,.12)',
          borderWidth: 2,
          fill: true,
          tension: .3,
          pointRadius: 3,
          pointBackgroundColor: '#2f6ee5',
          order: 1
        },
        {
          label: '通过',
          data: [<?php foreach($chart_days as $md=>$x){ echo $x['ac'].","; } ?>],
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
        xAxes: [{ gridLines: { display: false } }]
      }
    }
  });
});
</script>
<?php } ?>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
