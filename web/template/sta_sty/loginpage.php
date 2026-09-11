<!DOCTYPE html>
<html lang="en" style="height: 100%;">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="../../favicon.ico">
    <link rel="stylesheet" href="./template/sta_sty/css/loginpage.css">
    <link rel="stylesheet" href="./template/sta_sty/layui/css/layui.css">
    <script src="<?php echo $OJ_CDN_URL?>/include/jquery-latest.js"></script>
    <script src="./template/sta_sty/layui/layui.js"></script>
    <title>
        <?php echo $OJ_NAME ?>
    </title>
<!--    --><?php //require_once('./include/set_post_key.php');?>
</head>
<body style="height: 100%;">
<?php include("template/sta_sty/nav.php"); ?>
<script>
    $("#main_container").removeClass("ui");
    $("#main_container").removeClass("main");
    $("#main_container").removeClass("container");
    $("#marg").css("margin-top", "");
    $("body").css("margin", "");
    $("html").css("overflow", "");
</script>
<style>
    .layui-input-block {
        margin-left: 0;
    }
</style>

<!--
  This was created based on the Dribble shot by Deepak Yadav that you can find at https://goo.gl/XRALsw
  I'm @hk95 on GitHub. Feel free to message me anytime.
-->
<section class="user">
    <div class="user_options-container">
        <div class="user_options-text">
            <div class="user_options-unregistered">
                <h2 class="user_unregistered-title">没有账号?</h2>
                <p class="user_unregistered-text">从现在开始，你的大学生活将是如此的不同。巨大的脑洞，丰富的题库，全面的测试用例，全新的视野，这一切，都让你的 ACMer 生活更加精彩。</p>
                <button class="user_unregistered-signup" id="signup-button">注册</button>
            </div>

            <div class="user_options-registered">
                <h2 class="user_registered-title">拥有账号?</h2>
                <p class="user_registered-text">从现在开始，你的大学生活将是如此的不同。巨大的脑洞，丰富的题库，全面的测试用例，全新的视野，这一切，都让你的 ACMer 生活更加精彩。</p>
                <button class="user_registered-login" id="login-button">登录</button>
            </div>
        </div>

        <div class="user_options-forms" id="user_options-forms">
            <div class="user_forms-login">
                <h2 class="forms_title">登录</h2>
                <form class="forms_form" action="login.php" method="post" role="form"  onSubmit="return jsMd5();" >
                    <fieldset class="forms_fieldset">
                        <div class="forms_field">
                            <input name="user_id" type="text" placeholder="用户名（学号）" class="forms_field-input" required autofocus />
                        </div>
                        <div class="forms_field">
                            <input name="password" type="password" placeholder="密码" class="forms_field-input" required />
                        </div>
                    </fieldset>
                    <div class="forms_buttons">
                        <a href="lostpassword.php" ><button type="button" class="forms_buttons-forgot">忘记密码?</button></a>
                        <input type="submit" name="submit" value="Log In" class="forms_buttons-action">
                    </div>
                </form>
            </div>
            <div class="user_forms-signup">
                <h2 class="forms_title-register">注册</h2>
                <form class="forms_form" action="register.php" method="post">
                    <fieldset class="forms_fieldset">
                        <div class="forms_field">
                            <input type="text" name="user_id" maxlength="12" minlength="12" placeholder="学号" class="forms_field-input" required />
                        </div>
                        <div class="forms_field">
                            <input type="text" name="nick" placeholder="姓名" class="forms_field-input" required />
                        </div>
                        <div class="layui-form">
                            <div class="layui-form-item">
                                <div class="layui-input-block">
                                    <select name="xueYuan" lay-verify="required" lay-filter="xy-dropdown" style="width: 20%">
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
                                <div class="layui-input-block">
                                    <select name="nj" lay-verify="required" lay-filter="nj-dropdown" style="width: 20%">
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
                                <div class="layui-input-block" id="classList">
                                    <select name="school" lay-verify="required" lay-filter="class-dropdown" style="width: 20%">
                                        <option value="">请选择班级</option>
                                    </select>
                                </div>
                             </div>
                        </div>
                        <div class="forms_field">
                            <input type="tel" name="phone" pattern="[1]+[3456789]+\d{9}" maxlength="11" placeholder="手机号" class="forms_field-input" required />
                        </div>
                        <div class="forms_field">
                            <input type="text" name="qq" pattern="[1-9][0-9]{4,14}" maxlength="11" placeholder="QQ号" class="forms_field-input" required />
                        </div>
                        <div class="forms_field">
                            <input type="password" name="password" minlength="6" placeholder="密码" class="forms_field-input" required />
                        </div>
                        <div class="forms_field">
                            <input type="password" name="rptpassword" minlength="6" placeholder="确认密码" class="forms_field-input" required />
                        </div>
                        <div class="layui-form-item">
                            <div class="layui-inline">
                                <div class="layui-input-inline">
                                    <input type="email" name="email" id="email" required  lay-verify="required|email" placeholder="邮箱" autocomplete="off" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <div class="layui-input-inline">
                                    <input type="text" name="code" required minlength="6" maxlength="6"  lay-verify="required" placeholder="验证码" autocomplete="off" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <div class="layui-button-inline">
                                    <button class="forms_buttons-action-zym" id="sendmail" type="button">发送验证码</button>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                    <div class="forms_buttons">
                        <input type="submit" value="Sign up" class="forms_buttons-action">
                    </div>
                </form>
            </div>
        </div>
    </div>
    <footer style="position: absolute;bottom: 10px;width: 100%;text-align: center;">
        <p>© 2018-<?php echo date('Y') ?> HNIEACM 版权所有&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
            <a href="https://beian.miit.gov.cn/" style="text-decoration: none; color: #444444;"
               target="_blank"><?php echo $OJ_BEIAN; ?></a>
    </footer>
</section>
<script src="./template/sta_sty/js/loginpage.js"></script>
<script src="<?php echo $OJ_CDN_URL?>include/md5-min.js"></script>
<script>
    function jsMd5(){
        if($("input[name=password]").val()=="") return false;
        $("input[name=password]").val(hex_md5($("input[name=password]").val()));
        return true;
    }
</script>
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
</body>