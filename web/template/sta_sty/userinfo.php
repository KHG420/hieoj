<?php $show_title="用户主页 - $OJ_NAME"; include("template/$OJ_TEMPLATE/header.php"); ?>
<link rel="stylesheet" href="./template/sta_sty/layui/css/layui.css">
<script src="./template/sta_sty/layui/layui.js"></script>
    <style>
        #main1 {
            height: 30vw;
        }

        #main2 {
            height: 20vw;
        }

        @media only screen and (max-width: 600px) {
            #main1 {
                height: 75vw;
            }

            #main2 {
                height: 80vw;
            }
        }

        @media only screen and (max-width: 900px) {
            #main2 {
                height: 40vw;
            }
        }
    </style>
<div class="oj-page-heading"><h1>用户主页</h1><?php if (isset($_SESSION[$OJ_NAME.'_user_id']) && $_SESSION[$OJ_NAME.'_user_id'] === $user) { ?><a class="ui basic button" href="adventure.php?tab=memoir">我的解题回忆录</a><?php } ?></div>
<?php
$sql="SELECT COUNT(distinct date_format(time,'%Y-%m-%d')) as days FROM `loginlog` WHERE user_id=?";
$result = pdo_query( $sql, $user);
$days = !empty($result[0]['days']) ? $result[0]['days'] : 0;

function format_date($time){
    $t=time()-$time;
    $f=array(
        '31536000'=>'年',
        '2592000'=>'个月',
        '604800'=>'星期',
        '86400'=>'天',
        '3600'=>'小时',
        '60'=>'分钟',
        '1'=>'秒'
    );
    foreach ($f as $k=>$v)    {
        if (0 !=$c=floor($t/(int)$k)) {
            return $c.$v.'前';
        }
    }
    return '刚刚';
}
$sql = "SELECT UNIX_TIMESTAMP(time) as time FROM `loginlog` WHERE user_id=? ORDER BY time DESC LIMIT 1";
$result = pdo_query($sql, $user);
$last_time = !empty($result[0]['time']) ? $result[0]['time'] : time();
?>

<div class="oj-profile">
    <div style="padding: 1px;">
        <div class="layui-row layui-col-space16">
            <!--            <div class="layui-col-md12">-->
            <!--                <div style="height: 5px;"></div>-->
            <!--            </div>-->
            <div class="layui-col-md4">
                <div class="layui-card">
                    <div class="layui-card-header">用户信息</div>
                    <div class="blurring dimmable image" id="avatar_container" style="float:left;">
                        <?php $default = ""; $grav_url = "/upload/anonymous.png"; ?>
                        <?php
                        // 如果email填写的是qq邮箱，取QQ头像显示
                        $qq = "";
                        if ($qq_num != "") $qq = $qq_num;
                        else {
                            $qq=stripos($email,"@qq.com");
                            $qq=urlencode(substr($email,0,$qq));
                        }
                        if($qq>0){
                            $grav_url="https://q1.qlogo.cn/g?b=qq&nk=$qq&s=5";
                        }
                        ?>
                        <img alt="用户头像" width="85" height="85" style="height:85px; margin-left:20px;  margin-right:20px; margin-top: 15px;" src="<?php echo $grav_url; ?>">
                    </div>
                    <div class="layui-card-body" style="position: relative; padding-bottom: 1px;">
                        学号: <?php echo htmlentities($user, ENT_QUOTES, "UTF-8") ?><br>
                        姓名: <?php echo htmlentities($nick, ENT_QUOTES, "UTF-8") ?><br>
                        班级: <?php echo htmlentities($school, ENT_QUOTES, "UTF-8") ?><br>
                        邮箱: <?php echo htmlentities($email, ENT_QUOTES, "UTF-8") ?>
                    </div>
                    <div class="layui-card-body" style="padding-top: 1px;">
                        <table class="layui-table" lay-even lay-skin="nob">
                            <tbody style="font-size: 14px; font-weight: normal">

                            <tr>
                                <th>距离上次登陆:</th>
                                <th><?php echo format_date($last_time) ?></th>
                            </tr>
                            <tr>
                                <th>累计登陆天数:</th>
                                <th><?php echo $days ?></th>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php

                $sql = "SELECT * FROM `acmer` WHERE `user_id`=? AND `status`=0 ORDER BY weight desc, join_time";
                $res = pdo_query($sql, $user);


                $sql_achieve = "SELECT achieve.year, achieve.content, achieve.award FROM `achieve` WHERE user_id=? AND `status`=1 ORDER BY time DESC";
                $result_achieve = pdo_query( $sql_achieve, $user );
                if ( $result_achieve || count($res) != 0) {

                    echo "<div class='layui-panel' style='margin-top: 16px;'>"
                        ."<div class='layui-card-header'>个人成就";
                    echo "</div>"
                        ."<div class='layui-card-body'>"
                        ."<table class='layui-table'  lay-skin='nob'>"
                        ."<tbody>";
                    foreach ($res as $i)
                        echo "<span class='layui-badge' style='margin-left:1px; margin-right:1px; background-color: " . htmlentities($i['color'], ENT_QUOTES, "UTF-8") . ";'>" . htmlentities($i['value'], ENT_QUOTES, "UTF-8") . "</span>";

                        foreach ( $result_achieve as $row ) {
                            echo "<tr>"
                                ."<th>"."[".htmlentities($row['year'], ENT_QUOTES, "UTF-8")."]&nbsp&nbsp".htmlentities($row['content'], ENT_QUOTES, "UTF-8")."</th>"
                                ."<th>".htmlentities($row['award'], ENT_QUOTES, "UTF-8")."</th>"
                                ."</tr>";
                        }
                    echo "</tbody>"
                        ."</table>"
                        ."</div>"
                        ."</div>";
                }
                ?>
                <div class="layui-card" style="margin-top: 16px;">
                    <div class="layui-card-header">提交信息</div>
                    <div class="layui-card-body">
                        <table class="layui-table" lay-even lay-skin="nob">
                            <tbody style="font-size: 14px; font-weight: normal">
                            <tr>
                                <th><?php echo $MSG_Number?>:</th>
                                <th><?php echo $Rank?></th>
                            </tr>
                            <tr>
                                <th><?php echo $MSG_SOVLED ?>数:</th>
                                <th><a href='status.php?user_id=<?php echo $user ?>&jresult=4'><?php echo $AC ?></a></th>
                            </tr>
                            <tr>
                                <th><?php echo $MSG_SUBMIT ?>数:</th>
                                <th><a href='status.php?user_id=<?php echo $user ?>'><?php echo $Submit ?></a></th>
                            </tr>
                            <tr>
                                <th>重复率:</th>
                                <th><?php echo $chongFu ?></th>
                            </tr>
                            </tbody>
                        </table>
                        <div id="main1">
                            <script src="./template/sta_sty/js/echarts.min.js"></script>
                            <script>
                                var chartDom = document.getElementById('main1');
                                var myChart = echarts.init(chartDom);
                                var option;

                                option = {
                                    tooltip: {
                                        trigger: 'item'
                                    },
                                    legend: {
                                        top: '1%',
                                        left: 'center'
                                    },
                                    series: [
                                        {
                                            name: '做题统计',
                                            type: 'pie',
                                            radius: ['40%', '70%'],
                                            avoidLabelOverlap: false,
                                            label: {
                                                show: false,
                                                position: 'center'
                                            },
                                            emphasis: {
                                                label: {
                                                    show: true,
                                                    fontSize: '18',
                                                    fontWeight: 'bold'
                                                }
                                            },
                                            labelLine: {
                                                show: false
                                            },
                                            data: [
                                                <?php foreach ($view_userstat as $row) {
                                                $jlab = isset($jresult[$row[0]]) ? $jresult[$row[0]] : ("结果".$row[0]);
                                                echo "{ name:'" . $jlab . "', value:" . $row[1] . "},";
                                            }?>
                                            ]
                                        }
                                    ]
                                };
                                option && myChart.setOption(option);
                            </script>
                        </div>
                    </div>
                </div>
            </div>
            <div class="layui-col-md8">
                <div class="layui-card">
                    <div class="layui-card-header">练习信息</div>
                    <div class="layui-card-body">
                        <button type="button" class="layui-btn layui-btn-primary" id="selmain2"
                                style="position: absolute; z-index: 10; margin: 30px;">
                            <i class="layui-icon layui-icon-date"></i>
                        </button>
                        <div id="main2">
                            <script>
                                var chartDom = document.getElementById('main2');
                                var myChart = echarts.init(chartDom);
                                var option;
                                var d1 = [];
                                let da;
                                var now = layui.util.toDateString(Date(), 'yyyy');
                                <?php foreach($chart_data_ac as $k=> $d){?>
                                da = layui.util.toDateString(<?php echo $k ?>, 'yyyy-MM-dd');
                                d1.push([da, <?php echo $d ?>]);
                                <?php }?>
                                option = {
                                    title: {
                                        top: 30,
                                        left: 'center',
                                        text: '通过题目数量'
                                    },
                                    tooltip: {},
                                    visualMap: {
                                        min: 0,
                                        max: 20,
                                        type: 'piecewise',
                                        orient: 'horizontal',
                                        left: 'center',
                                        top: 65,
                                        color: ['#216E39', '#30A14E', '#40C463', '#91DA9E']
                                    },
                                    calendar: {
                                        top: 120,
                                        left: 30,
                                        right: 30,
                                        cellSize: ['auto', 13],
                                        range: now,
                                        itemStyle: {
                                            borderWidth: 0.5
                                        },
                                        yearLabel: {show: false}
                                    },
                                    series: {
                                        type: 'heatmap',
                                        coordinateSystem: 'calendar',
                                        data: d1
                                    }
                                };

                                option && myChart.setOption(option);
                                layui.use('laydate', function () {
                                    var laydate = layui.laydate;
                                    laydate.render({
                                        elem: '#selmain2',
                                        btns: [],
                                        type: 'year',
                                        min: '2018-1-1',
                                        max: now,
                                        done: function (value, date, endDate) {
                                            option.calendar.range = value;
                                            option && myChart.setOption(option);
                                        }
                                    });
                                });
                            </script>
                        </div>
                    </div>
                </div>
                <div class="layui-card" style="margin-top: 16px;">
                    <div class="layui-card-header">尝试过的题目</div>
                    <div class="layui-card-body">
                        <div class="layui-btn-container">
                            <script language='javascript'>
                                function p(id, c) {
                                    document.write("<span class='oj-solved-item'><a href='problem.php?id=" + id + "'>" + id + "</a></span>");
                                }

                                var str = "<div style='color:black; font-size:16px; margin:8px;'>暂时没有未完成的题目，快去尝试新的题目吧!</div>";
                                <?php $sql = "SELECT * FROM (SELECT DISTINCT `problem_id` AS p1 FROM solution WHERE `user_id`=?) AS t1 LEFT JOIN (SELECT DISTINCT `problem_id` AS p2 FROM solution WHERE `user_id`=? AND result=4) AS t2 ON p1=p2  WHERE p2 IS NULL GROUP BY `p1` ORDER BY `p1` ASC";
                                $num = 0;
                                if ($result = pdo_query($sql, $user, $user)) {
                                    foreach ($result as $row) {
                                        echo "p($row[0],$row[1]);";
                                        $num += 1;
                                    }
                                }
                                if ($num == 0)
                                    echo "document.write(str)";
                                ?>
                            </script>
                        </div>
                    </div>
                </div>
                <div class="layui-card" style="margin-top: 16px;">
                    <div class="layui-card-header">已通过的题目<a href="export_ac_code.php">
                            <button style="float:right; margin: 6px;"
                                    class="layui-btn layui-btn-primary layui-border-blue layui-btn-sm"><i
                                        class="layui-icon layui-icon-export"></i>下载所有AC代码
                            </button>
                        </a></div>
                    <div class="layui-card-body">
                        <div class="layui-btn-container">
                            <script language='javascript'>
                                function p(id, c) {
                                    document.write("<span class='oj-solved-item'><a href='problem.php?id=" + id + "'>" + id + "</a><a aria-label='查看题目 " + id + " 的提交记录' href='status.php?user_id=<?php echo urlencode($user)?>&problem_id=" + id + "'>" + c + " 次</a></span>");
                                }
                                <?php $sql = "SELECT `problem_id`,count(1) from solution where `user_id`=? and result=4 group by `problem_id` ORDER BY `problem_id` ASC";
                                if ($result = pdo_query($sql, $user)) {
                                    foreach ($result as $row)

//                                        echo "p(1000,1);";
                                        echo "p($row[0],$row[1]);";
                                }

                                ?>
                            </script>
                        </div>
                    </div>
                </div>
            </div>
            <div class="layui-col-md12">
                <div style="float:right">Made By <a href="userinfo.php?user=201803140220">201803140220</a></div>
            </div>
        </div>
    </div>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
