<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>
        <?php echo "队伍管理 - $OJ_NAME" ?>
    </title>
    <meta name="renderer" content="webkit">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../template/sta_sty/layui/css/layui.css">
</head>
<script src="../template/sta_sty/layui/layui.js"></script>
<?php
require("admin-header.php");
$_SESSION[$OJ_NAME.'_'.'postkey']=strtoupper(substr(MD5($_SESSION[$OJ_NAME.'_'.'user_id'].rand(0,9999999)),0,10));
$key = '<input type=hidden name="postkey" value="'.$_SESSION[$OJ_NAME.'_'.'postkey'].'">';
?>

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
<div class="layui-container">
    <div class="layui-row">

<!--        <div class="demoTable">-->
<!--            搜索ID：-->
<!--            <div class="layui-inline">-->
<!--                <input class="layui-input" name="id" id="demoReload" autocomplete="off">-->
<!--            </div>-->
<!--            <button class="layui-btn" data-type="reload">搜索</button>-->
<!--        </div>-->
        <div class="demoTable">
            <script>
                function showFrom(){
                    document.getElementById('form1').style.display = "";
                }
                function hindForm(){
                    document.getElementById('form1').style.display = "none";
                }
                function showModify(team_id, team_name, user_id1, user_id2, user_id3){
                    document.getElementById('form2').style.display = "";
                    document.getElementById('team_id').style.value = team_id;
                    document.getElementById('team_name').style.value = team_name;
                    document.getElementById('user_id1').style.value = user_id1;
                    document.getElementById('user_id2').style.value = user_id2;
                    document.getElementById('user_id3').style.value = user_id3;
                }
                function hindModify(){
                    document.getElementById('form2').style.display = "none";
                    document.getElementById('team_id').style.value = "";
                    document.getElementById('team_name').style.value = "";
                    document.getElementById('user_id1').style.value = "";
                    document.getElementById('user_id2').style.value = "";
                    document.getElementById('user_id3').style.value = "";
                }
            </script>
            <button onclick="showFrom()" class="btn btn-primary" style="margin: 10px auto">增加队伍</button>
        </div>
        <table lay-filter="demo">
            <thead>
            <tr>
                <th lay-data="{field:'team_id', width:50, align: 'center'}">ID</th>
                <th lay-data="{field:'team_name', width:200, align: 'center'}">队伍名称</th>
                <th lay-data="{field:'user_id1', width:150, align: 'center'}">成员1学号（队长）</th>
                <th lay-data="{field:'nick1', width:100, align: 'center'}">成员1姓名</th>
                <th lay-data="{field:'user_id2', width:130, align: 'center'}">成员2学号</th>
                <th lay-data="{field:'nick2', width:100, align: 'center'}">成员2姓名</th>
                <th lay-data="{field:'user_id3', width:130, align: 'center'}">成员3学号</th>
                <th lay-data="{field:'nick3', width:100, align: 'center'}">成员3姓名</th>
                <th lay-data="{field:'create_time',width: 160, align: 'center'}">创建时间</th>
                <th lay-data="{field:'option', fixed: 'right', width:130, align: 'center'}">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $sql = "SELECT team_id,team_name,user_id1,nick1,user_id2,nick2,user_id3,nick3,create_time FROM `team` ORDER BY create_time DESC";
            $result = pdo_query($sql);
            foreach ($result as $row) {
                echo "<tr>";
                echo "<td>".$row['team_id']."</td>";
                echo "<td>".$row['team_name']."</td>";
                echo "<td><a href='/userinfo.php?user=".$row["user_id1"]."'>".$row['user_id1']."</a></td>";
                echo "<td>".$row['nick1']."</td>";
                echo "<td><a href='/userinfo.php?user=".$row["user_id2"]."'>".$row['user_id2']."</a></td>";
                echo "<td>".$row['nick2']."</td>";
                echo "<td><a href='/userinfo.php?user=".$row["user_id3"]."'>".$row['user_id3']."</a></td>";
                echo "<td>".$row['nick3']."</td>";
                echo "<td>".$row['create_time']."</td>";
            ?>
            <script>
                let team_id<?php echo trim($row['team_id'])?> = "<?php echo trim($row['team_id'])?>";
                let team_name<?php echo trim($row['team_id'])?> = "<?php echo trim($row['team_name'])?>";
                let user_id1<?php echo trim($row['team_id'])?> = "<?php echo $row['user_id1']?>";
                let user_id2<?php echo trim($row['team_id'])?> = "<?php echo $row['user_id2']?>";
                let user_id3<?php echo trim($row['team_id'])?> = "<?php echo $row['user_id3']?>";
                function show<?php echo trim($row['team_id'])?>() {
                    document.getElementById('form2').style.display = "";
                    document.getElementById('team_id').value = team_id<?php echo trim($row['team_id'])?>;
                    document.getElementById('team_name').value = team_name<?php echo trim($row['team_id'])?>;
                    document.getElementById('user_id1').value = user_id1<?php echo trim($row['team_id'])?>;
                    document.getElementById('user_id2').value = user_id2<?php echo trim($row['team_id'])?>;
                    document.getElementById('user_id3').value = user_id3<?php echo trim($row['team_id'])?>;
                }
            </script>
            <?php
                echo "<td>"
                    .'<span style="cursor:pointer;" onclick="show'.trim($row['team_id']).'()" class=green>编辑</span>'
                    ."&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp"
                    ."<a href=team_delete.php?cid=".$row['team_id']."&getkey=".$_SESSION[$OJ_NAME.'_'.'getkey']."><span class=red>删除</span></a>"
                    ."</td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    var table = layui.table;

    //转换静态表格
    table.init('demo', {
        height: 'full', //设置高度
        limit: 50, //注意：请务必确保 limit 参数（默认：10）是与你服务端限定的数据条数一致
        //支持所有基础参数
        page: true,
    });

</script>

    </div>
</div>

<div class="modal-dialog" style="display: none; width: 30%; position: absolute;top: 40%;left: 50%;transform: translate(-50%, -50%);" id="form1">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="modal-title text-center">添加队伍</h4>
            <i class="nav-icon fas fa-solid fa-x" style="color:red; cursor:pointer; font-size: 30px;" onclick="hindForm()">×</i>
        </div>
        <form action='team_add.php' method=post>
            <div class="modal-body" id = "model-body">
                <div class="form-group">
                    <input type=text name="team_name" class="form-control" placeholder="队伍名称" autocomplete="off">
                </div>
                <div class="form-group">
                    <input type=text name="user_id1" class="form-control" placeholder="成员1学号（队长）" autocomplete="off">
                </div>
                <div class="form-group">
                    <input type=text name="user_id2" class="form-control" placeholder="成员2学号" autocomplete="off">
                </div>
                <div class="form-group">
                    <input type=text name="user_id3" class="form-control" placeholder="成员3学号" autocomplete="off">
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
            <h4 class="modal-title text-center">编辑队伍</h4>
            <i class="nav-icon fas fa-solid fa-x" style="color:red; cursor:pointer; font-size: 30px;" onclick="hindModify()">×</i>
        </div>
        <form action='team_modify.php' method=post>
            <div class="modal-body" id = "model-body">
                <div class="form-group">
                    <input id="team_name" type=text name="team_name" class="form-control" placeholder="队伍名称" autocomplete="off">
                </div>
                <div class="form-group">
                    <input id="user_id1" type=text name="user_id1" class="form-control" placeholder="成员1学号（队长）" autocomplete="off">
                </div>
                <div class="form-group">
                    <input id="user_id2" type=text name="user_id2" class="form-control" placeholder="成员2学号" autocomplete="off">
                </div>
                <div class="form-group">
                    <input id="user_id3" type=text name="user_id3" class="form-control" placeholder="成员3学号" autocomplete="off">
                </div>
                <div class="modal-footer">
                    <div class="form-group">
                        <?php echo $key;?>
                        <input type='hidden' name='do' value='do'>
                        <input id="team_id" type='hidden' name='team_id' value=''>
                        <input type=submit value='修改' class="btn btn-primary form-control">
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    document.getElementById('menu4').classList.remove("menu");
    document.getElementById('menu4').classList.add("menu-open");
    document.getElementById('a24').classList.remove("bg-primary");
    document.getElementById('a24').classList.add("bg-secondary");
</script>
</body>
</html>
