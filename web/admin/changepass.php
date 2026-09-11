<?php require_once("admin-header.php");?>
<?php if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator'])|| isset($_SESSION[$OJ_NAME.'_'.'password_setter']) )){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
}
if(isset($_POST['do'])){
	//echo $_POST['user_id'];
	require_once("../include/check_post_key.php");
	//echo $_POST['passwd'];
	require_once("../include/my_func.inc.php");
	
	$user_id=$_POST['user_id'];
    $passwd =$_POST['passwd'];
    if ((function_exists('get_magic_quotes_gpc')&&get_magic_quotes_gpc())) {
		$user_id = stripslashes ( $user_id);
		$passwd = stripslashes ( $passwd);
	}
	$passwd=pwGen($passwd);
	$sql="update `users` set `password`=? where `user_id`=?  and user_id not in( select user_id from privilege where rightstr='administrator') ";
	
	if (pdo_query($sql,$passwd,$user_id)==1) echo "<script>alert('Password Changed!')</script>";
  else echo "<script>alert('No such user! or He/Her is an administrator!')</script>";
}
?>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include("navbar.php");?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="row">
                <div class="col-sm-1">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <div class="modal-dialog" style="margin-top: 10%;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title text-center">修改密码</h4>
                        </div>
                        <form action='changepass.php' method=post class=center>
                            <div class="modal-body" id = "model-body">
                                <div class="form-group">
                                    <input type=text size=10 name="user_id" class="form-control" placeholder="<?php echo $MSG_USER_ID?>" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="passwd" class="form-control" placeholder="<?php echo $MSG_PASSWORD?>" autocomplete="off">
                                </div>
                                <?php require_once("../include/set_post_key.php");?>
                            </div>
                            <div class="modal-footer">
                                <div class="form-group">
                                    <input type='hidden' name='do' value='do'>
                                    <input type=submit value='Change' class="btn btn-primary form-control">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
    document.getElementById('menu2').classList.remove("menu");
    document.getElementById('menu2').classList.add("menu-open");
    document.getElementById('a10').classList.remove("bg-primary");
    document.getElementById('a10').classList.add("bg-secondary");
</script>
</body>
