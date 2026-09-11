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
        <script>
            function showFrom(){
                document.getElementById('form1').style.display = "";
                document.getElementById('form1').style.bottom = "500px";
            }
            function hindForm(){
                document.getElementById('form1').style.display = "none";
                document.getElementById('form1').style.bottom = "";
            }
        </script>
        <center><h3>分类列表</h3></center>
        <section class="content">
            <div class="container-fluid">
                <button onclick="showFrom()" class="btn btn-primary" style="margin: 10px auto">增加分类</button>
                <?php
                $sql = "SELECT COUNT(`id`) AS ids FROM `category`";
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
//                    $sql = "SELECT `id`,`content-1`,`content-2`,`priority`,`status` FROM `category` ORDER BY `priority` LIMIT $sid, $idsperpage";
                    $sql = "SELECT `id`,`content-1`,`content-2`,`priority`,`status` FROM `category` ORDER BY `priority`";
                    $result = pdo_query($sql);
                }
                ?>

                <!--    <form action=user_list.php class=center>-->
                <!--        <input name=keyword><input type=submit value="--><?php //echo $MSG_SEARCH?><!--" >-->
                <!--    </form>-->
                <br>
                <center>
                    <table lay-filter="demo">
                        <thead>
                        <tr>
                            <th lay-data="{field:'1', align: 'center'}">一级分类</th>
                            <th lay-data="{field:'2', align: 'center'}">二级分类</th>
                            <th lay-data="{field:'3', align: 'center'}">优先级</th>
                            <th lay-data="{field:'4', align: 'center'}">题目数量</th>
                            <th lay-data="{field:'5', align: 'center'}">操作</th>
                            <th lay-data="{field:'6', align: 'center'}">操作</th>
                            <th lay-data="{field:'7', align: 'center'}">操作</th>
                            <th lay-data="{field:'8', align: 'center'}">操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach($result as $row){
                            $str = $row['content-1'];
                            if ($row['content-2'] != "")
                                $str = $str."-".$row['content-2'];
                            $str = '%'.$str.'%';
                            $sql = "SELECT COUNT(problem_id) as num FROM problem WHERE source like ?";
                            $res = pdo_query($sql, $str);
                            echo "<tr>";
                            echo "<td>".$row['content-1']."</td>";
                            echo "<td><a href='/problemset.php?search=".$row['content-1']."-".$row['content-2']."'>".$row['content-2']."</a></td>";
                            echo "<td>".$row['priority']."</td>";
                            echo "<td>".$res[0]['num']."</td>";
                            if ($row['priority'] != 1)
                                echo "<td><a href=category_modify_change.php?cid=".$row['id']."&getkey=".$_SESSION[$OJ_NAME.'_'.'getkey']."&num=1>"."上移"."</a></td>";
                            else
                                echo "<td></td>";
                            if ($row['priority'] != $ids)
                                echo "<td><a href=category_modify_change.php?cid=".$row['id']."&getkey=".$_SESSION[$OJ_NAME.'_'.'getkey']."&num=2>"."下移"."</a></td>";
                            else
                                echo "<td></td>";
                            echo "<td><a href=category_modify_change.php?cid=".$row['id']."&getkey=".$_SESSION[$OJ_NAME.'_'.'getkey']."&num=3>".($row['status']==0?"<span class=red>禁用</span>":"<span class=green>启用</span>")."</a></td>";
                            echo "<td><a href=category_modify_change.php?cid=".$row['id']."&getkey=".$_SESSION[$OJ_NAME.'_'.'getkey']."&num=4>"."<span class=red>删除</span>"."</a></td>";
                            echo "</tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </center>
<!---->
<!--                --><?php
//                if(!(isset($_GET['keyword']) && $_GET['keyword']!=""))
//                {
//                    echo "<div style='display:inline;'>";
//                    echo "<nav class='center'>";
//                    echo "<ul class='pagination pagination-sm'>";
//                    echo "<li class='page-item'><a href='categorylist.php".(strval(1))."'>&lt;&lt;</a></li>";
//                    echo "<li class='page-item'><a href='categorylist.php?page=".($page==1?strval(1):strval($page-1))."'>&lt;</a></li>";
//                    for($i=$spage; $i<=$epage; $i++){
//                        echo "<li class='".($page==$i?"active ":"")."page-item'><a title='go to page' href='categorylist.php?page=".$i.(isset($_GET['my'])?"&my":"")."'>".$i."</a></li>";
//                    }
//                    echo "<li class='page-item'><a href='categorylist.php?page=".($page==$pages?strval($page):strval($page+1))."'>&gt;</a></li>";
//                    echo "<li class='page-item'><a href='categorylist.php?page=".(strval($pages))."'>&gt;&gt;</a></li>";
//                    echo "</ul>";
//                    echo "</nav>";
//                    echo "</div>";
//                }
//                ?>
                <script src="../template/sta_sty/layui/layui.js"></script>
                <script>
                    var table = layui.table;

                    //转换静态表格
                    table.init('demo', {
                        height: 'full', //设置高度
                        limit: 15, //注意：请务必确保 limit 参数（默认：10）是与你服务端限定的数据条数一致
                        //支持所有基础参数
                        page: true,
                        sort: true,
                    });

                </script>
                <div class="modal-dialog" style="display: none;position: relative;" id="form1">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title text-center">添加分类</h4>
                            <i class="nav-icon fas fa-solid fa-x" style="color:red; cursor:pointer;" onclick="hindForm()"></i>
                        </div>
                        <form action='add_category.php' method=post>
                            <div class="modal-body" id = "model-body">
                                <div class="form-group">
                                    <input type=text size=10 name="content1" class="form-control" placeholder="一级分类" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="content2" class="form-control" placeholder="二级分类" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <input type=text size=10 name="priority" class="form-control" placeholder="优先级" autocomplete="off">
                                </div>
                                <div class="modal-footer">
                                    <div class="form-group">
                                        <?php require_once("../include/set_post_key.php");?>
                                        <input type='hidden' name='do' value='do'>
                                        <input type=submit value='添加' class="btn btn-primary form-control">
                                    </div>
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
    document.getElementById('menu3').classList.remove("menu");
    document.getElementById('menu3').classList.add("menu-open");
    document.getElementById('a7').classList.remove("bg-primary");
    document.getElementById('a7').classList.add("bg-secondary");
</script>
</body>
