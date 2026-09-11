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
            <h2>忘记密码</h2>
            <form class="layui-form layui-form-pane" action="forgetpassword.php" method="post">
                <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
                <div class="layui-form-item">
                    <label class="layui-form-label">学号</label>
                    <div class="layui-input-block">
                        <input type="text" name="user_id" maxlength="12" minlength="12" required  lay-verify="required|number" placeholder="请输入学号" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-inline">
                        <label class="layui-form-label">邮箱</label>
                        <div class="layui-input-inline">
                            <input type="email" name="email" id="email" required  lay-verify="required|email" placeholder="请输入账号绑定的邮箱" autocomplete="off" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">验证码</label>
                        <div class="layui-input-inline">
                            <input type="text" name="code" required  lay-verify="required" placeholder="请输入验证码" autocomplete="off" class="layui-input">
                        </div>
                    </div>
                    <button class="layui-btn" style="background-color: #449D44;" id="sendmail" type="button">发送验证码</button>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">密码</label>
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
            <div class="layui-col-md12">
                <div><div><?php include("template/$OJ_TEMPLATE/footer.php");?>
                    </div>
            <script>
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
