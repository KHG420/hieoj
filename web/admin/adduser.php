<?php require_once("admin-header.php");?>
<?php if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) )){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
if(isset($_POST['do'])){
    //echo $_POST['user_id'];
    require_once("../include/check_post_key.php");
    //echo $_POST['passwd'];
    require_once("../include/my_func.inc.php");
    require_once("../include/db_info.inc.php");

    $user_id = $_POST['user_id'];
    $nick = $_POST['nick'];
    $school = $_POST['school'];
    if ((function_exists('get_magic_quotes_gpc')&&get_magic_quotes_gpc())) {
        $user_id = stripslashes ( $user_id);
        $nick = stripslashes ( $nick);
        $school = stripslashes ( $school);
    }
    $defunct = "N";
    $ip = "0.0.0.0";
    $volume = "1";
    $language = "1";
    $password = "InASOwX0s6UMVOqyfJu4rOKI/QA3OTI0";
//    echo $nick;
//    echo $user_id;
//    echo $school;
//    $sql="update `users` set `password`=? where `user_id`=?  and user_id not in( select user_id from privilege where rightstr='administrator') ";
    $sql = "insert INTO users(`user_id`,`defunct`,`ip`,`volume`,`language`,`password`,`reg_time`,`nick`,`school`) 
            VALUES(?,?,?,?,?,?,now(),?,?)";

//    echo $sql;

    $result = pdo_query($sql, $user_id, $defunct, $ip, $volume, $language, $password, $nick, $school);

    if ($result == 1) {
        echo "<script添加成功：".$user_id;
    } else {
        echo "添加失败，可能是已存在了";
    }

//    if (pdo_query($sql,$passwd,$user_id)==1) echo "Password Changed!";
//    else echo "No such user! or He/Her is an administrator!";
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
                            <h4 class="modal-title text-center">添加用户</h4>
                        </div>
                        <form action='adduser.php' method=post>
                            <div class="modal-body" id = "model-body">
                                <div class="form-group">
                                    <input type=text size=10 name="user_id" class="form-control" placeholder="学号" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="nick" class="form-control" placeholder="名字" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="school" class="form-control" placeholder="班级" autocomplete="off">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="form-group">
                                    <?php require_once("../include/set_post_key.php");?>
                                    <input type='hidden' name='do' value='do'>
                                    <input type=submit value='添加' class="btn btn-primary form-control">
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
    document.getElementById('a6').classList.remove("bg-primary");
    document.getElementById('a6').classList.add("bg-secondary");
</script>
</body>
