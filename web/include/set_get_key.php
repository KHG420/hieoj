<?php
// 安全修复：使用强随机令牌
if(function_exists('random_bytes')) $k=bin2hex(random_bytes(16));
elseif(function_exists('openssl_random_pseudo_bytes')) $k=bin2hex(openssl_random_pseudo_bytes(16));
else $k=bin2hex(mt_rand()).bin2hex(uniqid('',true));
$_SESSION[$OJ_NAME.'_'.'getkey']=strtoupper(substr($k,0,10));
