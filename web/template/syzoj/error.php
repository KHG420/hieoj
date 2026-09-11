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
      <div onclick="back()" style="cursor:pointer; color: #0e90d2">返回上一页</div>
    </p>
  </div>
</div>

<?php include("template/$OJ_TEMPLATE/footer.php");?>
