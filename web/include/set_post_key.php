<?php
// 安全修复：使用强随机令牌；同一会话内保持稳定（避免多标签页互相覆盖导致提交失败）
if(!isset($_SESSION[$OJ_NAME.'_'.'postkey'])){
	if(function_exists('random_bytes')) $k=bin2hex(random_bytes(16));
	elseif(function_exists('openssl_random_pseudo_bytes')) $k=bin2hex(openssl_random_pseudo_bytes(16));
	else $k=bin2hex(mt_rand()).bin2hex(uniqid('',true));
	$_SESSION[$OJ_NAME.'_'.'postkey']=$k;
}
?>
<input type=hidden name="postkey" value="<?php echo htmlentities($_SESSION[$OJ_NAME.'_'.'postkey'],ENT_QUOTES,'UTF-8')?>">
