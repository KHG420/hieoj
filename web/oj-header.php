<?php function checkcontest($MSG_CONTEST){
	global $MSG_CONTEST;
      $now=date("Y-m-d H:i",time());
		$sql="SELECT count(*) FROM `contest` WHERE `end_time`>? AND `defunct`='N'";
		$result=pdo_query($sql,$now);
		$row=$result[0];
		if (intval($row[0])==0) $retmsg=$MSG_CONTEST;
		else $retmsg=$row[0]."<span class=red>&nbsp;$MSG_CONTEST</span>";
		
		return $retmsg;
	}
	
	 $OJ_FAQ_LINK="faqs.php";
   if(isset($OJ_LANG)){
                require_once(dirname(__FILE__)."/lang/$OJ_LANG.php");
                if(file_exists(dirname(__FILE__)."/faqs.$OJ_LANG.php")){
                        $OJ_FAQ_LINK="faqs.$OJ_LANG.php";
                }
    }
	
	if($OJ_ONLINE){
		require_once(dirname(__FILE__).'/include/online.php');
		$on = new online();
	}
	$url=basename($_SERVER['REQUEST_URI']);
	require_once(dirname(__FILE__)."/include/cache_layer.php");
	$view_marquee_msg=cache_get("oj_msg_local");
	if($view_marquee_msg===false){
		$raw=@file_get_contents($OJ_SAE?"saestor://web/msg.txt":dirname(__FILE__)."/admin/msg.txt");
		$view_marquee_msg=($raw===false?'':trim($raw));
		cache_set("oj_msg_local",$view_marquee_msg,60);
	}
	
	if(file_exists(dirname(__FILE__)."/template/".$OJ_TEMPLATE."/oj-header.php"))
		require(dirname(__FILE__)."/template/".$OJ_TEMPLATE."/oj-header.php");
	else
		require(dirname(__FILE__)."/template/".$OJ_TEMPLATE."/header.php");
?>