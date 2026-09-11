<?php
  @session_start();
  if( $_SERVER['REQUEST_METHOD']=='POST'  ){
	$ok=false;
	if (isset($_SESSION[$OJ_NAME.'_'.'csrf_keys'])
    		&& is_array($_SESSION[$OJ_NAME.'_'.'csrf_keys'])
		&& isset($_POST['csrf'])
    		&& in_array($_POST['csrf'],$_SESSION[$OJ_NAME.'_'.'csrf_keys'],true)
    	){
		$ok=true;
	}
	// 兼容使用 postkey 的表单
	if(!$ok && isset($_SESSION[$OJ_NAME.'_'.'postkey']) && isset($_POST['postkey'])
		&& hash_equals($_SESSION[$OJ_NAME.'_'.'postkey'],(string)$_POST['postkey'])){
		$ok=true;
	}
	if(!$ok){
		echo "<!-csrf check failed->";
		exit(1);
	}
  }
