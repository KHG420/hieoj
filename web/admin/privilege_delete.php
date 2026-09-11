<?php require_once("admin-header.php");?>
<?php require_once("../include/check_get_key.php");
if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
}
if(isset($_GET['uid'])){
	$user_id=$_GET['uid'];
	$rightstr =$_GET['rightstr'];
	$sql="delete from `privilege` where user_id=? and rightstr=?";
	$rows=pdo_query($sql,$user_id,$rightstr);
    echo"<script>alert('$user_id $rightstr deleted!')</script>";
    echo"<script>window.history.back(-1)</script>";
}
?>
