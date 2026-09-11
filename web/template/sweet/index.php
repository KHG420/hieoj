<?php
$cur_path = "template/sweet/";
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
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="../../favicon.ico">

    <title><?php echo $OJ_NAME?></title>
      <link rel="stylesheet" href="<?php echo $cur_path?>css/style.css">

<!--      --><?php //include("template/$OJ_TEMPLATE/css.php");?>
<!--      <link rel="stylesheet" href="./css/style.css">-->

<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
<!--[if lt IE 9]>
<!--<script src="http://cdn.bootcss.com/html5shiv/3.7.0/html5shiv.js"></script>-->
<!--<script src="http://cdn.bootcss.com/respond.js/1.4.2/respond.min.js"></script>-->
<!--<![endif]-->
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

<div class=wrap id=wrap>
    <div class=wrapper>
        <div class=header>
            <div class="head clearfix">
                <div class=logo_box>
                    <h1 style="color:White;">HNIEOJ</h1>
                    <a href='javascript:;' class=logo_link></a>
                </div>
                <div class=nav_box id=nav_box>
                    <ul>
                        <li>
                            <a href='index1.php' clickid=guanwang_navigation_homepage>主页</a>
                        </li>
                        <li>
                            <a href='<?php echo $path_fix?>problemset.php' clickid=guanwang_navigation_productcenter>问题</a>
                        </li>
                        <li>
                            <a href='<?php echo $path_fix?>category.php' clickid=guanwang_navigation_customer>分类</a>
                        </li>
                        <li>
                            <a href='<?php echo $path_fix?>contest.php' clickid=guanwang_navigation_customer>竞赛</a>
                        </li>
                        <li>
                            <a href='<?php echo $path_fix?>status.php' clickid=guanwang_navigation_yangtai>状态</a>
                        </li>
                        <li>
                            <a href='<?php echo $path_fix?>ranklist.php' clickid=guanwang_navigation_yangtai>排名</a>
			</li>
			<li>
                            <a href='<?php echo $path_fix?>knowledge_graph.html' clickid=guanwang_navigation_yangtai>知识图谱</a>
                        </li>
                        <li>
                            <a href='<?php echo $path_fix?>blog.php' clickid=guanwang_navigation_yangtai>博客</a>
                        </li>
                    </ul>
                    <span class=ic_line></span></div>
            </div>
        </div>

        <?php if ($OJ_CONTEST_INDEX) {?>
            <div style="position:absolute;top:18%;left:5%;z-index:99999; color: white; font-size: 6.5vw;">
                <div style="text-align: center"><?php echo $OJ_CONTEST_INDEX_HEADLINE_1?></div>
                <div style="text-align: center"><?php echo $OJ_CONTEST_INDEX_HEADLINE_2?></div>
            </div>
            <div style="position:absolute;top:68%;left:64%;z-index:99999; color: white; font-size: 3.0vw; text-align: right;">
                <div><?php echo $OJ_CONTEST_INDEX_SPONSOR_1?></div>
                <div><?php echo $OJ_CONTEST_INDEX_SPONSOR_2?></div>
                <div><?php echo $OJ_CONTEST_INDEX_TIME?></div>
            </div>
        <?php }?>

        <div class=page_wp id=page_wp>
            <div class="page page_1"><span class="page_bg scale_box"></span>
                <div class=img_box><img src="" alt=""></div>
                <div class="txt_box scale_box">
                    <h2><?php echo $OJ_NAME?> OnlineJudge</h2>
                    <p class=txt_brief>从现在开始，你的大学生活将是如此的不同。巨大的脑洞，丰富的题库，全面的测试用例，全新的视野，这一切，都让你的 ACMer 生活更加精彩。</div>
            </div>

            <div class="page page_2"><span class="page_bg scale_box"></span>
                <div class=img_box><img src="" alt=""></div>
                <div class="txt_box scale_box">
                    <h2>欢迎加入ACM实验室</h2>
                    <p class=txt_brief>ACM不是为了一场比赛而存在的，而是为了团队的整体提高。</div>
            </div>

            <div class="page page_3"><span class="page_bg scale_box"></span>
                <div class=img_box><img src="" alt=""></div>
                <div class="txt_box scale_box">
                    <h2>OJ 也要酷一点</h2>
                     <p class=txt_brief>这是你们自己的 OJ，你有任何酷酷的想法，尽管写在讨论区，我们将共同为努力，搭建最舒适家园。为酷酷的青春、酷酷的梦想、干杯。</div>
                </div>
                <div class="page page_4"><span class="page_bg scale_box"></span>
                    <div class=img_box><img src="" alt=""></div>
                    <div class="light_wp scale_box"><span class=light_box><i class=light_1></i> <i class=light_2></i> <i class=light_3></i> <i class=light_4></i> <i class=light_5></i></span></div><span class="meteor_box scale_box"></span>
                    <div class="txt_box scale_box">
                        <h2>还有另一个自己在这里等你</h2>
                        <p class=txt_brief>也许另一个人也和你一样，发现着自己的创新和诚心。</p>
                    </div>
                </div>
            </div>

            <div class="star_wp scale_box" id=star_wp><span class=star_bg></span> <span class=star_box></span></div><canvas id=canvas></canvas>
            <div class="btns_wp scale_box">
                <?php if ($OJ_CONTEST_INDEX) {?>
                    <a class="btn_download JS-btn-download" href='<?php echo $path_fix?>contest.php'>进入比赛</a>
                <?php }else{?>

                    <?php
                    if (isset($_SESSION[$OJ_NAME.'_'.'user_id'])){?>

                        <a class="btn_download JS-btn-download" href='<?php echo $path_fix?>problemset.php'>练习</a>

                    <?php }else{ ?>
                    <a class="btn_download JS-btn-download" href='<?php echo $path_fix?>loginpage.php'>登录</a>
                    <?php } ?>
                <?php } ?>
            </div>


            <div class=btn_control id=btn_control>
                <a href=javascript:; class=cur></a>
                <a href=javascript:;></a>
                <a href=javascript:;></a>
                <a href=javascript:;></a>
            </div>
            			<div class=footer>
                            <div class=foot>
                                <p>© 2018-<?php echo date('Y') ?> HNIEACM 版权所有&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
                                    <a href="https://beian.miit.gov.cn/" style="text-decoration: none; color: #444444;"
                                       target="_blank"><?php echo $OJ_BEIAN; ?></a>
                            </div>
                        </div>
        </div>
    </div>

<!--<div class="container">-->
<!--    --><?php //include("template/$OJ_TEMPLATE/nav.php");?>
<!--    <!-- Main component for a primary marketing message or call to action -->-->
<!--    <div class="jumbotron">-->
<!--        <p>-->
<!--        <center> 最近提交记录 : --><?php //echo $speed?><!-- .-->
<!--            <div id=submission style="width:80%;height:300px" ></div>-->
<!--        </center>-->
<!--        </p>-->
<!--        --><?php //echo $view_news?>
<!--    </div>-->
<!---->
<!--</div> <!-- /container -->-->

<!--<script src="./js/vendors.js"></script>-->
<!--<script src="./js/index.js"></script>-->

<!-- Bootstrap core JavaScript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
<?php //include("template/$OJ_TEMPLATE/js.php");?>
<!--<script language="javascript" type="text/javascript" src="--><?php //echo $OJ_CDN_URL?><!--include/jquery.flot.js"></script>-->
<!--<script type="text/javascript">-->
<!--    $(function () {-->
<!--        var d1 = --><?php //echo json_encode($chart_data_all)?>//;
<!--//        var d2 = --><?php ////echo json_encode($chart_data_ac)?><!--//;-->
<!--//        $.plot($("#submission"), [-->
<!--//            {label:"--><?php ////echo $MSG_SUBMIT?><!--//",data:d1,lines: { show: true }},-->
<!--//            {label:"--><?php ////echo $MSG_AC?><!--//",data:d2,bars:{show:true}} ],{-->
<!--//            grid: {-->
<!--//                backgroundColor: { colors: ["#fff", "#eee"] }-->
<!--//            },-->
<!--//            xaxis: {-->
<!--//                mode: "time"//,-->
<!--////max:(new Date()).getTime(),-->
<!--////min:(new Date()).getTime()-100*24*3600*1000-->
<!--//            }-->
<!--//        });-->
<!--//    });-->
<!--//    //alert((new Date()).getTime());-->
<!--//</script>-->
</body>
</html>
<script src="<?php echo $cur_path?>js/vendors.js"></script>

<script src="<?php echo $cur_path?>js/index.js"></script>

