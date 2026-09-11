<?php require_once("admin-header.php");
if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
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
                            <h4 class="modal-title text-center">导出问题</h4>
                        </div>
                        <form action='problem_export_xml.php' method=post>
                            <?php require_once("../include/set_post_key.php");?>
                            <div class="modal-body" id = "model-body">
                                <div class="form-group">
                                    <input type=text size=10 name="start" class="form-control" placeholder="开始的题目编号" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="end" class="form-control" placeholder="结束的题目编号" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=40 name="in" class="form-control" placeholder="题目范围(编号)" autocomplete="off">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="form-group">
                                    <input type='hidden' name='do' value='do'>
                                    <input type=submit name=submit value='导出' class="btn btn-primary form-control">
                                    <input type=submit value='下载' class="btn btn-default form-control">
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h4>如果题目范围为空，则会利用开始至结束。</h4>
                            <h4>如果利用题目范围，那么开始至结束则会失效。</h4>
                            <h4>题目范围格式[1000,2000]。</h4>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
    document.getElementById('menu3').classList.remove("menu");
    document.getElementById('menu3').classList.add("menu-open");
    document.getElementById('a17').classList.remove("bg-primary");
    document.getElementById('a17').classList.add("bg-secondary");
</script>
</body>


