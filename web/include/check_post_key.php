<?php
if (!isset($_SESSION[$OJ_NAME.'_'.'postkey'])||!isset($_POST['postkey'])||!hash_equals($_SESSION[$OJ_NAME.'_'.'postkey'],(string)$_POST['postkey']))
	exit(1);
