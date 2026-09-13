<?php $show_title="登录 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>

<div class="ui error message" id="error" hidden></div>
<div class="ui middle aligned center aligned grid"  style="height: 500px;" >
  <div class="row">
    <div class="column" style="max-width: 450px">
      <h1 class="ui image header">
        <div class="content" style="margin-bottom: 10px; ">
          登&nbsp;&nbsp;&nbsp;&nbsp;录
	</div>
      </h1>
      <form class="ui large form" id="login" action="login.php" method="post" role="form" onSubmit="return jsMd5();" >
        <?php require_once('./include/set_post_key.php');?>
        <input type="hidden" name="url" value="<?php echo htmlentities(isset($login_target)?$login_target:"",ENT_QUOTES,"UTF-8")?>">
        <div class="ui existing segment">
          <div class="field">
            <div class="ui left icon input">
              <i class="user icon"></i>
              <label class="sr-only" for="username">用户名（学号）</label><input name="user_id" placeholder="用户名（学号）" type="text" id="username" autocomplete="username" required>
            </div>
          </div>
          <div class="field">
            <div class="ui left icon input">
              <i class="lock icon"></i>
              <label class="sr-only" for="password">密码</label><input name="password" placeholder="密码" type="password" id="password" autocomplete="current-password" required>
            </div>
          </div>
          <?php if($OJ_VCODE){?>
            <div class="field">
              <div class="ui left icon input">
                <i class="lock icon"></i>
                <input name="vcode" aria-label="验证码" placeholder="验证码" type="text" autocomplete="off" required>
                <img id="vcode-img" onclick="this.src='vcode.php?'+Math.random()" height="30px">
              </div>
            </div>
          <?php }?>
          <button name="submit" type="submit" class="ui primary fluid large submit button" >登录</button>
        </div>

        <div class="ui error message"></div>

      </form>

      <div class="ui message">
          <a href="registerpage.php">注册账号</a>
          &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
          <a href="lostpassword.php">忘记密码</a>
      </div>
    </div>
  </div>
</div>
<script src="<?php echo $OJ_CDN_URL?>include/md5-min.js"></script>
<script>
  function jsMd5(){
    if (!document.getElementById("login").reportValidity()) return false;
    $("input[name=password]").val(hex_md5($("input[name=password]").val()));
    return true;
  }
</script>
<?php if ($OJ_VCODE) { ?>
    <script>
        $(document).ready(function () {
            $("#vcode-img").attr("src", "vcode.php?" + Math.random());
        })
    </script>
<?php } ?>


<?php include("template/$OJ_TEMPLATE/footer.php");?>
