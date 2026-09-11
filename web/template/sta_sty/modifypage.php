<?php require_once('./include/set_post_key.php');?>
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
    <script src="<?php echo $OJ_CDN_URL?>/include/jquery-latest.js"></script>
    <script src="./template/sta_sty/layui/layui.js"></script>
    <title>
        <?php echo $OJ_NAME ?>
    </title>
</head>
<body>
<!--<script type="text/javascript">-->
<!--    //1.创建一个二维数组用于存储班级-->
<!--    var classes = {};-->
<!--    var xu = {};-->
<!--    --><?php
////        foreach ($xueYuan as $x){
////            echo "classes['".$x[1]."'] = [];";
////            foreach ($classes[$x[1]] as $c) {
////                echo "classes['".$x[1]."'].push('".$c."');";
////            }
////        }
////        foreach ($xueYuan as $x) {
////            echo "xu['".$x[0]."'] = '".$x[1]."';";
////        }
////        ?>
<!--//-->
<!--//-->
<!--//    // for (var i = 0; i < classes['03'].length; i ++ ) {-->
<!--//    //     console.log(classes['03'][i]);-->
<!--//    // }-->
<!--//    function changeClass(val){-->
<!--//        console.log(val);-->
<!--//        let num = xu[val];-->
<!--//-->
<!--//        //7.获取第二个下拉列表-->
<!--//        var classEle = document.getElementById("class");-->
<!--//-->
<!--//        //9.清空第二个下拉列表的option内容-->
<!--//        classEle.options.length=0;-->
<!--//-->
<!--//        for(var j = 0; j < classes[num].length; j ++ ) {-->
<!--//            var textNode = document.createTextNode(classes[num][j]);-->
<!--//            //5.创建option元素节点-->
<!--//            var opEle = document.createElement("option");-->
<!--//            //6.将班级的文本节点添加到option元素节点-->
<!--//            opEle.appendChild(textNode);-->
<!--//            //8.将option元素节点添加到第二个下拉列表中去-->
<!--//            classEle.appendChild(opEle);-->
<!--//        }-->
<!--//    }-->
<!--//    changeClass("计算机与通信学院");-->
<!--</script>-->

<div class="container">
    <?php include("template/$OJ_TEMPLATE/header.php"); ?>
</div>
<div class="layui-container">
    <div class="layui-row">
        <div class="layui-col-md9" style="position: absolute; margin-left: auto; margin-right: auto; top: 0; left: 0; right: 0; bottom: 0;">
            <h3>注册信息</h3>
            <table class="layui-table" lay-even lay-skin="">
                <tbody style="font-size: 14px; font-weight: normal">
                    <tr>
                        <th>学号：</th>
                        <th><?php echo $row['user_id'] ?></th>
                    </tr>
                    <tr>
                        <th>姓名:</th>
                        <th><?php echo $row['nick'] ?></th>
                    </tr>
                    <tr>
                        <th>学院:</th>
                        <th><?php echo $row['xueYuan'] ?></th>
                    </tr>
                    <tr>
                        <th>专业班级:</th>
                        <th><?php echo $row['school'] ?></th>
                    </tr>
                    <tr>
                        <th>邮箱:</th>
                        <th><?php echo $row['email'] ?></th>
                    </tr>
                    <tr>
                        <th>手机号:</th>
                        <th><?php echo $row['phone'] ?></th>
                    </tr>
                    <tr>
                        <th>qq:</th>
                        <th><?php echo $row['qq'] ?></th>
                    </tr>
                    <tr>
                        <th>注册时间:</th>
                        <th><?php echo date("Y-m-d", $row['time']) ?></th>
                    </tr>
                </tbody>
            </table>

            <h3  style="margin-top: 40px;">修改信息</h3>
            <div class="layui-tab layui-tab-brief" lay-filter="docDemoTabBrief">
                <ul class="layui-tab-title">
                    <li class="layui-this">学院班级</li>
                    <li>姓名学号</li>
                    <li>邮箱</li>
                    <li>密码</li>
                    <li>其他</li>
                </ul>
                <div class="layui-tab-content">
                    <div class="layui-tab-item layui-show">
                        <form class="layui-form" action="modify2.php" method="post">
                            <div class="layui-form-item">
                                <label class="layui-form-label">学院</label>
                                <div class="layui-input-block">
                                    <select name="xueYuan" lay-verify="required" lay-filter="xy-dropdown">
                                        <option value="">请选择学院</option>
                                        <?php
                                        foreach ($xueYuan as $rows) {
                                            echo '<option value="' . $rows[0] . '">' . $rows[0] . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">年级</label>
                                <div class="layui-input-block">
                                    <select name="nj" lay-verify="required" lay-filter="nj-dropdown">
                                        <option value="">请选择年级</option>
                                        <?php
                                        for ($nnj = $n_nj + 1; $nnj <= $n_nj + 4; $nnj ++) {
                                            echo '<option value="20' . $nnj . '">20' . $nnj . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">班级</label>
                                <div class="layui-input-block" id="classList">
                                    <select name="school" lay-verify="required" lay-filter="class-dropdown">
                                        <option value="">请选择班级</option>
                                    </select>
                                </div>
                            </div>
                            <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
                            <input name="type" value="class" type="hidden">
                            <div class="layui-form-item layui-form-text">
                                <label class="layui-form-label">理由</label>
                                <div class="layui-input-block">
                                    <textarea name="reason" lay-verify="required" maxlength="100" placeholder="请输入内容（如转专业，信息错误等）内容需小于50字" class="layui-textarea"></textarea>
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <div class="layui-input-block">
                                    <button class="layui-btn" lay-submit style="background-color: #449D44;">立即提交</button>
                                    <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="layui-tab-item">
                        <form class="layui-form" action="modify2.php" method="post">
                            <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
                            <div class="layui-form-item">
                                <label class="layui-form-label">学号</label>
                                <div class="layui-input-block">
                                    <input type="text" value="<?php echo $row['user_id']?>" name="user_id" maxlength="12" minlength="12" required  lay-verify="required|number" placeholder="请输入学号" autocomplete="off" class="layui-input">
                                </div>
                            </div>
                            <input name="type" value="name" type="hidden">
                            <div class="layui-form-item">
                                <label class="layui-form-label">姓名</label>
                                <div class="layui-input-block">
                                    <input type="text" value="<?php echo $row['nick']?>" name="nick" required  lay-verify="required" placeholder="请输入姓名" autocomplete="off" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-form-item layui-form-text">
                                <label class="layui-form-label">理由</label>
                                <div class="layui-input-block">
                                    <textarea name="reason" lay-verify="required" maxlength="50" placeholder="请输入内容（如转专业，信息错误等）内容需小于50字" class="layui-textarea"></textarea>
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <div class="layui-input-block">
                                    <button class="layui-btn" lay-submit style="background-color: #449D44;">立即提交</button>
                                    <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="layui-tab-item">
                        <form class="layui-form" action="modify2.php" method="post">
                            <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
                            <input name="type" value="email" type="hidden">
                            <div class="layui-form-item">
                                <label class="layui-form-label">新邮箱</label>
                                <div class="layui-input-block">
                                    <input type="email" name="email" id="email" required  lay-verify="required|email" placeholder="请输入新邮箱" autocomplete="off" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">验证码</label>
                                <div class="layui-input-inline">
                                    <input type="text" name="code" required  lay-verify="required" placeholder="请输入验证码" autocomplete="off" class="layui-input">
                                </div>
                                <button class="layui-btn" style="background-color: #449D44;" id="sendmail" type="button">发送验证码</button>
                            </div>
                            <div class="layui-form-item">
                                <div class="layui-input-block">
                                    <button class="layui-btn" lay-submit style="background-color: #449D44;">立即提交</button>
                                    <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="layui-tab-item">
                        <form class="layui-form" action="modify.php" method="post">
                            <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
                            <div class="layui-form-item">
                                <label class="layui-form-label">旧密码</label>
                                <div class="layui-input-block">
                                    <input type="password" name="opassword" required  lay-verify="required" placeholder="请输入旧密码" autocomplete="off" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">新密码</label>
                                <div class="layui-input-block">
                                    <input type="password" name="npassword" required  lay-verify="required" placeholder="请输入新密码" autocomplete="off" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">确认密码</label>
                                <div class="layui-input-block">
                                    <input type="password" name="rptpassword" required  lay-verify="required" placeholder="请再输入一遍新密码" autocomplete="off" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <div class="layui-input-block">
                                    <button class="layui-btn" lay-submit style="background-color: #449D44;">立即提交</button>
                                    <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="layui-tab-item">
                        <form class="layui-form" action="modify2.php" method="post">
                            <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
                            <input name="type" value="other" type="hidden">
                            <div class="layui-form-item">
                                <label class="layui-form-label">手机号</label>
                                <div class="layui-input-block">
                                    <input name="phone" type="tel" pattern="[1]+[3456789]+\d{9}" maxlength="11" value="<?php echo $row['phone']?>" required  lay-verify="required|phone" placeholder="请输入手机号" autocomplete="off" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">qq号</label>
                                <div class="layui-input-block">
                                    <input type="text" name="qq" pattern="[1-9][0-9]{4,14}" value="<?php echo $row['qq']?>" required  lay-verify="required" placeholder="请输入qq号" autocomplete="off" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <div class="layui-input-block">
                                    <button class="layui-btn" lay-submit style="background-color: #449D44;">立即提交</button>
                                    <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="layui-col-md12" style="margin-top: 200px;">
            </div>

            <script>
                let xy = "";
                let nj = "";

                function renderForm() {
                    layui.use(['form'], function () {
                        var form = layui.form;
                        form.render('select');
                    });
                }

                function get_class() {
                    $.ajax({
                        url: "/getClass.php",
                        data: {
                            nj: nj,
                            xy: xy,
                        },
                        success: function (resp) {
                            let data = resp;
                            $("#classList select").empty();
                            for (var i = 0; i < data.length; i ++) {
                                $("#classList select").append("<option value='" + data[i].value + "'>" + data[i].value + "</option>")
                            }
                            renderForm();
                        }
                    });
                }
                layui.use(['form'], function(){
                    var form = layui.form;
                    form.on('select(xy-dropdown)', function(data){
                        xy = data.value;
                        get_class();
                    })
                    form.on('select(nj-dropdown)', function(data){
                        nj = data.value;
                        get_class();
                    })
                })

                $("#sendmail").click(function () {
                    var email = $("#email").val();
                    console.log(email);
                    if(email.length < 7){
                        alert("邮箱不能为空！");
                    }else{
                        $.ajax({
                            url: "/sendemail.php",
                            data: {
                                email: email,
                            },
                            success: function(resp) {
                                if (resp === "-1") {
                                    alert("发送成功。");
                                }else {
                                    alert("请在" + resp + "秒后再次获取。");
                                }
                            }
                        });
                    }
                });
                //注意：选项卡 依赖 element 模块，否则无法进行功能性操作
                layui.use('element', function(){
                    var element = layui.element;

                    //…
                });
                //Demo
                layui.use('form', function(){
                    var form = layui.form;

                    //监听提交
                    form.on('submit(formDemo)', function(data){
                        layer.msg(JSON.stringify(data.field));
                        return false;
                    });
                });
            </script>
        </div>
    </div>
</div>
</body>
