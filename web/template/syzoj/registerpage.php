<?php $show_title="注册 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<div class="padding oj-registration">
  <h1>注册</h1>
  <div class="ui error message" id="error" data-am-alert hidden>
    <p id="error_info"></p>
  </div>
          <form action="register.php" method="post" role="form" class="ui form">
                <?php require_once('./include/set_post_key.php');?>
                <div class="field">
                    <label for="username">学号*</label>
                    <input id="username" autocomplete="username" name="user_id" class="form-control" placeholder="" type="text" maxlength="12" minlength="12" required>
                </div>
                <div class="field">
                    <label for="register-name">姓名*</label>
                    <input id="register-name" autocomplete="name" name="nick" placeholder="" type="text" required>
                </div>
                <div class="two fields">
                    <div class="field">
                    <label for="register-password">密码*</label>
                      <input id="register-password" autocomplete="new-password" name="password" placeholder="" type="password" minlength="6" required>
                    </div>
                    <div class="field">
                      <label for="register-confirm">确认密码*</label>
                      <input id="register-confirm" autocomplete="new-password" name="rptpassword" placeholder="" type="password" minlength="6" required>
                    </div>
                </div>
                <div class="field">
                  <label for="xueyuan_sel">学院*</label>
                  <select name="xueYuan" id="xueyuan_sel" required>
                    <option value="">请选择学院</option>
                    <?php
                    foreach ($xueYuan as $row) {
                        echo '<option value="' . htmlentities($row[0],ENT_QUOTES,"UTF-8") . '">' . htmlentities($row[0],ENT_QUOTES,"UTF-8") . '</option>';
                    }
                    ?>
                  </select>
                </div>
                <div class="field">
                  <label for="school_sel">专业班级*</label>
                  <select name="school" id="school_sel" required>
                    <option value="">请先选择学院</option>
                  </select>
                </div>
              <div class="field">
                  <label for="register-phone">手机号*</label>
                  <input id="register-phone" autocomplete="tel" name="phone" placeholder="" type="tel" pattern="[1]+[3456789]+\d{9}" maxlength="11" required>
              </div>
              <div class="field">
                  <label for="register-qq">QQ号码*</label>
                  <input id="register-qq" autocomplete="off" name="qq" placeholder="" type="text" pattern="[1-9][0-9]{4,14}" required>
              </div>
                <div class="three fields">
                    <div class="field">
                        <label for="email">邮箱*</label>
                        <input autocomplete="email" name="email" placeholder="" type="email" id="email" required>
                    </div>
                    <div class="field">
                        <label for="code">验证码*</label>
                        <input id="code" autocomplete="one-time-code" name="code" placeholder="" type="text" required>
                    </div>
                    <button type="button" class="ui button" id="sendmail">获取验证码</button>
                </div>
                <?php if($OJ_VCODE){?>
                  <div class="field">
                    <label for="vcode">图片验证码*</label>
                    <input id="vcode" required name="vcode" class="form-control" placeholder="" type="text">
                    <img alt="click to change" src="vcode.php" onclick="this.src='vcode.php?'+Math.random()" height="30px">
                  </div>
                <?php }?>
                <button name="submit" type="submit" class="ui primary button">注册</button>
                <button name="submit" type="reset" class="ui button">重置</button>
            </form>
</div>
<script>
function esc(v){ return $("<div>").text(v).html(); }
function loadClasses(xy){
    var sel = $("#school_sel");
    sel.empty();
    if(!xy){ sel.append("<option value=''>请先选择学院</option>"); return; }
    sel.append("<option value=''>加载中…</option>");
    $.ajax({
        url: "getClass.php",
        data: { xy: xy },
        dataType: "json",
        success: function(data){
            sel.empty();
            sel.append("<option value=''>请选择专业班级</option>");
            if(data && data.length){
                for (var i=0;i<data.length;i++){
                    var v = data[i].value !== undefined ? data[i].value : data[i];
                    if(!v) continue;
                    sel.append("<option value='"+esc(v)+"'>"+esc(v)+"</option>");
                }
            }
        },
        error: function(){
            sel.empty();
            sel.append("<option value=''>班级加载失败，请重选学院</option>");
        }
    });
}
$("#xueyuan_sel").change(function(){ loadClasses($(this).val()); });
$("#sendmail").click(function () {
    var email = $("#email").val();
    if(email.length < 7){
        alert("邮箱不能为空！");
    }else{
        $.ajax({
            url: "/sendemail.php",
            data: { email: email },
            success: function(resp) {
                if (resp === "-1") { alert("发送成功。"); } else { alert(resp); }
            }
        });
    }
});
</script>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
