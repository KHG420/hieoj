<?php
require("admin-header.php");
require_once("../include/set_get_key.php");

if(!isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}

if(isset($OJ_LANG)){
    require_once("../lang/$OJ_LANG.php");
}
?>

<title>Register User List</title>


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
        <script>
            function showFrom(){
                document.getElementById('form1').style.display = "";
                document.getElementById('form1').style.bottom = "500px";
            }
            function hindForm(){
                document.getElementById('form1').style.display = "none";
                document.getElementById('form1').style.bottom = "";
            }
        </script>
        <center><h3>用户注册申请列表</h3></center>
        <form class="layui-form layui-form-pane" style="margin-left: 30px; display: inline-block;" action="user_register_access.php?num=1" method="post">
            <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
            <button class="layui-btn" lay-submit>一键通过</button>使用上传过的名单自动审核（新学年请先更新数据库班级信息）
        </form>
        <form class="layui-form layui-form-pane" style="margin-left: 30px; display: inline-block;" action="user_register_access.php?num=2" method="post">
            <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
            <button class="layui-btn" lay-submit>一键通过-2</button>通过爬取易班学生信息进行对比
        </form>
        <section class="content">
            <div class="container-fluid">
                <?php
                $sql = "SELECT COUNT(*) as ids FROM `users` WHERE `register_num`=0 OR `register_num`=1";
                $result = pdo_query($sql);
                $row = $result[0];

                $ids = intval($row['ids']);

                $idsperpage = 25;
                $pages = intval(ceil($ids/$idsperpage));

                if(isset($_GET['page'])){ $page = intval($_GET['page']);}
                else{ $page = 1;}

                $pagesperframe = 5;
                $frame = intval(ceil($page/$pagesperframe));

                $spage = ($frame-1)*$pagesperframe+1;
                $epage = min($spage+$pagesperframe-1, $pages);

                $sid = ($page-1)*$idsperpage;

                $sql = "";
                if(isset($_GET['keyword']) && $_GET['keyword']!=""){
                    $keyword = $_GET['keyword'];
                    $keyword = "%$keyword%";
                    $sql = "SELECT `user_id`,`nick`,`reg_time`,`ip`,`school`,`defunct` FROM `users` WHERE (user_id LIKE ?) OR (nick LIKE ?) OR (school LIKE ?) ORDER BY `user_id` DESC";
                    $result = pdo_query($sql,$keyword,$keyword,$keyword);
                }else{
//                    $sql = "SELECT user_id,nick,school,xueYuan,qq,phone,email,ip,reg_time,accesstime,register_num FROM `users` WHERE `register_num`=0 OR `register_num`=1 ORDER BY `reg_time` DESC LIMIT $sid, $idsperpage";
                    $sql = "SELECT user_id,nick,school,xueYuan,qq,phone,email,ip,reg_time,accesstime,register_num FROM `users` WHERE `register_num`=0 OR `register_num`=1 ORDER BY `reg_time` DESC ";
                    $result = pdo_query($sql);
                }
                ?>

                <!--    <form action=user_list.php class=center>-->
                <!--        <input name=keyword><input type=submit value="--><?php //echo $MSG_SEARCH?><!--" >-->
                <!--    </form>-->
                <br>
                <center>
                    <table lay-filter="demo">
                        <thead>
                        <tr>
                            <th lay-data="{field:'1', width:'8%', align: 'center'}">学号</th>
                            <th lay-data="{field:'2', align: 'center'}">姓名</th>
                            <th lay-data="{field:'3', width:'10%', align: 'center'}">专业班级</th>
                            <th lay-data="{field:'4', width:'12%', align: 'center'}">学院</th>
                            <th lay-data="{field:'5', align: 'center'}">qq</th>
                            <th lay-data="{field:'6', align: 'center'}">手机号</th>
                            <th lay-data="{field:'7', width:'12%', align: 'center'}">邮箱</th>
                            <th lay-data="{field:'8', align: 'center'}">ip</th>
                            <th lay-data="{field:'9', width:'10%', align: 'center'}">提交时间</th>
                            <th lay-data="{field:'10', width:'10%', align: 'center'}">通过时间</th>
                            <th lay-data="{field:'11', width:'10%', align: 'center'}">状态</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach($result as $row){
                            echo "<tr>";
                            if ($row['register_num']==1)
                                echo "<td><a href='../userinfo.php?user=".$row['user_id']."'>".$row['user_id']."</a></td>";
                            else
                                echo "<td>".$row['user_id']."</td>";
                            echo "<td>".$row['nick']."</td>";
                            echo "<td>".$row['school']."</td>";
                            echo "<td>".$row['xueYuan']."</td>";
                            echo "<td>".$row['qq']."</td>";
                            echo "<td>".$row['phone']."</td>";
                            echo "<td>".$row['email']."</td>";
                            echo "<td>".$row['ip']."</td>";
                            echo "<td>".$row['reg_time']."</td>";
                            echo "<td>".$row['accesstime']."</td>";
                            if ($row['register_num']==0) {
                                echo "<td><a href=user_register_change.php?cid=" . $row['user_id'] . "&getkey=" . $_SESSION[$OJ_NAME . '_' . 'getkey'] . "&num=1"."><span class=green>通过</span></a>";
                                echo "<a style='margin-left: 10px;' href=user_register_change.php?cid=" . $row['user_id'] . "&getkey=" . $_SESSION[$OJ_NAME . '_' . 'getkey'] . "&num=0"."><span class=red>不通过</span></a></td>";
                            }
                            else
                                echo "<td style='color: green'>已通过</td>";
                            echo "</tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </center>
                <script src="../template/sta_sty/layui/layui.js"></script>
                <script>
                    var table = layui.table;

                    //转换静态表格
                    table.init('demo', {
                        height: 'full', //设置高度
                        limit: 50, //注意：请务必确保 limit 参数（默认：10）是与你服务端限定的数据条数一致
                        //支持所有基础参数
                        page: true,
                        sort: true,
                    });

                </script>
            </div>
        </section>
    </div>
</div>
<script>
    document.getElementById('menu2').classList.remove("menu");
    document.getElementById('menu2').classList.add("menu-open");
    document.getElementById('a9').classList.remove("bg-primary");
    document.getElementById('a9').classList.add("bg-secondary");
</script>
</body>


