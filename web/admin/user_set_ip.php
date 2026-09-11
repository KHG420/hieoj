<?php require("admin-header.php");

        if(isset($OJ_LANG)){
                require_once("../lang/$OJ_LANG.php");
        }

echo "<title>User List</title>";?>
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
                            <h4 class="modal-title text-center"><?php echo "$MSG_SET_LOGIN_IP" ?></h4>
                        </div>
                        <?php
                        require_once("../include/set_get_key.php");
                        $sql="";
                        if(isset($_POST['user_id'])){
                            require_once("../include/check_post_key.php");
                            $user_id=$_POST['user_id'];
                            $ip=$_POST['ip'];
                            $sql="insert into loginlog (user_id,password,ip,time) value(?,?,?,now())";
                            $result=pdo_query($sql,$user_id,"set ip by ".$_SESSION[$OJ_NAME."_user_id"],$ip);
                            echo "$MSG_USER ".htmlentities($user_id)." $MSG_SET_LOGIN_IP : ".htmlentities($ip);
                        }
                        ?>
                        <form action=user_set_ip.php class=center method="post">
                            <div class="modal-body" id = "model-body">
                                <div class="form-group">
                                    <input type="text" name="user_id" class="form-control"placeholder="用户" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type="text" name="ip" class="form-control"placeholder="IP" autocomplete="off">
                                </div>
                                <?php require_once("../include/set_post_key.php");?>
                            </div>
                            <div class="modal-footer">
                                <div class="form-group">
                                    <input type=submit value="<?php echo $MSG_ADD?>" class="btn btn-primary form-control">
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
        document.getElementById('a5').classList.remove("bg-primary");
        document.getElementById('a5').classList.add("bg-secondary");
    </script>
</body>
<?php
require("../oj-footer.php");
?>
