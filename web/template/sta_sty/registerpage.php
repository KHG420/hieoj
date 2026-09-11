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

<div class="container">
    <?php include("template/$OJ_TEMPLATE/header.php"); ?>
</div>
<div class="layui-container">
    <div class="layui-row">
        <div class="layui-col-md9" style="position: absolute; margin-left: auto; margin-right: auto; top: 0; left: 0; right: 0; bottom: 0;">
            <h2>注册用户</h2>
            <form class="layui-form layui-form-pane" action="register.php" method="post">
                <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
                <div class="layui-form-item">
                    <label class="layui-form-label">学号</label>
                    <div class="layui-input-block">
                        <input type="text" name="user_id" maxlength="12" minlength="12" required  lay-verify="required|number" placeholder="请输入学号" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">姓名</label>
                    <div class="layui-input-block">
                        <input type="text" name="nick" required  lay-verify="required" placeholder="请输入姓名" autocomplete="off" class="layui-input">
                    </div>
                </div>
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
                    <label class="layui-form-label">密码</label>
                    <div class="layui-input-block">
                        <input type="password" name="password" required minlength="6"  lay-verify="required" placeholder="请输入密码" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">确认密码</label>
                    <div class="layui-input-block">
                        <input type="password" name="rptpassword" required minlength="6"  lay-verify="required" placeholder="请再输入一遍密码" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-inline">
                        <label class="layui-form-label">邮箱</label>
                        <div class="layui-input-inline">
                            <input type="email" name="email" id="email" required  lay-verify="required|email" placeholder="请输入邮箱" autocomplete="off" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">验证码</label>
                        <div class="layui-input-inline">
                            <input type="text" name="code" required minlength="6" maxlength="6"  lay-verify="required" placeholder="请输入验证码" autocomplete="off" class="layui-input">
                        </div>
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
            <div class="layui-col-md12">
                <div><div><?php include("template/$OJ_TEMPLATE/footer.php");?>
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
                                    alert(resp);
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
