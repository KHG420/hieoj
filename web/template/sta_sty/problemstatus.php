<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="../../favicon.ico">
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
    <title>
        <?php echo $OJ_NAME?>
    </title>
</head>

<body>

<div class="container">
    <?php include("./template/sta_sty/nav.php");?>
</div>

<div style="background-color: #f2f2f2;">
    <div style="padding: 5px;">
        <div class="layui-row layui-col-space1">
            <div class="layui-col-md12">
                <div style="height: 5px;"></div>
            </div>
            <div class="layui-col-md4">
                <div class="layui-panel">
                    <div class="layui-card-header layui-font-20">推荐习题</div>
                    <div class="layui-card-body">
                        <div class="layui-btn-container">
                            <script language="javascript">
                                function p(id) {
                                    document.write("<a style='color:#FFFFFF;' href='problem.php?id=" + id + "'><button type='button' style='font-size:20px'  style='width:75px;'  class='layui-btn'>" + id + "</button></a>");
                                }
                                function blank() {
                                    document.write("<button type='button' style='width:75px;'  class='layui-btn'>" + 暂无推荐 + "</button>");
                                }

                                <?php
                                if (empty($view_recommand))
                                    echo "blank();";
                                else
                                foreach ($view_recommand as $row){
                                    echo "p(";
                                    echo $row[0];
                                    echo ");";
                                }?>
                            </script>

                        </div>
                    </div>
                </div>
                <div class="layui-panel" style="margin-top: 16px;">
                    <div class="layui-card-header layui-font-20">提交信息</div>
                    <div class="layui-card-body">

                        提交:<?php echo $total?><br>
                        用户(提交):<?php echo $total_users?><br>
                        用户(解决):<?php echo $acuser?><br>
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
                                            label:{
                                                normal: {
                                                    position: 'inner',  // 设置标签位置，默认在饼状图外 可选值：'outer' ¦ 'inner（饼状图上）'
                                                    // formatter: '{a} {b} : {c}个 ({d}%)'   设置标签显示内容 ，默认显示{b}
                                                    // {a}指series.name  {b}指series.data的name
                                                    // {c}指series.data的value  {d}%指这一部分占总数的百分比
                                                    formatter: '{d}%'
                                                }
                                            },
                                            data: [
                                                <?php foreach ($view_problem as $row){
                                                    echo "{name: '";
                                                    echo $row[0];
                                                    echo "', value:";
                                                    echo $row[1];
                                                    echo "},";
                                                }?>
                                                ]
                                        }
                                    ]
                                };
                                option && myChart.setOption(option);

                                //圆柱图上添加点击事件
                                var data = new Array();
                                <?php foreach ($view_problem as $row) {
                                    echo "data.push('";
                                    echo $row[2];
                                    echo "');";
                                }?>;
                                myChart.on("click", pieConsole);
                                function pieConsole(param) {
                                    //     获取data长度
                                    // alert(option.series[0].data.length);
                                    //      获取地N个data的值
                                    // 　　alert(option.series[0].data[i]);
                                    //     获取series中param.dataIndex事件对应的值
                                    // alert(param.dataIndex);
                                    window.location.href=data[param.dataIndex];
                                    // alert(option.series[param.seriesIndex].data[param.dataIndex].value);
                                    // alert(option.series[param.seriesIndex].data[param.dataIndex].name);
                                    // 　　clickFunc(param.dataIndex);//执行点击效果,触发相应js函数
                                    //param具体包含的方法见 https://blog.csdn.net/allenjay11/article/details/76033232
                                }
                            </script>
                        </div>
                    </div>
                </div>

            </div>
            <div class="layui-col-md8">
                <div class="layui-panel">
                    <div class="layui-card-header layui-font-20">已通过的用户</div>
                    <div class="layui-card-body">
                        <table class="layui-table">
                            <colgroup>
                            </colgroup>
                            <thead>
                            <tr>
                                <th>名次</th>
                                <th>RunID</th>
                                <th>用户</th>
                                <th>内存</th>
                                <th>耗时</th>
                                <th>语言</th>
                                <th>代码长度</th>
                                <th>提交时间</th>
                            </tr>
                            </thead>
                            <tbody>
                                <?php

                                foreach ($view_solution as $row){
                                    $i = 1;
                                    echo "<tr>";
                                        foreach ($row as $c){
                                            echo "<th>";
                                            if ($i == 3 || $i == 6)
                                                echo "<strong>";
                                            echo $c;
                                            if ($i == 3 || $i == 6)
                                                echo "</strong>";
                                            $i++;
                                            echo "</th>";
                                        }
                                    echo "</tr>";
                                }?>

                            </tbody>
                        </table>
                        <center>
                            <div class="layui-btn-group">
                                <?php
                                echo "<a href='problemstatus.php?id=$id'><button type='button' class='layui-btn'><i class='layui-icon layui-icon-prev layui-font-8'></i></button></a>";
                               // "<a href='problemstatus.php?id=$id'>[TOP]</a>";
                                echo "<a href='status.php?problem_id=$id'><button type='button' class='layui-btn layui-bg-orange'><i class='layui-icon layui-icon-log layui-font-8'></i></button></a>";
                                if ($page>$pagemin){
                                    $page--;
                                    echo "<a href='problemstatus.php?id=$id&page=$page'><button type='button' class='layui-btn layui-bg-blue'><i class='layui-icon layui-icon-left layui-font-8'></i></button></a>";
                                    $page++;
                                }
                                if ($page<$pagemax){
                                    $page++;
                                    echo "<a href='problemstatus.php?id=$id&page=$page'><button type='button' class='layui-btn layui-bg-red'><i class='layui-icon layui-icon-right layui-font-8'></i></button></a>";
                                    $page--;
                                }
                                ?>
                            </div>
                        </center>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</script>
</body>
</html>
