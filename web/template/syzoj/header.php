<?php
$show_title = isset($show_title) ? $show_title : ''; // 若未传递，默认空字符串
$url=basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
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

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="utf-8">
    <meta content="IE=edge" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=1200">
    <title><?php echo $show_title ?></title>
    <?php include("template/$OJ_TEMPLATE/css.php");?>
    <script src="<?php echo $OJ_CDN_URL?>/include/jquery-latest.js"></script>
</head>
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
<body>
<div class="ui borderless menu" style="position: sticky; top: 0; z-index: 100; height: 49px; font-size: 100%;">
    <div class="ui container" >
        <a class="header item" href="index.php" style="padding: 0 0.82vw 0 0.82vw;"><span
                    style="font-family: 'Exo 2'; font-size: 1.5em; font-weight: 600;"><?php echo $OJ_NAME?></span></a>
        <a class="item <?php if ($url=="index.php"||$url=="index1.php") echo "active";?>" href="index.php"  style="padding: 0 0.82vw 0 0.82vw;"><i class="home icon"></i> <?php echo $MSG_HOME?></a>
        <a class="item <?php if ($url=="problemset.php") echo "active";?>"
	   href="<?php echo $path_fix?>problemset.php"  style="padding: 0 0.82vw 0 0.82vw;"><i class="list icon"></i><?php echo $MSG_PROBLEMS?> </a>
	<a class="item <?php if ($url=="category.php") echo "active";?>"
           href="<?php echo $path_fix?>category.php"  style="padding: 0 0.82vw 0 0.82vw;"><i class="globe icon"></i><?php echo $MSG_SOURCE?></a>
        <a class="item <?php if ($url=="contest.php") echo "active";?>" href="<?php echo $path_fix?>contest.php"   style="padding: 0 0.82vw 0 0.82vw;"><i
                    class="calendar icon"></i> <?php echo $MSG_CONTEST?></a>
        <a class="item <?php if ($url=="status.php") echo "active";?>" href="<?php echo $path_fix?>status.php"  style="padding: 0 0.82vw 0 0.82vw;"><i
                    class="tasks icon"></i><?php echo $MSG_STATUS?></a>
        <a class="item <?php if ($url=="ranklist.php") echo "active";?>"
           href="<?php echo $path_fix?>ranklist.php"  style="padding: 0 0.82vw 0 0.82vw;"><i class="signal icon"></i> <?php echo $MSG_RANKLIST?></a>
	<a class="item <?php if ($url=="knowledge_graph.html") echo "active";?>"
           href="<?php echo $path_fix?>knowledge_graph.html"  style="padding: 0 0.82vw 0 0.82vw;"><i class="list icon"></i><?php echo "知识图谱"?> </a>	

<!-- <a class="item <?php if ($url=="acmerlist.php") echo "active";?>" -->
           <!-- href="<?php echo $path_fix?>acmerlist.php"  style="padding: 0 0.82vw 0 0.82vw;"><i class="signal icon"></i> <?php echo "测试"?></a> -->
        <!-- <a class="item <?php //if ($url=="contest.php") echo "active";?>" href="/discussion/global"><i class="comments icon"></i> 讨论</a> -->
        <a class="item <?php if ($url=="faqs.php"||$url=="faqs.cn.php") echo "active";?>" href="<?php echo $path_fix?>faqs.php"  style="padding: 0 0.82vw 0 0.82vw;"><i
                    class="help circle icon"></i> <?php echo $MSG_FAQ?></a>

        <?php if (isset($OJ_BBS)&& $OJ_BBS){ ?>
            <a class='item <?php if ($url=="discuss.php") echo "active";?>' href="<?php echo $path_fix?>discuss.php"  style="padding: 0 0.82vw 0 0.82vw;"><i class="clipboard icon"></i> <?php echo $MSG_BBS?></a>
        <?php }?>
        <?php if(isset($_GET['cid'])){
            $cid=intval($_GET['cid']);
            ?>
            <a id="back_to_contest" class="item active" href="<?php echo $path_fix?>contest.php?cid=<?php echo $cid?>"><i
                        class="arrow left icon"></i><?php echo $MSG_CONTEST.$MSG_PROBLEMS.$MSG_LIST?></a>
        <?php }?>
        <div class="right menu" style="position:relative;">
            <?php if(isset($_SESSION[$OJ_NAME.'_'.'user_id'])) { ?>
                <!--                <a href="--><?php //echo $path_fix?><!--userinfo.php?user=--><?php //echo $_SESSION[$OJ_NAME.'_'.'user_id']?><!--"-->
                <!--                    style="color: inherit; ">-->
                <div class="ui simple dropdown item">
                    <?php echo $_SESSION[$OJ_NAME.'_'.'user_id']; ?>
                    <i class="dropdown icon"></i>
                    <div class="menu">
                        <a class="item" href="<?php echo $path_fix?>userinfo.php?user=<?php echo $_SESSION[$OJ_NAME.'_'.'user_id']?>"><i
                                    class="user icon"></i>我的主页</a>
                        <a class="item" href="<?php echo $path_fix?>status.php?user_id=<?php echo $_SESSION[$OJ_NAME.'_'.'user_id']?>"><i
                                    class="file icon"></i>我的提交</a>
                        <a class="item" href="<?php echo $path_fix?>modifypage.php"><i
                                    class="edit icon"></i><?php echo $MSG_REG_INFO;?></a>
                        <?php if ($OJ_SaaS_ENABLE){ ?>
                            <?php if($_SERVER['HTTP_HOST']==$DOMAIN)
                                echo  "<a class='item' href='http://".  $_SESSION[$OJ_NAME.'_'.'user_id'].".$DOMAIN'><i class='globe icon' ></i>MyOJ</a>";?>
                        <?php } ?>
                        <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])||isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])){ ?>
                            <a class="item" href="admin/" style="position:relative;"><i class="settings icon"></i><?php echo $MSG_ADMIN;?>
                                <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
                                    // 性能优化：徽标计数缓存 10 秒，避免管理员每页 2 次 COUNT
                                    require_once(dirname(__FILE__)."/../../include/cache_layer.php");
                                    $num = cache_get("admin_badge_".$OJ_NAME);
                                    if($num===false){
                                        $sql = "SELECT COUNT(id) as num FROM `modify` WHERE `status`=0";
                                        $num_result = pdo_query($sql);
                                        $num = $num_result[0]['num'];
                                        $sql = "SELECT COUNT(*) as num FROM `users` WHERE `register_num`=0";
                                        $num_result = pdo_query($sql);
                                        $num += $num_result[0]['num'];
                                        cache_set("admin_badge_".$OJ_NAME, $num, 10);
                                    }
                                    if ($num > 0) {
                                        echo "<div style='background-color: red; color: white; position:absolute; top:0px; right:15px; border-radius: 50px; width: 15px;height: 15px;'>"
                                            ."<div style='text-align: center; line-height: 15px;'>"
                                            .$num
                                            ."</div>"
                                            ."</div>";
                                    }
                                } ?>
                            </a>
                        <?php } ?>
                        <form method="post" action="<?php echo $path_fix?>logout.php" style="display:block;margin:0">
                            <?php require_once($path_fix.'include/set_post_key.php');?>
                            <button type="submit" class="item" style="background:none;border:none;width:100%;text-align:left;cursor:pointer;font-size:inherit"><i class="power icon"></i><?php echo $MSG_LOGOUT;?></button>
                        </form>
                    </div>
                </div>
                </a>
            <?php } else { ?>


                <div class="item">
                    <a class="ui button" style="margin-right: 0.5em; " href="loginpage.php">
                        <?php echo $MSG_LOGIN?>
                    </a>
                    <?php if(isset($OJ_REGISTER)&&$OJ_REGISTER ){ ?>
                        <a class="ui primary button" href="registerpage.php">
                            <?php echo $MSG_REGISTER?>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
                if ($num > 0) {
                    echo "<div style='background-color: red; color: white; position:absolute; top:1px; right:15px; border-radius: 50px; width: 20px;height: 20px;'>"
                        ."<div style='text-align: center; line-height: 20px;'>"
                        .$num
                        ."</div>"
                        ."</div>";
                }
            } ?>
        </div>
    </div>
</div>
<div id="marg">
    <div class="ui main container" id="main_container">
