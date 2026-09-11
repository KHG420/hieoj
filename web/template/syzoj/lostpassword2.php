<?php $show_title="重置密码 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>
.oj-card { max-width: 460px; margin: 40px auto; background: #fff; border: 1px solid #e6ebf2; border-radius: 10px; box-shadow: 0 4px 14px rgba(15,23,42,.06); padding: 24px; }
.oj-card h2 { margin: 0 0 16px; font-size: 18px; color: #1f2d3d; border-bottom: 1px solid #e6ebf2; padding-bottom: 12px; }
.oj-field { margin-bottom: 14px; }
.oj-field label { display: block; font-size: 14px; font-weight: 600; color: #1f2d3d; margin-bottom: 6px; }
.oj-field input { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #d7dee8; border-radius: 6px; font-size: 14px; }
.oj-btn { display: inline-block; padding: 9px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; background: #2f6ee5; color: #fff; }
.oj-btn:hover { background: #1e56c6; }
.oj-hint { color: #5f6d85; font-size: 13px; margin-top: 12px; }
</style>
<div class="oj-card">
  <h2>确认重置密码</h2>
  <form action="lostpassword2.php" method="post">
    <?php require_once('./include/set_post_key.php');?>
    <div class="oj-field">
      <label>用户名（学号）</label>
      <input name="user_id" type="text" required>
    </div>
    <div class="oj-field">
      <label>邮箱收到的重置密钥</label>
      <input name="lost_key" type="text" required>
    </div>
    <?php if($OJ_VCODE){?>
    <div class="oj-field">
      <label>验证码</label>
      <input name="vcode" type="text" size="4">
      <img alt="click to change" src="vcode.php" onclick="this.src='vcode.php?'+Math.random()">
    </div>
    <?php }?>
    <button class="oj-btn" type="submit">重置密码</button>
  </form>
  <p class="oj-hint">重置后密码将设置为上述密钥，请登录后立即修改。</p>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
