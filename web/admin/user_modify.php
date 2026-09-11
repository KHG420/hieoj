<?php
require("admin-header.php");
require_once("../include/set_get_key.php");

if(!isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}

if(isset($OJ_LANG)){
    require_once("../lang/$OJ_LANG.php");
}
?>

<title>User List</title>

<link rel="stylesheet" href="https://cdn.bootcss.com/bootstrap/4.0.0-beta/css/bootstrap.min.css">
<link href="https://cdn.bootcdn.net/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css" rel="stylesheet">
<link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.1.1/css/all.css" rel="stylesheet">
<style>
    .nav > li > a:hover{
        background-color:#01AAED;
    }
</style>
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
        <center><h3>申请列表</h3></center>
        <section class="content">
            <div class="container-fluid">
                <?php
                $sql = "SELECT COUNT('id') AS ids FROM `modify`";
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
                    $sql = "SELECT `user_id`,`nick`,`reg_time`,`ip`,`school`,`defunct` FROM `users` WHERE (user_id LIKE ?) OR (nick LIKE ?) OR (school LIKE ?) ORDER BY `user_id` DESC";
                    $result = pdo_query($sql,$keyword,$keyword,$keyword);
                }else{
                    $sql = "SELECT m.id,m.user_id,m.content,m.thing,m.reason,m.time,m.`status`,users.nick,users.school FROM `modify` as m LEFT JOIN `users` ON users.user_id=m.user_id ORDER BY `time` DESC LIMIT $sid, $idsperpage";
                    $result = pdo_query($sql);
                }
?>
<!--    <form action=user_list.php class=center>-->
<!--        <input name=keyword><input type=submit value="--><?php //echo $MSG_SEARCH?><!--" >-->
<!--    </form>-->
<br>
    <center>
        <table width=100% border=1 style="text-align:center;">
            <tr>
                <td>学号</td>
                <td>姓名</td>
                <td>修改项</td>
                <td>修改内容</td>
                <td>原因</td>
                <td>提交时间</td>
                <td>状态</td>
            </tr>
            <?php
            foreach($result as $row){
                $thing = "";
                if (strcmp($row['thing'], "xueYuan") == 0)
                    $thing = "学院";
                else if (strcmp($row['thing'], "school") == 0)
                    $thing = "专业班级";
                else if (strcmp($row['thing'], "nick") == 0)
                    $thing = "姓名";
                else if (strcmp($row['thing'], "user_id") == 0)
                    $thing = "学号";
                else if (strcmp($row['thing'], ""))
                echo "<tr>";
                echo "<td><a href='../userinfo.php?user=".$row['user_id']."'>".$row['user_id']."</a></td>";
                echo "<td>".$row['nick']."</td>";
                echo "<td>".$thing."</td>";
                echo "<td>".$row['content']."</td>";
                echo "<td>".$row['reason']."</td>";
                echo "<td>".$row['time']."</td>";
                echo "<td><a href=user_modify_change.php?cid=".$row['id']."&getkey=".$_SESSION[$OJ_NAME.'_'.'getkey'].">".($row['status']==0?"<span class=red>未通过</span>":"<span class=green>通过</span>")."</a></td>";
                echo "</tr>";
            }
            ?>
        </table>
    </center>

                <!--    <form action=user_list.php class=center>-->
                <!--        <input name=keyword><input type=submit value="--><?php //echo $MSG_SEARCH?><!--" >-->
                <!--    </form>-->
                <br>
                <!-- <center>
                //     <table width=100% border=1 style="text-align:center;">
                //         <tr>
                //             <td>学号</td>
                //             <td>姓名</td>
                //             <td>专业班级</td>
                //             <td>修改项</td>
                //             <td>修改内容</td>
                //             <td>原因</td>
                //             <td>提交时间</td>
                //             <td>状态</td>
                //         </tr>
                //         <?php
                //         foreach($result as $row){
                //             $thing = "";
                //             if ($row['thing'] == 0)
                //                 $thing = "姓名";
                //             else if ($row['thing'] == 1)
                //                 $thing = "专业班级";
                //             echo "<tr>";
                //             echo "<td><a href='../userinfo.php?user=".$row['user_id']."'>".$row['user_id']."</a></td>";
                //             echo "<td>".$row['nick']."</td>";
                //             echo "<td>".$row['school']."</td>";
                //             echo "<td>".$thing."</td>";
                //             echo "<td>".$row['content']."</td>";
                //             echo "<td>".$row['reason']."</td>";
                //             echo "<td>".$row['time']."</td>";
                //             echo "<td><a href=user_modify_change.php?cid=".$row['id']."&getkey=".$_SESSION[$OJ_NAME.'_'.'getkey'].">".($row['status']==0?"<span class=red>未通过</span>":"<span class=green>通过</span>")."</a></td>";
                //             echo "</tr>";
                //         }
                //         ?>
                //     </table>
                // </center>-->

                <?php
                if(!(isset($_GET['keyword']) && $_GET['keyword']!=""))
                {
                    echo "<div style='display:inline;'>";
                    echo "<nav class='center'>";
                    echo "<ul class='pagination pagination-sm'>";
                    echo "<li class='page-item'><a href='user_modify.php".(strval(1))."'>&lt;&lt;</a></li>";
                    echo "<li class='page-item'><a href='user_modify.php?page=".($page==1?strval(1):strval($page-1))."'>&lt;</a></li>";
                    for($i=$spage; $i<=$epage; $i++){
                        echo "<li class='".($page==$i?"active ":"")."page-item'><a title='go to page' href='user_modify.php?page=".$i.(isset($_GET['my'])?"&my":"")."'>".$i."</a></li>";
                    }
                    echo "<li class='page-item'><a href='user_modify.php?page=".($page==$pages?strval($page):strval($page+1))."'>&gt;</a></li>";
                    echo "<li class='page-item'><a href='user_modify.php?page=".(strval($pages))."'>&gt;&gt;</a></li>";
                    echo "</ul>";
                    echo "</nav>";
                    echo "</div>";
                }
                ?>
            </div>
        </section>
    </div>
</div>
<script>
    document.getElementById('menu2').classList.remove("menu");
    document.getElementById('menu2').classList.add("menu-open");
    document.getElementById('a8').classList.remove("bg-primary");
    document.getElementById('a8').classList.add("bg-secondary");
</script>
</body>
