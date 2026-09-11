<?php
require_once("./include/db_info.inc.php");
require_once('./include/setlang.php');
?>

<?php $show_title="博客"." - $OJ_NAME";

?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<script>
    $("#main_container").removeClass("ui");
    $("#main_container").removeClass("main");
    $("#main_container").removeClass("container");
    $("#marg").removeAttr("style","");
</script>
<div style="position:absolute;width:100%;height:100%">
<iframe src="/blog/" width="100%" height="100%" frameborder="no"></iframe>
</div>
