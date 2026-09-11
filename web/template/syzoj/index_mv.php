<?php $show_title="首页 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<link rel="stylesheet" href="./template/sta_sty/layui/css/layui.css">
<script src="./template/sta_sty/layui/layui.js"></script>
<div class="padding">
    <div class="ui three column grid">
        <div class="eleven wide column">
            <h4 class="ui top attached block header"><i class="ui info icon"></i>公告</h4>
           <div class="ui bottom attached segment">
                <table class="ui very basic table" lay-filter="demo-new">
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
                    var table = layui.table;

                    //转换静态表格
                    table.init('demo-new', {
                        height: 'full', //设置高度
                        limit: 3, //注意：请务必确保 limit 参数（默认：10）是与你服务端限定的数据条数一致
                        //支持所有基础参数
                        page: true,
                        skin: "line",
                        limits: [3, 10, 20],
                    });

                </script>
           </div>
            <h4 class="ui top attached block header">
                <div style="display: inline;"><i class="ui signal icon"></i>
                    <div class="ui simple dropdown item">
                        <span id="month"><?php echo $month ?></span>月排名<i class="dropdown icon"></i>
                        <div class="menu">
                            <?php
                                for ($i = 0; $i < 5; $i ++ ) {
                                    echo "<div class=\"item\" id='rank-".$i."'>".date('n', strtotime("-$i month"))."月</div>";
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </h4>
                <table id="demo" lay-filter="test"></table>
        </div>
        <style>
            .layui-form {
                margin: 0;
            }
            a {
                color: #4183c4;
            }
        </style>
        <script>
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
        </script>
        <div class="right floated five wide column">
            <h4 class="ui top attached block header"><i class="ui rss icon"></i> <?php echo $month ?>月提交榜 </h4>
            <div class="ui bottom attached segment">
                <table class="ui very basic center aligned table">
                    <thead>
                    <tr>
                        <th width="60%"><?php echo $MSG_TITLE;?></th>
                        <th width="40%">尝试数</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    //                        $sql_problems = "SELECT * FROM `problem` WHERE `defunct`='N' AND `problem_id` NOT IN(
                    //            SELECT `problem_id` FROM `contest_problem` WHERE `contest_id` IN(
                    //              SELECT `contest_id` FROM `contest` WHERE `end_time`> NOW() or `private`='1'))
                    //							ORDER BY `problem_id` DESC LIMIT 5";
                    //去除掉了私有比赛的题目
                    //                    $sql_problems="SELECT problem.title, COUNT(solution.problem_id) as submit FROM `problem`
                    //						LEFT JOIN solution ON problem.problem_id = solution.problem_id
                    //				WHERE `defunct`='N' AND TO_DAYS(solution.in_date)>=TO_DAYS('2022-6-20') AND problem.`problem_id` NOT IN(
                    //            SELECT contest_problem.`problem_id` FROM `contest_problem` WHERE `contest_id` IN(
                    //            SELECT `contest_id` FROM `contest` WHERE `end_time` > Now() AND `private`='1'))
                    //						GROUP BY solution.problem_id order by submit desc LIMIT 5";
                    // 未去除私有比赛
                    $sql_problems="SELECT problem.problem_id, problem.title, s.submit FROM `problem`
						RIGHT JOIN
						(SELECT problem_id,COUNT(problem_id) as submit FROM solution
				WHERE TO_DAYS(solution.in_date)>=TO_DAYS('".$today."')
				GROUP BY problem_id order by submit desc LIMIT 5 ) s
				ON problem.problem_id = s.problem_id
				WHERE problem.`defunct`='N'";
                    $result_problems = mysql_query_cache( $sql_problems );
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
            <h4 class="ui top attached block header"><i class="ui signal icon"></i>上月做题榜</h4>
            <div class="ui bottom attached segment">
                <table class="ui very basic center aligned table">
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
                    $sql_users = "SELECT users.`user_id`,`school`,`nick`,s.`solved`,t.`submit` FROM `users`
                                        inner join
                                        (select count(distinct problem_id) solved ,user_id from solution 
						where (TO_DAYS(in_date)>=TO_DAYS('".$firstday."') and TO_DAYS(in_date)<=TO_DAYS('".$lastday."'))and result=4 
						group by user_id order by solved desc limit 50) s 
					on users.user_id=s.user_id
                                        inner join
                                        (select count( problem_id) submit ,user_id from solution 
						where (TO_DAYS(in_date)>=TO_DAYS('".$firstday."') and TO_DAYS(in_date)<=TO_DAYS('".$lastday."')) 
						group by user_id order by submit desc ) t 
					on users.user_id=t.user_id
                                ORDER BY s.`solved` DESC,t.submit,reg_time  LIMIT  3";
                    $result_users = mysql_query_cache( $sql_users );
                    if ( $result_users ) {
                        $i = 1;
                        foreach ( $result_users as $row ) {
                            echo "<tr>"."<td>".$i++."</td>"."<td>"
                                ."<a href=\"userinfo.php?user=".$row["user_id"]."\">"
                                .$row["nick"]."</a></td>"
                                ."<td>".$row["solved"]."</td>"."</tr>";
                            //."<td>".$row["school"]."</td>"."</tr>";
                        }
                    }
                    ?>
                    </tbody>
                </table>
<!--                <form action="problem.php" method="get">-->
<!--                    <div class="ui search" style="width: 100%; ">-->
<!--                        <div class="ui left icon input" style="width: 100%; ">-->
<!--                            <input class="prompt" style="width: 100%; " type="text" placeholder="--><?php //echo $MSG_PROBLEM_ID ;?><!-- …" name="id">-->
<!--                            <i class="search icon"></i>-->
<!--                        </div>-->
<!--                        <div class="results" style="width: 100%; "></div>-->
<!--                    </div>-->
<!--                </form>-->
            </div>
            <h4 class="ui top attached block header"><i class="ui calendar icon"></i>近期比赛&作业</h4>
            <div class="ui bottom attached center aligned segment">
                <table class="ui very basic center aligned table">
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
</div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
