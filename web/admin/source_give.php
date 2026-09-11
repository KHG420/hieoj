<?php require_once("admin-header.php");
if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
}?>
<?php if(isset($_POST['do'])){
	require_once("../include/check_post_key.php");
	$from=$_POST['from'];
	$to=$_POST['to'];
	$start=intval($_POST['start']);
	$end=intval($_POST['end']);
	$sql="update `solution` set `user_id`=? where `user_id`=? and problem_id>=? and problem_id<=? and result=4";
	//echo $sql;
	echo pdo_query($sql,$to,$from,$start,$end)." source file given!";
	
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
                            <h4 class="modal-title text-center">转移源码</h4>
                        </div>
                        <form action='source_give.php' method=post class=center>
                            <?php require_once("../include/set_post_key.php");?>
                            <div class="modal-body" id = "model-body">
                                <div class="form-group">
                                    <input type=text size=10 name="from" class="form-control" placeholder="发起用户的学号" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="to" class="form-control" placeholder="接收用户的学号" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="start" class="form-control" placeholder="开始题号" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="end" class="form-control" placeholder="结束题号" autocomplete="off">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="form-group">
                                    <input type='hidden' name='do' value='do'>
                                    <input type=submit value='开始转移' class="btn btn-primary form-control">
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
    document.getElementById('a11').classList.remove("bg-primary");
    document.getElementById('a11').classList.add("bg-secondary");
</script>
</body>