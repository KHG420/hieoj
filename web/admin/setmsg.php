<?php
require_once("admin-header.php");
if(!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))){
  echo "<a href='../loginpage.php'>Please Login First!</a>";
  exit(1);
}


if(isset($_POST['do'])){
  require_once("../include/check_post_key.php");

  $fp = fopen($OJ_SAE?"saestor://web/msg.txt":"msg.txt","w");
  $msg = $_POST['msg'];

  $msg = str_replace("<p>", "", $msg);
  $msg = str_replace("</p>", "<br />", $msg);
  $msg = str_replace(",", "&#44;", $msg);

  if((function_exists('get_magic_quotes_gpc')&&get_magic_quotes_gpc())){
    $title = stripslashes($title);
  }

  $msg = RemoveXSS($msg);
  fputs($fp,$msg);
  fclose($fp);
  $massage = "Update At ".date('Y-m-d h:i:s');
  echo "<script>alert('$massage')</script>";
}

$msg = file_get_contents($OJ_SAE?"saestor://web/msg.txt":"msg.txt");

include("kindeditor.php");
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
                    <?php echo "<hr>";
                    echo "<center><h3>$MSG_SETMESSAGE</h3></center>";?>
                    <center>
                        <form action='setmsg.php' method='post' >
                            <textarea name='msg' rows=25 class="kindeditor"><?php echo $msg?></textarea><br>
                            <input type='hidden' name='do' value='do'>
                            <center><input type='submit' value='Save'></center>
                            如果升级无法修改公告，发送“修改公告”到微信公众号onlinejudge看解决方案。<br>
                            if this does not work, try run "sudo chown -R www-data /home/judge/src/web " in terminal.
                            <?php require_once("../include/set_post_key.php");?>
                        </form>
                    </center>
                </div>
            </section>
        </div>
    </div>
</body>
<script>
    document.getElementById('menu1').classList.remove("menu");
    document.getElementById('menu1').classList.add("menu-open");
    document.getElementById('a1').classList.remove("bg-primary");
    document.getElementById('a1').classList.add("bg-secondary");
</script>
<?php require_once('../oj-footer.php'); ?>
