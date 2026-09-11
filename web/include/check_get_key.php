<?php
if (!isset($_SESSION[$OJ_NAME.'_'.'getkey'])||!isset($_GET['getkey'])||!hash_equals($_SESSION[$OJ_NAME.'_'.'getkey'],(string)$_GET['getkey'])){
?>
<script language=javascript>
        history.go(-1);
</script>
<?php 
	exit(1);
}
else{
   unset($_SESSION[$OJ_NAME.'_'.'getkey']);
}
?>
