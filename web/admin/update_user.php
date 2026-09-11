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
        <center><h3>用户审核花名册</h3></center>
        <div class="layui-container">
            <div class="layui-row">
                <div class="layui-col-md9">
                    <button type="button" class="layui-btn" id="test1">
                        <i class="layui-icon">&#xe67c;</i>上传文件（还不可用）
                    </button>
                </div>
            </div>
        </div>
        <center>
            <table lay-filter="demo">
                <thead>
                <tr>
                    <th lay-data="{field:'1', align: 'center'}">学号</th>
                    <th lay-data="{field:'2', align: 'center'}">姓名</th>
                    <th lay-data="{field:'3', width:'10%', align: 'center'}">专业班级</th>
                    <th lay-data="{field:'4', align: 'center'}">状态</th>
                    <th lay-data="{field:'5', align: 'center'}">创建时间</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $sql = "SELECT user_id,nick,school,`status`,`time` FROM `audit_user` ORDER BY `status` DESC,user_id";
                $result = pdo_query($sql);
                foreach($result as $row){
                    echo "<tr>";
                    if ($row['status']==1)
                        echo "<td><a href='../userinfo.php?user=".$row['user_id']."'>".$row['user_id']."</a></td>";
                    else
                        echo "<td>".$row['user_id']."</td>";
                    echo "<td>".$row['nick']."</td>";
                    echo "<td>".$row['school']."</td>";
                    if ($row['status']==0) {
                        echo "<td><span class=red>未注册</span></a>";
                    }
                    else
                        echo "<td><span class=green>已注册</span></td>";
                    echo "<td>".$row['time']."</td>";
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

            layui.use('upload', function(){
                var upload = layui.upload;

                //执行实例
                var uploadInst = upload.render({
                    accept: 'file'
                    ,elem: '#test1' //绑定元素
                    ,url: '/admin/update_user_excel.php/' //上传接口
                    ,done: function(res){
                        //上传完毕回调
                    }
                    ,error: function(){
                        //请求异常回调
                    }
                });
            });
        </script>

    </div>
</div>
<script>
    document.getElementById('menu2').classList.remove("menu");
    document.getElementById('menu2').classList.add("menu-open");
    document.getElementById('a20').classList.remove("bg-primary");
    document.getElementById('a20').classList.add("bg-secondary");
</script>
</body>


