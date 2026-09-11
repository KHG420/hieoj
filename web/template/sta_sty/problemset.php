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

    <title>
        <?php echo $OJ_NAME?>
    </title>
</head>

<body>

<div class="container">
    <?php include("template/sta_sty/nav.php");?>
</div>

<table id="paging" lay-filter="test"></table>

<script type="javascript">
    layui.use('table', function(){
        var table = layui.table;

        table.render({
            elem: '#test'
            ,url:'/demo/table/user/'
            ,cols: [[
                {field:'id', width:80, title: 'ID', sort: true}
                ,{field:'username', width:80, title: '用户名'}
                ,{field:'sex', width:80, title: '性别', sort: true}
                ,{field:'city', width:80, title: '城市'}
                ,{field:'sign', title: '签名', minWidth: 150}
                ,{field:'experience', width:80, title: '积分', sort: true}
                ,{field:'score', width:80, title: '评分', sort: true}
                ,{field:'classify', width:80, title: '职业'}
                ,{field:'wealth', width:135, title: '财富', sort: true}
            ]]
            ,page: true
        });
    });
    layui.use('table', function(){
        var table = layui.table;
        //第一个实例
        table.render({
            elem: 'paging'
            ,limit:<?php echo($page_cnt) ?>//每页条数
            ,count:<?php echo($count) ?>//数据总数
            ,curr:<?php echo($page) ?>
            ,layout:['prev','page','next','count']
            ,page: true //开启分页
            ,jump:function(obj,first){
                //首次不执行
                if(!first){
                    <?php
                        if (isset($_GET['search'])&&trim($_GET['search'])!="") {
                            $search = $_GET['search'];
                            echo "location.href='/problemset.php?page='+obj.curr+'&search=$search';";
                        }else{
                            echo "location.href='/problemset.php?page='+obj.curr;";
                        }
                    ?>
                }
        });

    });
</script>
</body>

</html>
