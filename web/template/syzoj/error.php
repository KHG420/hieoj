<?php $show_title="错误信息 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<script>
    function back() {
        history.go(-1);
    }
</script>
<div class="ui negative icon message">
  <i class="remove icon"></i>
  <div class="content">
    <div class="header" style="margin-bottom: 10px; ">
      <?php echo $view_errors?>
    </div>
      <!-- <p><%= err.details %></p> -->
    <p>
        <!-- <a href="<%= err.nextUrls[text] %>" style="margin-right: 5px; "><%= text %></a> -->
        <?php if (!isset($_SESSION[$OJ_NAME.'_user_id']) && (strpos($view_errors, 'loginpage.php') !== false || strpos($view_errors, '登录') !== false)) { ?>
        <a class="ui primary button" href="loginpage.php">登录后继续</a>
        <?php } ?>
        <button type="button" class="ui basic button" onclick="back()">返回上一页</button>
    </p>
  </div>
</div>

<?php include("template/$OJ_TEMPLATE/footer.php");?>
