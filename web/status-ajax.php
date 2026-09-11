<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

////////////////////////////Common head
$cache_time = 2;
$OJ_CACHE_SHARE = false;

require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');
$view_title = "$MSG_STATUS";

require_once("./include/const.inc.php");

// 安全修复：先读取会话权限，随后尽早释放会话锁，避免轮询互相阻塞
$is_admin = isset($_SESSION[$OJ_NAME.'_'.'administrator']);
$is_src = isset($_SESSION[$OJ_NAME.'_'.'source_browser']);
$my_user = isset($_SESSION[$OJ_NAME.'_'.'user_id']) ? $_SESSION[$OJ_NAME.'_'.'user_id'] : null;
@session_write_close();

$solution_id = 0;
// check the top arg

if (isset($_GET['solution_id'])) {
	$solution_id = intval($_GET['solution_id']);
}

$sql = "select * from solution where solution_id=? LIMIT 1";
$result = pdo_query($sql,$solution_id);

if (count($result)>0) {
	$row = $result[0];
	// 安全修复：tr=1 仅在查看自己的提交（或 source_browser）时返回编译/运行信息
	if (isset($_GET['tr']) && $my_user!==null && ($is_src || $row['user_id']==$my_user)) {
		$res = $row['result'];

		if ($res==11) {
			$sql = "SELECT `error` FROM `compileinfo` WHERE `solution_id`=?";
		}
		else {
			$sql = "SELECT `error` FROM `runtimeinfo` WHERE `solution_id`=?";
		}

		$result = pdo_query($sql,$solution_id);
		$row = $result[0];

		if ($row) {
			echo htmlentities(str_replace("
","
",$row['error']),ENT_QUOTES,"UTF-8");
			$sql = "delete from custominput where solution_id=?";
			pdo_query($sql,$solution_id);
		}
		//echo $sql.$res;
	}
	else {
		// 安全修复：q=user_id 仅限 source_browser/管理员（用于查重展示）
		if (isset($_GET['q']) && "user_id"==$_GET['q'] && ($is_src || $is_admin)) {
			echo htmlentities($row['user_id'],ENT_QUOTES,"UTF-8")."["."]";
		}
		else {
			$contest_id = $row['contest_id'];

			if ($contest_id>0) {
				$result = pdo_query("select title from contest where contest_id=?",$contest_id);
				$contest_title = $result[0][0];

				if (stripos($contest_title,$OJ_NOIP_KEYWORD)!==false) {
					echo "$OJ_NOIP_KEYWORD";
					exit(0);
				}
			}

			if (isset($_GET['t']) && "json"==$_GET['t']) {
				echo json_encode($row);
			}
			else {
				if($is_admin)
					echo $row['result'].",".$row['memory']." KB,".$row['time']." ms,".$row['judger'].",".($row['pass_rate']*100);
				else
					echo $row['result'].",".$row['memory']." KB,".$row['time']." ms,"."none,".($row['pass_rate']*100);
			}
		}
	}
}
else {
	echo $solution_id;
	echo "0, 0, 0,unknown,0";
}

?>