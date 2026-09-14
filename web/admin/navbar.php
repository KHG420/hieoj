<link rel="stylesheet" href="https://cdn.bootcss.com/bootstrap/4.0.0-beta/css/bootstrap.min.css">
<link href="https://cdn.bootcdn.net/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css" rel="stylesheet">
<link href="https://cdn.bootcss.com/font-awesome/5.13.0/css/all.css" rel="stylesheet">
<style>
    .nav > li > a:hover, .nav > li > a:focus{
        background-color:#01AAED;
    }
</style>
<aside class="main-sidebar sidebar-light-primary elevation-4">
        <a href="index.php" class="brand-link">
            <img src="../favicon.ico" alt="HomeProtect Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <b class="brand-text font-weight-light">HNIEOJAdmin</b>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a class='btn btn-info text-white' href="../status.php" title="<?php echo $MSG_HELP_SEEOJ?>">
                            <b><?php echo $MSG_SEEOJ?></b>
                        </a>
                    </li>
                    <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                    <li class="nav-item menu" id="menu6" style="text-align: center">
                        <a href="#" class="nav-link active bg-info">
                            <b>数据中心</b>
                            <i class="right fas fa-angle-left"></i>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="dashboard.php" title="运营数据驾驶舱" id="a30">
                                    <b>数据驾驶舱</b>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="sim_heatmap.php" title="提交相似度查重热力图" id="a31">
                                    <b>查重热力图</b>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php }?>
                    <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                    <li class="nav-item menu" id="menu1" style="text-align: center">
                        <a href="#" class="nav-link active bg-info">
                            <b>新闻类</b>
                            <i class="right fas fa-angle-left"></i>
                        </a>
                        <ul class="nav nav-treeview">
                            <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                                <li class="nav-item">
                                    <a class='nav-link bg-primary' href="setmsg.php" title="<?php echo $MSG_HELP_SETMESSAGE?>" id="a1">
                                        <b>设置公告</b>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class='nav-link bg-primary' href="news_list.php" title="管理已经发布的新闻" id="a2">
                                        <b>新闻列表</b>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class='nav-link bg-primary' href="news_add_page.php" title="添加首页显示的新闻" id="a3">
                                        <b>添加新闻</b>
                                    </a>
                                </li>
                            <?php }?>
                        </ul>
                    </li>
                    <?php }
                    if (isset($_SESSION[$OJ_NAME . '_' . 'administrator'])){ ?>
                    <li class="nav-item menu" id="menu2" style="text-align: center">
                        <a href="#" class="nav-link active bg-info">
                            <b>用户类</b>
                            <i class="right fas fa-angle-left"></i>
                        </a>
                        <ul class="nav nav-treeview">
                            <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="user_list.php" title="对注册用户停用、启用帐号" id="a4"><b>用户列表</b></a>
                            </li>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="academic_directory.php" title="临时登录教务采集学院班级目录并预览确认" id="a32"><b>同步学院班级</b></a>
                            </li>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="user_set_ip.php" title="指定登录IP" id="a5"><b>指定登录IP</b></a>
                            </li>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="adduser.php" title="添加用户" id="a6"><b>添加用户 *</b></a>
                            </li>
                                <li class="nav-item">
                                    <a class='nav-link bg-primary' href="update_user.php" title="上传审核花名册" id="a20"><b>上传审核花名册 *</b></a>
                                </li>
                            <li class="nav-item">
                                <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
                                    $sql = "SELECT COUNT(id) as num FROM `modify` WHERE `status`=0";
                                    $result = pdo_query($sql);
                                    $num = $result[0]['num'];
                                    if ($num > 0) {
                                        echo "<a class='nav-link bg-primary' href='user_modify.php' style='position:relative;' id='a8'>"
                                            ."<b>审核用户申请*</b>"
                                            ."<div style='background-color: red; color: white; position:absolute; top:-10px; right:-10px; border-radius: 50px; width: 20px;height: 20px;'>"
                                            ."<div style='text-align: center; line-height: 20px;'>"
                                            .$num
                                            ."</div>"
                                            ."</div>"
                                            ."</a>";
                                    }else{
                                        echo "<a class='nav-link bg-primary' href='user_modify.php' style='position:relative;' id='a8'>"
                                            ."<b>审核用户申请*</b>"
                                            ."</a>";
                                    }
                                } ?>
                            </li>
                            <li class="nav-item">
                                <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
                                    $sql = "SELECT COUNT(*) as num FROM `users` WHERE `register_num`=0";
                                    $result = pdo_query($sql);
                                    $num = $result[0]['num'];
                                    if ($num > 0) {
                                        echo "<a class='nav-link bg-primary' href='user_register.php' style='position:relative;' id='a9'>"
                                            ."<b>用户注册申请*</b>"
                                            ."<div style='background-color: red; color: white; position:absolute; top:-10px; right:-10px; border-radius: 50px; width: 20px;height: 20px;'>"
                                            ."<div style='text-align: center; line-height: 20px;'>"
                                            .$num
                                            ."</div>"
                                            ."</div>"
                                            ."</a>";
                                    }else{
                                        echo "<a class='nav-link bg-primary' href='user_register.php' style='position:relative;' id='a9'>"
                                            ."<b>用户注册申请*</b>"
                                            ."</a>";
                                    }
                                } ?>
                            </li>
                                <li class="nav-item">
                                    <a class='nav-link bg-primary' href="achieve_add_page.php" title="管理用户成就" id="a25">
                                        <b>用户成就*</b>
                                    </a>
                                </li>
                            <?php }
                            if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset( $_SESSION[$OJ_NAME.'_'.'password_setter'] )){?>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="changepass.php" title="<?php echo $MSG_HELP_SETPASSWORD?>" id="a10">
                                    <b><?php echo $MSG_SETPASSWORD?></b>
                                </a>
                            </li>
                            <?php }
                            if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="source_give.php" title="<?php echo $MSG_HELP_GIVESOURCE?>" id="a11">
                                    <b><?php echo $MSG_GIVESOURCE?></b>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="privilege_list.php" title="<?php echo $MSG_HELP_PRIVILEGE_LIST?>" id="a12">
                                    <b><?php echo $MSG_PRIVILEGE.$MSG_LIST?></b>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="privilege_add.php" title="<?php echo $MSG_HELP_ADD_PRIVILEGE?>" id="a13">
                                    <b><?php echo $MSG_ADD.$MSG_PRIVILEGE?></b>
                                </a>
                            </li>
                            <?php }?>
                        </ul>
                    </li>
                    <?php }?>
                    <li class="nav-item menu" id="menu3" style="text-align: center">
                        <a href="#" class="nav-link active bg-info">
                            <b>问题类</b>
                            <i class="right fas fa-angle-left"></i>
                        </a>
                        <ul class="nav nav-treeview">
                            <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                                <li class="nav-item">
                                    <a class='nav-link bg-primary' href="categorylist.php" id="a7"><b>分类列表*</b></a>
                                </li>
                                <li class="nav-item">
                                    <a class='nav-link bg-primary' href="knowledge_graph.php" id="a-knowledge"><b>知识地图管理</b></a>
                                </li>
                                <li class="nav-item"><a class="nav-link bg-primary" href="../solutions.php?tab=review" target="_top"><b>题解审核</b></a></li>
                                <li class="nav-item"><a class="nav-link bg-primary" href="../lab.php?tab=manage" target="_top"><b>ACM 实验室管理</b></a></li>
                            <?php }
                            if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])||isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])){?>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="problem_list.php" title="<?php echo $MSG_HELP_PROBLEM_LIST?>" id="a14">
                                    <b><?php echo $MSG_PROBLEM.$MSG_LIST?></b>
                                </a>
                            </li>
                            <?php }
                            if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])){?>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="problem_add_page.php" title="<?php echo $MSG_HELP_ADD_PROBLEM?>" id="a15">
                                    <b><?php echo $MSG_ADD.$MSG_PROBLEM?></b>
                                </a>
                            </li>
                            <?php }
                            if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                                <li class="nav-item">
                                    <a class='nav-link bg-primary' href="problem_import.php" title="<?php echo $MSG_HELP_IMPORT_PROBLEM?>" id="a16">
                                        <b><?php echo $MSG_IMPORT.$MSG_PROBLEM?></b>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class='nav-link bg-primary' href="problem_export.php" title="<?php echo $MSG_HELP_EXPORT_PROBLEM?>" id="a17">
                                        <b><?php echo $MSG_EXPORT.$MSG_PROBLEM?></b>
                                    </a>
                                </li>
                            <?php }
                            if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="rejudge.php" title="<?php echo $MSG_HELP_REJUDGE?>" id="a22">
                                    <b><?php echo $MSG_REJUDGE?></b>
                                </a>
                            </li>
                            <?php }?>
                        </ul>
                    </li>
                    <li class="nav-item menu" id="menu4" style="text-align: center">
                        <a href="#" class="nav-link active bg-info">
                            <b>竞赛类</b>
                            <i class="right fas fa-angle-left"></i>
                        </a>
                        <ul class="nav nav-treeview">
                            <?php
                            if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])){?>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="contest_list.php"  title="<?php echo $MSG_HELP_CONTEST_LIST?>" id="a18">
                                    <b><?php echo $MSG_CONTEST.$MSG_LIST?></b>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="contest_add.php"  title="<?php echo $MSG_HELP_ADD_CONTEST?>" id="a19">
                                    <b><?php echo $MSG_ADD.$MSG_CONTEST?></b>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="add_contest_achieve.php" id="a20">
                                    <b>添加比赛成就*</b>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="team_list.php" id="a24">
                                    <b>比赛队伍管理</b>
                                </a>
                            </li>
                            <?php }
                            if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="team_generate.php" title="<?php echo $MSG_HELP_TEAMGENERATOR?>" id="a21">
                                    <b><?php echo $MSG_TEAMGENERATOR?></b>
                                </a>
                            </li>
                            <?php }?>
                        </ul>
                    </li>
                    <li class="nav-item menu" id="menu5" style="text-align: center">
                        <a href="#" class="nav-link active bg-info">
                            <b>杂类</b>
                            <i class="right fas fa-angle-left"></i>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="https://github.com/zhblue/freeproblemset/">
                                    <b>FreeProblemSet</b>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="https://github.com/zhblue/hustoj/">
                                    <b>HUSTOJ</b>
                                </a>
                            </li>
                            <?php
                            if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                                <li class="nav-item">
                                    <a class='nav-link bg-primary' href="update_db.php" title="<?php echo $MSG_HELP_UPDATE_DATABASE?>" id="a23">
                                        <b><?php echo $MSG_UPDATE_DATABASE?></b>
                                    </a>
                                </li>
                            <?php }
                            if (isset($OJ_ONLINE)&&$OJ_ONLINE){?>
                                <li class="nav-item">
                                    <a class='nav-link bg-primary' href="../online.php">
                                        <b><?php echo $MSG_ONLINE?></b>
                                    </a>
                                </li>
                            <?php }?>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="http://tk.hustoj.com">
                                    <b>自助题库</b>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class='nav-link bg-primary' href="http://shang.qq.com/wpa/qunwpa?idkey=d52c3b12ddaffb43420d308d39118fafe5313e271769277a5ac49a6fae63cf7a">手机QQ加官方群23361372</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>
<!-- <script src="https://cdn.bootcss.com/jquery/3.2.1/jquery.min.js"></script> -->
<script src="./js/jquery.min.js"></script>
<!-- <script src="https://cdn.bootcss.com/bootstrap/4.0.0-beta/js/bootstrap.min.js"></script> -->
<script src="./js/popper.min.js"></script>
<script src="./js/bootstrap.min.js"></script>
<!-- <script src="https://cdn.bootcdn.net/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js"></script> -->
<script src="./js/adminlte.min.js"></script>
