<?php
require_once("admin-header.php");
if(!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))){
  echo "<a href='../loginpage.php'>Please Login First!</a>";
  exit(1);
}

include_once("kindeditor.php");
?>

<?php
if(isset($_GET['cid'])){
  $cid = intval($_GET['cid']);
  $sql = "SELECT * FROM news WHERE `news_id`=?";
  $result = pdo_query($sql,$cid);
  $row = $result[0];
  $title = $row['title'];
  $content = $row['content'];
  $defunct = $row['defunct'];
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
            <?php echo "<center><h3>$MSG_ADD"."$MSG_NEWS</h3></center>";?>
            <section class="content">
                <div class="container-fluid">
                    <form method=POST action=news_add.php>
                        <p align=left>
                            <?php echo $MSG_TITLE?>:<input type=text name=title size=71 value='<?php echo isset($title)?$title."-Copy":""?>'>
                        </p>
                        <p align=left>
                          <textarea class=kindeditor name=content>
                            <?php echo isset($content)?$content:""?>
                          </textarea>
                        </p>
                        <p>
                        <center>
                            <input type=submit value=Submit name=submit>
                        </center>
                        </p>
                        <?php require_once("../include/set_post_key.php");?>
                    </form>
                </div>
            </section>
        </div>
    </div>
    <script>
        document.getElementById('menu1').classList.remove("menu");
        document.getElementById('menu1').classList.add("menu-open");
        document.getElementById('a3').classList.remove("bg-primary");
        document.getElementById('a3').classList.add("bg-secondary");
    </script>
</body>
