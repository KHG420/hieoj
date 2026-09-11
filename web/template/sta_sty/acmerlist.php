<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>
        <?php echo "测试 - $OJ_NAME" ?>
    </title>
    <meta name="renderer" content="webkit">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- 注意：项目正式环境请勿引用该地址 -->
    <link rel="stylesheet" href="./template/sta_sty/layui/css/layui.css">

</head>
<!-- 注意：项目正式环境请勿引用该地址 -->
<script src="./template/sta_sty/layui/layui.js"></script>
<body>

<div class="container">
    <?php include("template/$OJ_TEMPLATE/header.php"); ?>
</div>

<div class="layui-container">
    <div class="layui-row">

        <form class="layui-form layui-form-pane" action="ranklist.php" method="get" style="margin-top: 15px; margin-bottom: 15px;">
            <div class="layui-form-item">
                <div class="layui-input-inline">
                    <select name="xy" id="xy" lay-filter="xy-dropdown">
                        <option value="">学院</option>
                        <?php
                        foreach ($xueYuan as $rows) {
                            if (strcmp($rows[0], $_GET['xy']) == 0)
                                echo '<option value="' . $rows[0] . '" selected>' . $rows[0] . '</option>';
                            else
                                echo '<option value="' . $rows[0] . '">' . $rows[0] . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="layui-input-inline">
                    <select name="nj" lay-filter="nj-dropdown">
                        <option value="">年级</option>
                        <?php
                        foreach ($nianJi as $rows) {
                            $nnj = "20".$rows;
                            if (strcmp($rows, $_GET['nj']) == 0)
                                echo '<option value="' . $rows . '" selected>' . $nnj . '</option>';
                            else
                                echo '<option value="' . $rows . '">' . $nnj . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="layui-input-inline" id="classList">
                    <select name="school" lay-filter="school-dropdown">
                        <option value="">专业班级</option>
                    </select>
                </div>
                <div class="layui-input-inline">
                    <input id="prefix-input" type="text" name="prefix" lay-verify="title" autocomplete="off" placeholder="姓名或学号" class="layui-input">
                </div>
<!--                <div class="layui-input-inline">-->
<!--                    <button id="find-submit" type="button" class="layui-btn" lay-submit>搜索</button>-->
<!--                </div>-->
            </div>
        </form>
        <script>

            function renderForm() {
                layui.use(['form'], function () {
                    var form = layui.form;
                    form.render('select');
                });
            }

            function get_class(nj, xy) {
                if (nj !== "") nj = "20" + nj;
                $.ajax({
                    url: "/getClass.php",
                    data: {
                        nj: nj,
                        xy: xy,
                    },
                    success: function (resp) {
                        let data = resp;
                        $("#classList select").empty();
                        $("#classList select").append("<option value=''>专业班级</option>")
                        for (var i = 0; i < data.length; i ++) {
                            if (school === data[i].value)
                                $("#classList select").append("<option value='" + data[i].value + "' selected>" + data[i].value + "</option>")
                            else
                                $("#classList select").append("<option value='" + data[i].value + "'>" + data[i].value + "</option>")
                        }
                        renderForm();
                    }
                });
            }
        </script>
        <div style="color: #B3B3B3; text-align: right; font-size: xx-small;">*数据会有30分钟延迟</div>
        <table id="ranklist" lay-filter="ranklist" style="margin-top: 0;"></table>
        <script>
            var table = layui.table;

            //所获得的 tableIns 即为当前容器的实例
            var tableIns = table.render({
                url: '/java/rankListResults'
                ,method: "get"
                ,where: {
                }
                ,elem: '#ranklist' //指定原始表格元素选择器（推荐id选择器）
                ,skin: "line"
                ,id: "ranklist"
                ,height: '' //容器高度
                ,cols: [[
                    {field: 'rank', title: '名次', width: '7%', align: 'center', templet: '<div>{{d.LAY_INDEX}}</div>'}
                    ,{field: 'userId', title: '学号', width: '20%', align: 'center', templet: '<div><a target="_blank" href="userinfo.php?user={{d.userId}}" class="layui-table-link">{{d.userId}}</a></div>'}
                    ,{field: 'school', title: '专业班级',  align: 'center'}
                    ,{field: 'nick', title: '姓名', width: '17%', align: 'center'}
                    ,{field: 'solved', title: '解决数', width: '10%', align: 'center', templet: '<div><a target="_blank" href="status.php?user_id={{d.userId}}&jresult=4" class="layui-table-link">{{d.solved}}</a></div>'}
                    ,{field: 'submit', title: '提交数', width: '10%', align: 'center', templet: '<div><a target="_blank" href="status.php?user_id={{d.userId}}" class="layui-table-link">{{d.submit}}</a></div>'}
                    ,{field: 'ratio', title: '比率', width: '10%', align: 'center', templet: '<div>{{d.ratio == null ? "0" : d.ratio}}%</div>'}
                    ,{field: 'sim', title: '重复率', width: '10%', align: 'center', templet: '<div><span style="color:{{parseFloat(d.sim) >= 50.0 ? \'red\' : \'\'}}">{{d.sim == null ? "0" : d.sim}}%<span></div>'}
                ]] //设置表头
                ,page: {
                    layout:['prev', 'page', 'next', 'skip', 'count', 'limit'],
                    limit: 50,
                    limits: [25, 50, 100, 200, 300, 500],
                    curr: 1 //重新从第 1 页开始
                }
                //,…… //更多参数参考右侧目录：基本参数选项
                ,done: function(res){
                    $("#query-msg").html(res.message);
                }
            });

            function reload_ranklist(xy, nj, school, prefix) {
                tableIns.reload({
                    where: { //设定异步数据接口的额外参数，任意设
                        xy: xy
                        ,nj: nj
                        ,school: school
                        ,prefix: prefix
                    }
                    ,page: {
                        curr: 1 //重新从第 1 页开始
                    }
                });
            }

            //注意：选项卡 依赖 element 模块，否则无法进行功能性操作
            layui.use('element', function(){
                var element = layui.element;

                //…
            });

            let xy = "", prefix = "", nj = "", school = "";
            layui.use(['form'], function(){
                var form = layui.form;

                form.on('select(xy-dropdown)', function(data){
                    xy = data.value;
                    school = "";
                    reload_ranklist(xy, nj, school, prefix);
                    get_class(nj, xy);
                });
                form.on('select(nj-dropdown)', function(data){
                    nj = data.value;
                    school = "";
                    reload_ranklist(xy, nj, school, prefix);
                    get_class(nj, xy);
                });
                form.on('select(school-dropdown)', function(data){
                    school = data.value;
                    reload_ranklist(xy, nj, school, prefix);
                });
            });

            let flag = 0;
            $("#prefix-input").on("input", function () {
                if (flag === 1) return ;
                flag = 1;
                setTimeout(
                    'prefix = $("#prefix-input").val().trim(); reload_ranklist(xy, nj, school, prefix); flag = 0;'
                    ,700);
            });
        </script>
    </div>
</div>
</body>
</html>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
