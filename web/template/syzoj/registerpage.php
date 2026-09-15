<?php $show_title="注册 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>.oj-registration [hidden] { display: none !important; }</style>
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
                    foreach ($register_xueYuan as $row) {
                        echo '<option value="' . htmlentities($row[0],ENT_QUOTES,"UTF-8") . '">' . htmlentities($row[0],ENT_QUOTES,"UTF-8") . '</option>';
                    }
                    ?>
                  </select>
                </div>
                <?php if ($register_policy_error !== ''): ?>
                <div class="ui error message visible" role="alert">
                  <p><?php echo htmlentities($register_policy_error, ENT_QUOTES, "UTF-8"); ?></p>
                </div>
                <?php elseif (count($register_xueYuan) === 0): ?>
                <div class="ui warning message visible" role="alert">
                  <p>当前开放年级范围（<?php echo htmlentities($register_year_min . ' - ' . $register_year_max, ENT_QUOTES, "UTF-8"); ?>）内没有可注册的学院，请联系管理员。</p>
                </div>
                <?php endif; ?>
                <div class="field">
                  <label id="school_label" for="school_sel">专业班级*</label>
                  <select name="school" id="school_sel" required>
                    <option value="">请先选择学院</option>
                  </select>
                  <p id="school_status" class="ui message" role="status" hidden></p>
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
var classRequest;
function setClassStatus(text, isError){
    var el = $("#school_status");
    if(!text){
        el.text("").removeClass("error warning visible").prop("hidden", true);
        return;
    }
    // .ui.form 下的 error / warning 消息默认 display:none，必须带 visible 才会显示。
    el.text(text)
      .removeClass("error warning")
      .addClass(isError ? "error" : "warning")
      .addClass("visible")
      .prop("hidden", false);
}
// 始终清空旧选项并保留 value="" 的占位项：不会静默选中第一个班级，也不会残留学院切换前的旧班级。
function resetClassSelect(placeholder){
    var sel = $("#school_sel");
    sel.empty().append(new Option(placeholder, ""));
    sel.prop("disabled", false).prop("required", true);
    sel.val("");
}
function loadClasses(xy){
    if(classRequest) classRequest.abort();
    resetClassSelect("请先选择学院");
    setClassStatus("", false);
    if(!xy){ return; }
    var sel = $("#school_sel");
    resetClassSelect("加载中…");
    classRequest = $.ajax({
        url: "getClass.php",
        data: { xy: xy, registration: 1 },
        dataType: "json",
        success: function(data){
            classRequest = null;
            var values = [];
            if(data && data.length){
                for (var i=0;i<data.length;i++){
                    var v = data[i].value !== undefined ? data[i].value : data[i];
                    if(v) values.push(v);
                }
            }
            sel.prop("disabled", false).prop("required", true);
            if(values.length){
                sel.empty().append(new Option("请选择专业班级", ""));
                for (var j=0;j<values.length;j++){ sel.append(new Option(values[j], values[j])); }
                sel.val("");
            }else{
                // 范围内没有可注册班级：清空旧班级，保留必填占位，明确提示联系管理员，不提供手填兜底。
                resetClassSelect("暂无可注册班级");
                setClassStatus("该学院在当前开放年级范围内暂无可注册班级，请联系管理员。", true);
            }
        },
        error: function(xhr, status){
            if(status === "abort") return;
            classRequest = null;
            // 失败也必须清掉旧班级，避免把上一次的班级继续提交。
            resetClassSelect("班级加载失败");
            setClassStatus("班级加载失败，请重试或联系管理员。", true);
        }
    });
}
$("#xueyuan_sel").change(function(){ loadClasses($(this).val()); });
$(".oj-registration form").on("reset", function(){ setTimeout(function(){ loadClasses($("#xueyuan_sel").val()); }, 0); });
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
