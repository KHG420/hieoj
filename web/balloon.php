<?php
    require_once('./include/db_info.inc.php');
    require_once('./include/const.inc.php');
    require_once('./include/memcache.php');
	require_once('./include/setlang.php');
	$view_title=$MSG_SUBMIT;
 if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){

	$view_errors= "<a href=loginpage.php>$MSG_Login</a>";
	require("template/".$OJ_TEMPLATE."/error.php");
	exit(0);
//	$_SESSION[$OJ_NAME.'_'.'user_id']="Guest";
 }
if ($_SERVER['REQUEST_METHOD']=="POST"){
	require_once("include/check_post_key.php");
}
if(isset($_SESSION[$OJ_NAME.'_'.'balloon'])){
	$school=pdo_query("select school from users where user_id=?",$_SESSION[$OJ_NAME."_user_id"])[0][0];
	$cid=intval($_GET['cid']);
	if($cid==0) $cid=1000;
	
		// 安全修复：标记完成仅接受 POST（令牌由上方 check_post_key 校验）；school 通配符转义
		$school_like = addcslashes($school,"%_")."%";
		if(isset($_POST['id'])){
			$id=intval($_POST['id']);
			pdo_query("update balloon set status=1 where balloon_id=?",$id);
		}
		if(isset($_POST['clean'])){
			pdo_query("delete from balloon where cid=? and user_id like ?",$cid,$school_like);
		}
		
		$sql="select * from solution where result=4 and contest_id=? and user_id like ? and solution_id not in (select sid from balloon where cid=?) order by solution_id;";
		$result=pdo_query($sql,$cid,$school_like,$cid);
	        foreach($result as $row){
		     $user_id=$row['user_id'];
		     $sid=$row['solution_id'];
		     $pid=$row['num'];
		     $sql="select balloon_id from balloon where user_id=? and cid=? and pid=?";
			//echo $sql;
		     if(count(pdo_query($sql,$user_id,$cid,$pid))==0){
			$sql="insert into balloon(user_id,sid,cid,pid,status) value(?,?,?,?,0)";
//			echo $sql."<br>".$user_id." ".$sid." ".$cid." ".$pid;
			pdo_query($sql,$user_id,$sid,$cid,$pid);
		     }		     
		}	
		$view_balloon=Array();
		$result=pdo_query("select * from balloon where cid= ? and  user_id like ? order by status,balloon_id limit 50",$cid,"$school%");
		$i=0;
		foreach ($result as $row){
			$view_balloon[$i]=Array();
			$view_balloon[$i][0]=$row['balloon_id'];
			$view_balloon[$i][1]=$row['user_id'];
			$view_balloon[$i][2]= "<font color='".$ball_color[$row['pid']]."'>";
			 $view_balloon[$i][2].=$ball_name[$row['pid']];
			 $view_balloon[$i][2].="</font>";
			if($row['status']==1)$view_balloon[$i][3].="<span class='btn btn-success'>$MSG_BALLOON_DONE</span>";
			else $view_balloon[$i][3].="<span class='btn btn-danger'>$MSG_BALLOON_PENDING</span>";
			$view_balloon[$i][4]="<a class='btn btn-info' href='balloon_view.php?id=".$row['balloon_id']."' target='_self'>$MSG_PRINTER</a>";
			if(!isset($_SESSION[$OJ_NAME.'_'.'postkey'])){
				if(function_exists('random_bytes')) $k=bin2hex(random_bytes(16));
				elseif(function_exists('openssl_random_pseudo_bytes')) $k=bin2hex(openssl_random_pseudo_bytes(16));
				else $k=bin2hex(mt_rand()).bin2hex(uniqid('',true));
				$_SESSION[$OJ_NAME.'_'.'postkey']=$k;
			}
			$view_balloon[$i][4].="<form method='post' action='balloon.php?cid=$cid' style='display:inline'><input type='hidden' name='postkey' value='".htmlentities($_SESSION[$OJ_NAME.'_'.'postkey'],ENT_QUOTES,'UTF-8')."'><input type='hidden' name='id' value='".$row['balloon_id']."'><button type='submit' class='btn btn-primary'>$MSG_PRINT_DONE</button></form>";
			
			$i++;
		}
		require("template/".$OJ_TEMPLATE."/balloon_list.php");
		exit(0);

 }else{

	$view_errors= "$MSG_BALLOON not available!";
	require("template/".$OJ_TEMPLATE."/error.php");
	exit(0);
 }
/////////////////////////Template
/////////////////////////Common foot
?>

