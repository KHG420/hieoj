<?php $show_title="修改资料 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>
.oj-modify { max-width: 640px; margin: 0 auto; }
.oj-card { background: #fff; border: 1px solid #e6ebf2; border-radius: 10px; box-shadow: 0 4px 14px rgba(15,23,42,.06); padding: 24px; margin-bottom: 20px; }
.oj-card h2 { margin: 0 0 16px; font-size: 18px; color: #1f2d3d; border-bottom: 1px solid #e6ebf2; padding-bottom: 12px; }
.oj-field { margin-bottom: 14px; }
.oj-field label { display: block; font-size: 14px; font-weight: 600; color: #1f2d3d; margin-bottom: 6px; }
.oj-field input, .oj-field select { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #d7dee8; border-radius: 6px; font-size: 14px; background: #fff; }
.oj-btn { display: inline-block; padding: 9px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; }
.oj-btn-primary { background: #2f6ee5; color: #fff; }
.oj-btn-primary:hover { background: #1e56c6; }
.oj-hint { color: #5f6d85; font-size: 13px; margin-top: 8px; }
</style>
<div class="padding oj-modify">
  <div class="oj-card">
    <h2>基本信息（如需修改请提交申请，管理员审核后生效）</h2>
    <div class="oj-field">
      <label>用户名（学号）</label>
      <input type="text" disabled value="<?php echo htmlentities($_SESSION[$OJ_NAME.'_'.'user_id'],ENT_QUOTES,"UTF-8")?>">
    </div>
    <div class="oj-field">
      <label>姓名</label>
      <input type="text" disabled value="<?php echo htmlentities($row['nick'],ENT_QUOTES,"UTF-8")?>">
    </div>
    <div class="oj-field">
      <label>专业班级</label>
      <input type="text" disabled value="<?php echo htmlentities($row['school'],ENT_QUOTES,"UTF-8")?>">
    </div>
    <div class="oj-field">
      <label>学院</label>
      <input type="text" disabled value="<?php echo htmlentities($row['xueYuan'],ENT_QUOTES,"UTF-8")?>">
    </div>
    <form action="modify2.php" method="post">
      <?php require_once('./include/set_post_key.php');?>
      <input type="hidden" name="type" value="name">
      <div class="oj-field">
        <label>申请修改姓名（留空表示不改）</label>
        <input name="nick" type="text" maxlength="100" placeholder="新姓名">
      </div>
      <div class="oj-field">
        <label>申请理由（必填，100 字以内）</label>
        <input name="reason" type="text" maxlength="100" placeholder="如：姓名有误" value="信息错误">
      </div>
      <button class="oj-btn oj-btn-primary" type="submit">提交姓名修改申请</button>
    </form>
    <form action="modify2.php" method="post" style="margin-top:16px">
      <?php require_once('./include/set_post_key.php');?>
      <input type="hidden" name="type" value="class">
      <div class="oj-field">
        <label>申请修改学院</label>
        <select name="xueYuan">
          <?php foreach ($xueYuan as $x) { ?>
            <option value="<?php echo htmlentities($x[0],ENT_QUOTES,"UTF-8")?>" <?php if($x[0]==$row['xueYuan']) echo "selected"; ?>><?php echo htmlentities($x[0],ENT_QUOTES,"UTF-8")?></option>
          <?php } ?>
        </select>
      </div>
      <div class="oj-field">
        <label>申请修改专业班级（留空表示不改）</label>
        <input name="school" type="text" maxlength="100" placeholder="新专业班级">
      </div>
      <div class="oj-field">
        <label>申请理由（必填，100 字以内）</label>
        <input name="reason" type="text" maxlength="100" placeholder="如：转专业" value="信息错误">
      </div>
      <button class="oj-btn oj-btn-primary" type="submit">提交班级修改申请</button>
    </form>
  </div>

  <div class="oj-card">
    <h2>修改邮箱</h2>
    <form action="modify2.php" method="post">
      <?php require_once('./include/set_post_key.php');?>
      <input type="hidden" name="type" value="email">
      <div class="oj-field">
        <label>新邮箱</label>
        <input name="email" id="oj_email" type="email" maxlength="100" value="<?php echo htmlentities($row['email'],ENT_QUOTES,"UTF-8")?>" required>
      </div>
      <div class="oj-field">
        <label>邮箱验证码</label>
        <input name="code" type="text" maxlength="6" placeholder="6 位验证码" required>
        <button type="button" class="oj-btn" id="oj_sendmail" style="background:#eef4ff;color:#2f6ee5;margin-top:8px">获取验证码</button>
      </div>
      <button class="oj-btn oj-btn-primary" type="submit">修改邮箱</button>
    </form>
  </div>

  <div class="oj-card">
    <h2>修改联系方式</h2>
    <form action="modify2.php" method="post">
      <?php require_once('./include/set_post_key.php');?>
      <input type="hidden" name="type" value="other">
      <div class="oj-field">
        <label>手机号</label>
        <input name="phone" type="tel" maxlength="11" value="<?php echo htmlentities(isset($row['phone'])?$row['phone']:'',ENT_QUOTES,"UTF-8")?>">
      </div>
      <div class="oj-field">
        <label>QQ</label>
        <input name="qq" type="text" maxlength="20" value="<?php echo htmlentities(isset($row['qq'])?$row['qq']:'',ENT_QUOTES,"UTF-8")?>">
      </div>
      <button class="oj-btn oj-btn-primary" type="submit">保存</button>
    </form>
  </div>

  <div class="oj-card">
    <h2>修改密码</h2>
    <form action="modify.php" method="post">
      <?php require_once('./include/set_post_key.php');?>
      <div class="oj-field">
        <label>原密码</label>
        <input name="opassword" type="password" required>
      </div>
      <div class="oj-field">
        <label>新密码（留空表示不修改）</label>
        <input name="npassword" type="password" minlength="6">
      </div>
      <div class="oj-field">
        <label>确认新密码</label>
        <input name="rptpassword" type="password" minlength="6">
      </div>
      <button class="oj-btn oj-btn-primary" type="submit">修改密码</button>
    </form>
  </div>
</div>
<script>
$("#oj_sendmail").click(function () {
    var email = $("#oj_email").val();
    if(email.length < 7){ alert("邮箱不能为空！"); return; }
    $.ajax({ url: "/sendemail.php", data: { email: email },
        success: function(resp) { if (resp === "-1") { alert("发送成功。"); } else { alert(resp); } } });
});
</script>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
