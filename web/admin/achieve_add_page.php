<?php require_once("admin-header.php");?>
<?php
if(!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
$_SESSION[$OJ_NAME.'_'.'postkey']=strtoupper(substr(MD5($_SESSION[$OJ_NAME.'_'.'user_id'].rand(0,9999999)),0,10));
$key = '<input type=hidden name="postkey" value="'.$_SESSION[$OJ_NAME.'_'.'postkey'].'">';
?>
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
        <center><h3>用户成就列表</h3></center>
            <button class="layui-btn" style="margin-left: 30px; display: inline-block;" onclick="showFrom()" class="btn btn-primary">增加用户成就</button>
        <form class="layui-form layui-form-pane" style="margin-right: 40px; display: inline-block; float: right;" action="achieve_add_rank3.php" method="post">
            <?php echo $key; ?>
            <div class="layui-inline">
                <input type="text" class="layui-input" id="test1" placeholder="月份" name="month">
            </div>
            <button class="layui-btn" lay-submit>增加月前三成就</button>
        </form>
        <section class="content">
            <div class="container-fluid">
                <?php
                $sql = "SELECT id,acmer.user_id,join_time,`status`,`value`,color,weight,users.nick FROM `acmer` 
    LEFT JOIN users ON users.user_id=acmer.user_id ORDER BY join_time DESC";

                $result = pdo_query($sql);
                ?>
                <br>
                <center>
                    <script>
                        function showFrom(){
                            document.getElementById('form1').style.display = "";
                        }
                        function hindForm(){
                            document.getElementById('form1').style.display = "none";
                        }
                        function hindModify(){
                            document.getElementById('form2').style.display = "none";
                            document.getElementById('value').style.value = "";
                            document.getElementById('color').style.value = "";
                            document.getElementById('weight').style.value = "";
                        }
                    </script>
                    <table lay-filter="demo">
                        <thead>
                        <tr>
                            <th lay-data="{field:'1', width:'8%', align: 'center'}">序号</th>
                            <th lay-data="{field:'2', align: 'center'}">学号</th>
                            <th lay-data="{field:'3', width:'10%', align: 'center'}">姓名</th>
                            <th lay-data="{field:'4', width:'12%', align: 'center'}">内容</th>
                            <th lay-data="{field:'5', align: 'center'}">颜色</th>
                            <th lay-data="{field:'6', align: 'center'}">比重</th>
                            <th lay-data="{field:'7', width:'12%', align: 'center'}">创建时间</th>
                            <th lay-data="{field:'8', width:'10%', align: 'center'}">状态</th>
                            <th lay-data="{field:'9', width:'10%', align: 'center'}">操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach($result as $row){
                            echo "<tr>";
                            echo "<td>".$row['id']."</td>";
                            echo "<td><a href='/userinfo.php?user=".$row["user_id"]."'>".$row['user_id']."</a></td>";
                            echo "<td>".$row['nick']."</td>";
                            echo "<td>".$row['value']."</td>";
                            echo "<td>".$row['color']."</td>";
                            echo "<td>".$row['weight']."</td>";
                            echo "<td>".$row['join_time']."</td>";
                            if ($row['status']==0) {
                                echo "<td><a href=achieve_change.php?id=" . $row['id'] . "&getkey=" . $_SESSION[$OJ_NAME . '_' . 'getkey'] . "&num=0"."><span class=green>使用中</span></a>";
                            }
                            else
                                echo "<td><a href=achieve_change.php?id=" . $row['id'] . "&getkey=" . $_SESSION[$OJ_NAME . '_' . 'getkey'] . "&num=1"."><span class=red>停用中</span></td>";
                            ?>
                            <script>
                                function show<?php echo trim($row['id'])?>() {
                                    document.getElementById('form2').style.display = "";
                                    document.getElementById('id').value = "<?php echo trim($row['id'])?>";
                                    document.getElementById('value').value = "<?php echo trim($row['value'])?>";
                                    document.getElementById('color').value = "<?php echo trim($row['color'])?>";
                                    document.getElementById('weight').value = "<?php echo trim($row['weight'])?>";
                                }
                            </script>
                            <?php
                            echo "<td>"
                                .'<span style="cursor:pointer;" onclick="show'.trim($row['id']).'()" class=blue>编辑</span>'
                                ."</td>";
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
                    layui.use('laydate', function(){
                        var laydate = layui.laydate;

                        //执行一个laydate实例
                        laydate.render({
                            elem: '#test1' //指定元素
                            ,type: "month"
                            ,format: 'yyyy-MM'
                        });
                    });
                </script>
            </div>
        </section>
    </div>
</div>
<div class="modal-dialog" style="display: none; width: 30%; position: absolute;top: 40%;left: 50%;transform: translate(-50%, -50%);" id="form1">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="modal-title text-center">添加成就</h4>
            <i class="nav-icon fas fa-solid fa-x" style="color:red; cursor:pointer; font-size: 30px;" onclick="hindForm()">×</i>
        </div>
        <form action='achieve_add.php' method=post>
            <div class="modal-body" id = "model-body">
                <div class="form-group">
                    <input id="user_id1" type=text name="user_id" class="form-control" placeholder="学号" autocomplete="off">
                </div>
                <div class="form-group">
                    <input id="value1" type=text name="value" class="form-control" placeholder="内容" autocomplete="off">
                </div>
                <div class="form-group">
                    <input id="color1" type=text name="color" class="form-control" placeholder="颜色" autocomplete="off">
                </div>
                <div class="form-group">
                    <input id="weight1" type=text name="weight" class="form-control" placeholder="比重" autocomplete="off">
                </div>
                <div class="modal-footer">
                    <div class="form-group">
                        <?php echo $key;?>
                        <input type='hidden' name='do' value='do'>
                        <input type=submit value='添加' class="btn btn-primary form-control">
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal-dialog" style="display: none; width: 30%; position: absolute;top: 40%;left: 50%;transform: translate(-50%, -50%);" id="form2">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="modal-title text-center">编辑成就</h4>
            <i class="nav-icon fas fa-solid fa-x" style="color:red; cursor:pointer; font-size: 30px;" onclick="hindModify()">×</i>
        </div>
        <form action='achieve_modify.php' method=post>
            <div class="modal-body" id = "model-body">
                <div class="form-group">
                    <input id="value" type=text name="value" class="form-control" placeholder="内容" autocomplete="off">
                </div>
                <div class="form-group">
                    <input id="color" type=text name="color" class="form-control" placeholder="颜色" autocomplete="off">
                </div>
                <div class="form-group">
                    <input id="weight" type=text name="weight" class="form-control" placeholder="比重" autocomplete="off">
                </div>
                <div class="modal-footer">
                    <div class="form-group">
                        <?php echo $key;?>
                        <input type='hidden' name='do' value='do'>
                        <input id="id" type='hidden' name='id' value=''>
                        <input type=submit value='修改' class="btn btn-primary form-control">
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    document.getElementById('menu2').classList.remove("menu");
    document.getElementById('menu2').classList.add("menu-open");
    document.getElementById('a25').classList.remove("bg-primary");
    document.getElementById('a25').classList.add("bg-secondary");
</script>
</body>
