<?php require("admin-header.php");
if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
}?>
<?php if(isset($_POST['prefix'])){
    require_once("../include/check_post_key.php");
    $prefix=$_POST['prefix'];
    require_once("../include/my_func.inc.php");
    if (!is_valid_user_name($prefix)){
        echo "Prefix is not valid.";
        exit(0);
    }
    $teamnumber=intval($_POST['teamnumber']);
    $pieces = explode("\n", trim($_POST['ulist']));

    if ($teamnumber>0){
        for($i=1;$i<=$teamnumber;$i++){

            $user_id=$prefix.($i<10?('0'.$i):$i);
            $password=strtoupper(substr(MD5($user_id.rand(0,9999999)),0,10));
            if(isset($pieces[$i-1]))
                $nick=$pieces[$i-1];
            else
                $nick="your_own_nick";
            if($teamnumber==1) $user_id=$prefix;
            $password1 = $password;
            $password=pwGen($password);
            $email="your_own_email@internet";

            $school="your_own_school";
            $ip = ($_SERVER['REMOTE_ADDR']);
            if( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
                $REMOTE_ADDR = $_SERVER['HTTP_X_FORWARDED_FOR'];
                $tmp_ip=explode(',',$REMOTE_ADDR);
                $ip =(htmlentities($tmp_ip[0],ENT_QUOTES,"UTF-8"));
            }
            $sql="INSERT INTO `users`("."`user_id`,`email`,`ip`,`accesstime`,`password`,`reg_time`,`nick`,`school`)".
                "VALUES(?,?,?,NOW(),?,NOW(),?,?)on DUPLICATE KEY UPDATE `email`=?,`ip`=?,`accesstime`=NOW(),`password`=?,`reg_time`=now(),nick=?,`school`=?";
            pdo_query($sql,$user_id,$email,$ip,$password,$nick,$school,$email,$ip,$password,$nick,$school) ;
        }
    }
}
?>
<body class="hold-transition sidebar-mini layout-fixed">
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
                            <h4 class="modal-title text-center">TeamGenerator</h4>
                        </div>
                        <form action='team_generate.php' method=post>
                            <?php require_once("../include/set_post_key.php");?>
                            <div class="modal-body" id = "model-body">
                                <div class="form-group">
                                    <input type='test' name='prefix'  class="form-control" placeholder="队伍名称" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input class="input-mini form-control" type=input name='teamnumber' size=3 placeholder="队伍数量" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <textarea name="ulist" rows="10" cols="100" placeholder="Preset nicknames of the teams. One name per line." style="width: 100%;"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="form-group">
                                    <input type=submit value=Generate class="btn btn-primary form-control">
                                </div>
                            </div>
                        </form>
                        <?php if(isset($_POST['prefix'])){
                            $pieces = explode("\n", trim($_POST['ulist']));
                            echo "<div class='card'>";
                            echo "<div class='card-body'>";
                            if ($teamnumber>0){
                                echo "<table border=1>";
                                echo "<tr><td colspan=3>Copy these accounts to distribute</td></tr>";
                                echo "<tr><td>team_name<td>login_id</td><td>password</td></tr>";
                                for($i=1;$i<=$teamnumber;$i++){
                                    echo "<tr><td>$nick<td>$user_id</td><td>$password1</td></tr>";
                                }
                                echo  "</table>";
                            }
                            echo "</div>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
    document.getElementById('menu4').classList.remove("menu");
    document.getElementById('menu4').classList.add("menu-open");
    document.getElementById('a21').classList.remove("bg-primary");
    document.getElementById('a21').classList.add("bg-secondary");
</script>
</body>
