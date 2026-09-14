<?php
function crypto_rand_secure($min, $max) {
        $range = $max - $min;
        if ($range < 0) return $min; // not so random...
        $log = log($range, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
			if(function_exists("openssl_random_pseudo_bytes")){
				$rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
			}else{
				$rnd = hexdec(bin2hex(rand()."_".rand()));
			}
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;
}

function send_udp_message($host, $port, $message)
{
    $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    @socket_connect($socket, $host, $port);

    $num = 0;
    $length = strlen($message);
    do
    {
        $buffer = substr($message, $num);
        $ret = @socket_write($socket, $buffer);
        $num += $ret;
    } while ($num < $length);

    socket_close($socket);

    // UDP ............, ............
    return true;
}



function trigger_judge($solution_id=0){
          global $OJ_UDPSERVER,$OJ_UDPPORT,$OJ_JUDGE_HUB_PATH;
          $JUDGE_SERVERS = explode(",",$OJ_UDPSERVER);
          $JUDGE_TOTAL = count($JUDGE_SERVERS);

          $select = $solution_id%$JUDGE_TOTAL;
          $JUDGE_HOST = $JUDGE_SERVERS[$select];

          if (strstr($JUDGE_HOST,":")!==false) {
            $JUDGE_SERVERS = explode(":",$JUDGE_HOST);
            $JUDGE_HOST = $JUDGE_SERVERS[0];
            $OJ_UDPPORT = $JUDGE_SERVERS[1];
          }
          if(isset($OJ_JUDGE_HUB_PATH))
                send_udp_message($JUDGE_HOST, $OJ_UDPPORT, $OJ_JUDGE_HUB_PATH);
          else
                send_udp_message($JUDGE_HOST, $OJ_UDPPORT, $solution_id );
}

function getToken($length=32){
    $token = "";
    $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
    $codeAlphabet.= "0123456789";
    for($i=0;$i<$length;$i++){
        $token .= $codeAlphabet[crypto_rand_secure(0,strlen($codeAlphabet))];
    }
    return $token;
}

function problem_locked($problem_id,$level=1){
        global $OJ_NOIP_KEYWORD;
        $now = date('Y-m-d H:i', time());
        $sql="select c.contest_id cid from contest c inner join contest_problem cp on c.contest_id=cp.contest_id and cp.problem_id=? and (c.contest_type & ? > 0  or c.title like ? ) and start_time<? and end_time> ?  ";
        $result=pdo_query($sql,$problem_id,$level,"%$OJ_NOIP_KEYWORD%",$now,$now);
        if(empty($result)||$result[0]['cid']==0) return false;
        else return $result[0]['cid'];
}

function pwGen($password,$md5ed=False) 
{
	if (!$md5ed) $password=md5($password);
	$salt = sha1(rand());
	$salt = substr($salt, 0, 4);
	$hash = base64_encode( sha1($password . $salt, true) . $salt ); 
	return $hash; 
}

function pwCheck($password,$saved)
{
	if (isOldPW($saved)){
		if(!isOldPW($password)) $mpw = md5($password);
		else $mpw=$password;
		if ($mpw==$saved) return True;
		else return False;
	}
	$svd=base64_decode($saved);
	$salt=substr($svd,20);
	if(!isOldPW($password)) $password=md5($password);
	$hash = base64_encode( sha1(($password) . $salt, true) . $salt );
	if (strcmp($hash,$saved)==0) return True;
	else return False;
}

function isOldPW($password)
{
	if(strlen($password)!=32) return false;
	for ($i=strlen($password)-1;$i>=0;$i--)
	{
		$c = $password[$i];
		if ('0'<=$c && $c<='9') continue;
		if ('a'<=$c && $c<='f') continue;
		if ('A'<=$c && $c<='F') continue;
		return False;
	}
	return True;
}

function is_valid_user_name($user_name){
	$len=strlen($user_name);
	for ($i=0;$i<$len;$i++){
		if (
			($user_name[$i]>='a' && $user_name[$i]<='z') ||
			($user_name[$i]>='A' && $user_name[$i]<='Z') ||
			($user_name[$i]>='0' && $user_name[$i]<='9') ||
			$user_name[$i]=='_'||
			($i==0 && $user_name[$i]=='*') 
		);
		else return false;
	}
	return true;
}

function in_subnet_of_contest($ip,$contest_id){

	$sql="select contest_type,subnet from contest where contest_id=? ";
	$result=pdo_query($sql,$contest_id);
	if(!empty($result)){
		$contest_type=$result[0]['contest_type'];
		$subnet=$result[0]['subnet'];
		if(!empty($subnet)){
			$subnets=explode(",",$subnet);
			foreach( $subnets as $net){
				if(is_ip_in_subnet($ip,$net)){
			//		echo "subnet:$net<br>";
				       	return true;
				}
			}
			return false;
		}else{
			return true;
		}
	}

}

function sec2str($sec){
	return sprintf("%02d:%02d:%02d",$sec/3600,$sec%3600/60,$sec%60);
}
function is_running($cid){
   if(!$cid) return false; // 性能优化：无效 cid 直接返回
   $now=date("Y-m-d H:i",time());
	$sql="SELECT count(*) FROM `contest` WHERE `contest_id`=? AND `end_time`>?";
	$result=pdo_query($sql,$cid,$now);
	$row=$result[0];
	$cnt=intval($row[0]);
	return $cnt>0;
}
function check_ac($cid,$pid){
	//require_once("./include/db_info.inc.php");
	global $OJ_NAME;
	
	$sql="SELECT count(*) FROM `solution` WHERE `contest_id`=? AND `num`=? AND `result`='4' AND `user_id`=?";
	$result=pdo_query($sql,$cid,$pid,$_SESSION[$OJ_NAME.'_'.'user_id']);
	 $row=$result[0];
	$ac=intval($row[0]);
	if ($ac>0) return "<font color=green>Y</font>";
	$sql="SELECT count(*) FROM `solution` WHERE `contest_id`=? AND `num`=? AND `result`!=4 and `problem_id`!=0  AND `user_id`=?";
	$result=pdo_query($sql,$cid,$pid,$_SESSION[$OJ_NAME.'_'.'user_id']);
	$row=$result[0];
	$sub=intval($row[0]);
	
	if ($sub>0) return "<font color=red>N</font>";
	else return "";
}



function RemoveXSS($val) {
   // remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
   // this prevents some character re-spacing such as <java\0script>
   // note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
   $val = preg_replace('/([\x00-\x08\x0b-\x0c\x0e-\x19])/', '', $val);

   // straight replacements, the user should never need these since they're normal characters
   // this prevents like <IMG SRC=@avascript:alert('XSS')>
   $search = 'abcdefghijklmnopqrstuvwxyz';
   $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
   $search .= '1234567890!@#$%^&*()';
   $search .= '~`";:?+/={}[]-_|\'\\';
   for ($i = 0; $i < strlen($search); $i++) {
      // ;? matches the ;, which is optional
      // 0{0,7} matches any padded zeros, which are optional and go up to 8 chars

      // @ @ search for the hex values
      $val = preg_replace('/(&#[xX]0{0,8}'.dechex(ord($search[$i])).';?)/i', $search[$i], $val); // with a ;
      // @ @ 0{0,7} matches '0' zero to seven times
      $val = preg_replace('/(&#0{0,8}'.ord($search[$i]).';?)/', $search[$i], $val); // with a ;
   }

   // now the only remaining whitespace attacks are \t, \n, and \r   //, 'style'
   $ra1 = Array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base');
   $ra2 = Array('onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');
   $ra = array_merge($ra1, $ra2);

   $found = true; // keep replacing as long as the previous round replaced something
   while ($found == true) {
      $val_before = $val;
      for ($i = 0; $i < sizeof($ra); $i++) {
         // Tag names are dangerous as markup, not inside title attributes or URLs.
         $is_tag = in_array($ra[$i], array('applet','meta','xml','blink','link','script','embed','object','iframe','frame','frameset','ilayer','layer','bgsound','title','base'), true);
         $pattern = $is_tag ? '/(<\\s*\\/?\\s*)' : '/';
         for ($j = 0; $j < strlen($ra[$i]); $j++) {
            if ($j > 0) {
               $pattern .= '(';
               $pattern .= '(&#[xX]0{0,8}([9ab]);)';
               $pattern .= '|';
               $pattern .= '|(&#0{0,8}([9|10|13]);)';
               $pattern .= ')*';
            }
            $pattern .= $ra[$i][$j];
         }
         $pattern .= '/i';
         $replacement = ($is_tag ? '$1' : '').substr($ra[$i], 0, 2).'<x>'.substr($ra[$i], 2); // add in <> to nerf the tag
         $val = preg_replace($pattern, $replacement, $val); // filter out the hex tags
         if ($val_before == $val) {
            // no replacements were made, so exit the loop
            $found = false;
         }
      }
   }
   return $val;
}


?>
