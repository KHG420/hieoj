<?php require_once("admin-header.php");

	if(isset($OJ_LANG)){
		require_once("../lang/$OJ_LANG.php");
	}
	

?>
<html>
<head>
<title><?php echo $MSG_ADMIN?></title>
</head>

<body>
	<h3><?php echo $MSG_HELP?></h3>
<hr>
<h4>
<ul>

  <li><a class='btn btn-primary' href="../status.php" target="_top"><b><?php echo $MSG_SEEOJ?></b></a><?php echo $MSG_HELP_SEEOJ?>
  <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
  <li><a class='btn btn-primary' href="setmsg.php" target="main"><b><?php echo $MSG_SETMESSAGE?></b></a><?php echo $MSG_HELP_SETMESSAGE?>
  <li><a class='btn btn-primary' href="news_list.php" target="main"><b><?php echo $MSG_NEWS.$MSG_LIST?></b></a><?php echo $MSG_HELP_NEWS_LIST?>
  <li><a class='btn btn-primary' href="news_add_page.php" target="main"><b><?php echo $MSG_ADD.$MSG_NEWS?></b></a><?php echo $MSG_HELP_ADD_NEWS?>
  <li><a class='btn btn-primary' href="user_list.php" target="main"><b><?php echo $MSG_USER.$MSG_LIST?></b></a><?php echo $MSG_HELP_USER_LIST?>
  <li><a class='btn btn-primary' href="user_set_ip.php" target="main"><b><?php echo $MSG_SET_LOGIN_IP?></b></a><?php echo $MSG_SET_LOGIN_IP?>
    <li><a class='btn btn-primary' href="adduser.php" target="main" title="添加用户"><b>添加用户*</b></a>添加新用户，初始密码为123456
    <li><a class='btn btn-primary' href="categorylist.php" target="main" title="添加用户"><b>分类列表*</b></a>添加或调整问题分类
    <li><a class='btn btn-primary' href="user_modify.php" target="main" style="position:relative;"><b>审核用户申请*</b>
            <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
                $sql = "SELECT COUNT(id) as num FROM `modify` WHERE `status`=0";
                $result = pdo_query($sql);
                $num = $result[0]['num'];
                if ($num > 0) {
                    echo "<div style='background-color: red; color: white; position:absolute; top:-10px; right:-10px; border-radius: 50px; width: 20px;height: 20px;'>"
                        ."<div style='text-align: center; line-height: 20px;'>"
                        .$num
                        ."</div>"
                        ."</div>";
                }
            } ?>
        </a>审核用户提交的信息修改申请
    <li><a class='btn btn-primary' href="user_register.php" target="main" style="position:relative;"><b>用户注册申请*</b>
            <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
                $sql = "SELECT COUNT(*) as num FROM `users` WHERE `register_num`=0";
                $result = pdo_query($sql);
                $num = $result[0]['num'];
                if ($num > 0) {
                    echo "<div style='background-color: red; color: white; position:absolute; top:-10px; right:-10px; border-radius: 50px; width: 20px;height: 20px;'>"
                        ."<div style='text-align: center; line-height: 20px;'>"
                        .$num
                        ."</div>"
                        ."</div>";
                }
            } ?>
        </a>审核用户提交的注册申请
        <?php }
  if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset( $_SESSION[$OJ_NAME.'_'.'password_setter'] )){?>
  <li><a class='btn btn-primary' href="changepass.php" target="main"><b><?php echo $MSG_SETPASSWORD?></b></a><?php echo $MSG_HELP_SETPASSWORD?>
  <?php }
  if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
  <li><a class='btn btn-primary' href="source_give.php" target="main"><b><?php echo $MSG_GIVESOURCE?></b></a><?php echo $MSG_HELP_GIVESOURCE?>
  <li><a class='btn btn-primary' href="privilege_list.php" target="main"><b><?php echo $MSG_PRIVILEGE.$MSG_LIST?></b></a><?php echo $MSG_HELP_PRIVILEGE_LIST?>
  <li><a class='btn btn-primary' href="privilege_add.php" target="main"><b><?php echo $MSG_ADD.$MSG_PRIVILEGE?></b></a><?php echo $MSG_HELP_ADD_PRIVILEGE?>
  <?php }
  if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])||isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])){?>
  <li><a class='btn btn-primary' href="problem_list.php" target="main"><b><?php echo $MSG_PROBLEM.$MSG_LIST?></b></a><?php echo $MSG_HELP_PROBLEM_LIST?>
  <?php }
  if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])){?>
  <li><a class='btn btn-primary' href="problem_add_page.php" target="main"><b><?php echo $MSG_ADD.$MSG_PROBLEM?></b></a><?php echo $MSG_HELP_ADD_PROBLEM?>
  <?php }
  if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
  <li><a class='btn btn-primary' href="problem_import.php" target="main"><b><?php echo $MSG_IMPORT.$MSG_PROBLEM?></b></a><?php echo $MSG_HELP_IMPORT_PROBLEM?>
  <li><a class='btn btn-primary' href="problem_export.php" target="main"><b><?php echo $MSG_EXPORT.$MSG_PROBLEM?></b></a><?php echo $MSG_HELP_EXPORT_PROBLEM?>
  <?php }?>
  <li><a class='btn btn-primary' href="https://github.com/zhblue/freeproblemset/" target="_blank"><b>FreeProblemSet</b></a>
  <?php
  if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])){?>		
  <li><a class='btn btn-primary' href="contest_list.php" target="main"><b><?php echo $MSG_CONTEST.$MSG_LIST?></b></a><?php echo $MSG_HELP_CONTEST_LIST?>
  <li><a class='btn btn-primary' href="contest_add.php" target="main"><b><?php echo $MSG_ADD.$MSG_CONTEST?></b></a><?php echo $MSG_HELP_ADD_CONTEST?>
    <li><a class='btn btn-primary' href="add_contest_achieve.php" target="main"><b>添加比赛成就*</b></a>添加比赛成就
        <?php }
  if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
  <li><a class='btn btn-primary' href="team_generate.php" target="main"><b><?php echo $MSG_TEAMGENERATOR?></b></a><?php echo $MSG_HELP_TEAMGENERATOR?>
  <?php }
  if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
  <li><a class='btn btn-primary' href="rejudge.php" target="main"><b><?php echo $MSG_REJUDGE?></b></a><?php echo $MSG_HELP_REJUDGE?>
  <?php }?>
  <li><a class='btn btn-primary' href="https://github.com/zhblue/hustoj/" target="_blank"><b>HUSTOJ</b></a>
  <?php
  if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
  <li><a class='btn btn-primary' href="update_db.php" target="main"><b><?php echo $MSG_UPDATE_DATABASE?></b></a><?php echo $MSG_HELP_UPDATE_DATABASE?>
  <?php }
  if (isset($OJ_ONLINE)&&$OJ_ONLINE){?>
  <li><a class='btn btn-primary' href="../online.php" target="main"><b><?php echo $MSG_ONLINE?></b></a><?php echo $MSG_HELP_ONLINE?>
  <?php }?>
  <li><a class='btn btn-primary' href="http://tk.hustoj.com" target="_blank"><b>自助题库</b></a>
  <li><a class='btn btn-primary' href="http://shang.qq.com/wpa/qunwpa?idkey=d52c3b12ddaffb43420d308d39118fafe5313e271769277a5ac49a6fae63cf7a" target="_blank">手机QQ加官方群23361372</a>
	
</ul>
<?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])&&!$OJ_SAE){
?>
	<a href="problem_copy.php" target="main" title="Create your own data"><font color="eeeeee">CopyProblem</font></a> <br>
	<a href="problem_changeid.php" target="main" title="Danger,Use it on your own risk"><font color="eeeeee">ReOrderProblem</font></a>
   
<?php }
?>
<h4>
</body>
</html>
