<?php require_once("admin-header.php");?>
<?php if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
if(isset($_POST['do'])){
    $user_id = $_POST['user_id'];
    require_once("../include/check_post_key.php");
    $user_id=$_POST['user_id'];
    $rightstr =$_POST['rightstr'];
    if(isset($_POST['contest'])) $rightstr="c$rightstr";
    if(isset($_POST['psv'])) $rightstr="s$rightstr";
    $sql="insert into `privilege` values(?,?,'N')";
    $rows=pdo_query($sql,$user_id,$rightstr);
    echo "<script>alert('$user_id $rightstr 添加成功!')</script>";

}
?>
<link rel="stylesheet" href="https://cdn.bootcss.com/bootstrap/4.0.0-beta/css/bootstrap.min.css">
<link href="https://cdn.bootcdn.net/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css" rel="stylesheet">
<link href="https://cdn.bootcss.com/font-awesome/5.13.0/css/all.css" rel="stylesheet">
<style>
    .nav > li > a:hover{
        background-color:#01AAED;
    }
</style>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include("navbar.php");?>
    <?php
    $sql = "SELECT COUNT(*) AS ids FROM privilege WHERE rightstr IN ('administrator','source_browser','contest_creator','http_judge','problem_editor','password_setter','printer','balloon') ORDER BY user_id, rightstr";
    $result = pdo_query($sql);
    $row = $result[0];

    $ids = intval($row['ids']);

    $idsperpage = 25;
    $pages = intval(ceil($ids/$idsperpage));

    if(isset($_GET['page'])){ $page = intval($_GET['page']);}
    else{ $page = 1;}

    $pagesperframe = 5;
    $frame = intval(ceil($page/$pagesperframe));

    $spage = ($frame-1)*$pagesperframe+1;
    $epage = min($spage+$pagesperframe-1, $pages);

    $sid = ($page-1)*$idsperpage;

    $sql = "";
    if(isset($_GET['keyword']) && $_GET['keyword']!=""){
        $keyword = $_GET['keyword'];
        $keyword = "%$keyword%";
        $sql = "SELECT * FROM privilege WHERE (user_id LIKE ?) OR (rightstr LIKE ?) ORDER BY user_id, rightstr";
        $result = pdo_query($sql,$keyword,$keyword);
    }else{
        $sql = "SELECT * FROM privilege WHERE rightstr IN ('administrator','source_browser','contest_creator','http_judge','problem_editor','password_setter','printer','balloon') ORDER BY user_id, rightstr LIMIT $sid, $idsperpage";
        $result = pdo_query($sql);
    }
    ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="row">
                <div class="col-sm-1">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </div>
            </div>
        </div>
        <center><h3>增加权限</h3></center>
        <section class="content">
            <div class="container-fluid">
                <div class="modal-dialog" style="margin-top: 10%;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title text-center">增加权限</h4>
                            <select class="dropdown btn-default" onchange="choiceShow()" id="selete1">
                                <option class="dropdown-item" value="1">普通权限</option>
                                <option class="dropdown-item" value="2">竞赛查看权限</option>
                                <option class="dropdown-item" value="3">答案查看权限</option>
                            </select>
                        </div>
                        <form method=post id="form1" style="position: relative">
                            <?php require("../include/set_post_key.php");?>
                            <div class="modal-body" id = "model-body">
                                <div class="form-group">
                                    <input type=text size=10 name="user_id" class="form-control" placeholder="学号" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <select name="rightstr" class="form-control" autocomplete="off">
                                        <?php
                                        $rightarray=array("administrator","problem_editor","source_browser","contest_creator","http_judge","password_setter","printer","balloon" );
                                        while(list($key, $val)=each($rightarray)) {
                                            if (isset($rightstr) && ($rightstr == $val)) {
                                                echo '<option value="'.$val.'" selected>'.$val.'</option>';
                                            } else {
                                                echo '<option value="'.$val.'">'.$val.'</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="modal-footer">
                                    <div class="form-group">
                                        <input type='hidden' name='do' value='do'>
                                        <input type=submit value='添加' class="btn btn-primary form-control">
                                    </div>
                                </div>
                            </div>
                        </form>
                        <form method=post id="form2" style="position: relative;display: none">
                            <div class="modal-body" id = "model-body">
                                <div class="form-group">
                                    <input type=text size=10 name="user_id" class="form-control" placeholder="学号" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="rightstr" class="form-control" placeholder="竞赛编号(1000即编号为1000的竞赛)" autocomplete="off">
                                </div>
                                <div class="modal-footer">
                                    <div class="form-group">
                                        <input type='hidden' name='do' value='do'>
                                        <input type='hidden' name='contest' value='do'>
                                        <input type=submit value='添加' class="btn btn-primary form-control">
                                        <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
                                    </div>
                                </div>
                            </div>
                        </form>
                        <form method=post id="form3" style="position:relative;display: none">
                            <div class="modal-body" id = "model-body">
                                <div class="form-group">
                                    <input type=text size=10 name="user_id" class="form-control" placeholder="学号" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="rightstr" class="form-control" placeholder="题目编号(1000即编号为1000的题目)" autocomplete="off">
                                </div>
                                <div class="modal-footer">
                                    <div class="form-group">
                                        <input type='hidden' name='do' value='do'>
                                        <input type='hidden' name='psv' value='do'>
                                        <input type=submit value='添加' class="btn btn-primary form-control">
                                        <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
                                    </div>
                                </div>
                            </div>
                        </form>
                        <script>
                            function showContest(){
                                document.getElementById('form1').style.display = "none";
                                document.getElementById('form3').style.display = "none";
                                document.getElementById('form2').style.display = "";
                            }
                            function showPrivilege(){
                                document.getElementById('form1').style.display = "";
                                document.getElementById('form3').style.display = "none";
                                document.getElementById('form2').style.display = "none";
                            }
                            function showView(){
                                document.getElementById('form1').style.display = "none";
                                document.getElementById('form3').style.display = "";
                                document.getElementById('form2').style.display = "none";
                            }
                            function choiceShow(){
                                var obj = document.getElementById('selete1');
                                var a = obj.options[obj.selectedIndex].value;
                                if(a === '1'){
                                    showPrivilege();
                                }else if(a === '2'){
                                    showContest();
                                }else if(a === '3'){
                                    showView();
                                }
                            }
                        </script>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
    document.getElementById('menu2').classList.remove("menu");
    document.getElementById('menu2').classList.add("menu-open");
    document.getElementById('a13').classList.remove("bg-primary");
    document.getElementById('a13').classList.add("bg-secondary");
</script>
</body>
