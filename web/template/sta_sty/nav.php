<?php 
	$url=basename($_SERVER['REQUEST_URI']);
	$dir=basename(getcwd());
	if($dir=="discuss3") $path_fix="../";
	else $path_fix="";
 	if(isset($OJ_NEED_LOGIN)&&$OJ_NEED_LOGIN&&(
                  $url!='loginpage.php'&&
                  $url!='lostpassword.php'&&
                  $url!='lostpassword2.php'&&
                  $url!='registerpage.php'
                  ) && !isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
 
           header("location:".$path_fix."loginpage.php");
           exit();
        }

	if($OJ_ONLINE){
		require_once($path_fix.'include/online.php');
		$on = new online();
	}
?>
<link rel="stylesheet" href="./template/sta_sty/layui/css/layui.css">
<script src="./template/sta_sty/layui/layui.js"></script>
<?php
if ($OJ_WHITE_BLACK) { ?>
    <style>
        html {
            filter: grayscale(100%);
            -webkit-filter: grayscale(100%);
            -moz-filter: grayscale(100%);
            -ms-filter: grayscale(100%);
            -o-filter: grayscale(100%);
            -webkit-filter: grayscale(1);
        }

    </style>
<?php } ?>
<ul class="layui-nav" lay-filter="" lay-shrink="all">
		<li class="layui-nav-item">
			<a class="navbar" style="font-size:18px;" href="<?php echo $OJ_HOME?>"><?php echo $OJ_NAME?></a>
		</li>
		<?php $ACTIVE="layui-this"?>
		<?php if(!isset($OJ_ON_SITE_CONTEST_ID)){?>
		<li class="layui-nav-item<?php if ($url=="index1.php" ) echo " $ACTIVE" ;?>">
			<a class="navbar" href="<?php echo $path_fix?>index1.php">
				<i class="layui-icon layui-icon-survey" style="font-size: 18px;"> </i>
				主页
			</a></li>
		<li class="layui-nav-item<?php if ($dir=="discuss3" ) echo " $ACTIVE" ;?>">
			<a class="navbar" href="<?php echo $path_fix?>discuss1.php<?php if (isset($_GET['cid'])) echo " ?cid=".intval($_GET['cid']); ?>">
				<i class="layui-icon layui-icon-dialogue" style="font-size: 18px;"> </i>
				<?php echo $MSG_BBS?>
			</a></li>
		<?php }else{?>
		<li class="layui-nav-item<?php if ($dir=="discuss3" ) echo " $ACTIVE" ;?>">
			<a href="<?php echo $path_fix?>discuss1.php<?php echo "?cid=".intval($OJ_ON_SITE_CONTEST_ID); ?>">
				<i class="layui-icon layui-icon-dialogue" style="font-size: 18px;"> </i>
				<?php echo $MSG_BBS?>
			</a></li>
		<?php }?>
	
		<?php if (isset($OJ_PRINTER)&& $OJ_PRINTER){ ?>
		<li class="layui-nav-item<?php if ($url=="printer.php" ) echo " $ACTIVE" ;?>">
			<a href="<?php echo $path_fix?>printer.php">
				<i class="layui-icon layui-icon-print" style="font-size: 18px;"> </i>
				<?php echo $MSG_PRINTER?>
			</a></li>
		<?php }?>
		<?php if(!isset($OJ_ON_SITE_CONTEST_ID)){?>
		<li class="layui-nav-item<?php if ($url=="problemset.php" ) echo " $ACTIVE" ;?>">
			<a href="<?php echo $path_fix?>problemset.php">
				<i class="layui-icon layui-icon-read" style="font-size: 18px;"> </i>
				<?php echo $MSG_PROBLEMS?>
			</a></li>
		<li class="layui-nav-item<?php if ($url=="category.php" ) echo " $ACTIVE" ;?>">
			<a href="<?php echo $path_fix?>category.php">
				<i class="layui-icon layui-icon-template-1" style="font-size: 18px;"> </i>
				<?php echo $MSG_SOURCE?>
			</a></li>
		<li class="layui-nav-item<?php if ($url=="status.php" ) echo " $ACTIVE" ;?>">
			<a href="<?php echo $path_fix?>status.php">
				<i class="layui-icon layui-icon-file-b" style="font-size: 18px;"> </i>
				<?php echo $MSG_STATUS?>
			</a></li>
		<li class="layui-nav-item<?php if ($url=="ranklist.php" ) echo " $ACTIVE" ;?>">
			<a href="<?php echo $path_fix?>ranklist.php">
				<i class="layui-icon layui-icon-group" style="font-size: 18px;"> </i>
				<?php echo $MSG_RANKLIST?>
			</a></li>
		<li class="layui-nav-item<?php if ($url=="contest.php" ) echo " $ACTIVE" ;?>">
			<a href="<?php echo $path_fix?>contest.php">
				<i class="layui-icon layui-icon-transfer" style="font-size: 18px;"> </i>
				<?php echo $MSG_CONTEST?>
			</a></li>
		<?php }else{?>
		<li class="layui-nav-item<?php if ($url=="contest.php" ) echo " $ACTIVE" ;?>">
			<a href="<?php echo $path_fix?>contest.php<?php echo "?cid=".intval($OJ_ON_SITE_CONTEST_ID); ?>">
				<i class="layui-icon layui-icon-transfer" style="font-size: 18px;"> </i>
				<?php echo $MSG_CONTEST?>
			</a>
		<?php }?>
		<?php if(isset($_GET['cid'])){
	$cid=intval($_GET['cid']);
	?>
			<dl class="layui-nav-child">
				<!-- 二级菜单 -->
				<dd><a href="<?php echo $path_fix?>contest.php?cid=<?php echo $cid?>"><?php echo $MSG_PROBLEMS?></a></dd>
				<dd><a href="<?php echo $path_fix?>status.php?cid=<?php echo $cid?>"><?php echo $MSG_STATUS?></a></dd>
				<dd><a href="<?php echo $path_fix?>contestrank.php?cid=<?php echo $cid?>"><?php echo $MSG_RANKLIST?></a></dd>
				<dd><a href="<?php echo $path_fix?>contestrank-oi.php?cid=<?php echo $cid?>">OI<?php echo $MSG_RANKLIST?></a></dd>
				<dd><a href="<?php echo $path_fix?>conteststatistics.php?cid=<?php echo $cid?>"><?php echo $MSG_STATISTICS?></a></dd>
			</dl>
		</li>
		<?php }?>
	</ul>
	<ul class="layui-nav layui-layout-right">
		<li class="layui-nav-item">
			<a href="javascript:;"><span id="profile">Login</span><span class="caret"></span></a>
			<dl class="layui-nav-child">
				<script src="<?php echo $path_fix." template/sta_sty/profile.php?".rand();?>" ></script>
			</dl>
		</li>
	</ul>
