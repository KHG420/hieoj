<?php require_once("admin-header.php");

if(isset($OJ_LANG)){
    require_once("../lang/$OJ_LANG.php");
}


?>

<html>
<head>
    <title><?php echo $MSG_ADMIN?></title>
</head>
<div class="hold-transition sidebar-mini layout-fixed">
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
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3">
                            <div class="card-header"><?php echo $MSG_SEEOJ?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_SEEOJ?></p>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3">
                            <div class="card-header"><?php echo $MSG_SETMESSAGE?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_SETMESSAGE?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3">
                            <div class="card-header"><?php echo $MSG_NEWS.$MSG_LIST?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_NEWS_LIST?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3">
                            <div class="card-header"><?php echo $MSG_ADD.$MSG_NEWS?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_ADD_NEWS?></p>
                            </div>
                        </div>
                    </div>
                    <?php }?>
                </div>
                <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                <div class="row">
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3">
                            <div class="card-header"><?php echo $MSG_USER.$MSG_LIST?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_USER_LIST?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3">
                            <div class="card-header"><?php echo $MSG_SET_LOGIN_IP?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_SET_LOGIN_IP?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3">
                            <div class="card-header">添加用户*</div>
                            <div class="card-body">
                                <p class="card-text">添加新用户，初始密码为123456</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3">
                            <div class="card-header">分类列表*</div>
                            <div class="card-body">
                                <p class="card-text">添加或调整问题分类</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php }?>
                <div class="row">
                    <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])){?>
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3">
                            <div class="card-header">添加比赛成就*</div>
                            <div class="card-body">
                                <p class="card-text">添加比赛成就</p>
                            </div>
                        </div>
                    </div>
                    <?php }
                    if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3">
                            <div class="card-header">审核用户申请*</div>
                            <div class="card-body">
                                <p class="card-text">审核用户提交的信息修改申请</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3">
                            <div class="card-header">用户注册申请*</div>
                            <div class="card-body">
                                <p class="card-text">审核用户提交的注册申请</p>
                            </div>
                        </div>
                    </div>
                    <?php }
                    if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                    <div class="col-sm-3 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_EXPORT.$MSG_PROBLEM?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_EXPORT_PROBLEM?></p>
                            </div>
                        </div>
                    </div>
                    <?php }?>
                </div>
                <div class="row">
                    <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset( $_SESSION[$OJ_NAME.'_'.'password_setter'] )){?>
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_SETPASSWORD?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_SETPASSWORD?></p>
                            </div>
                        </div>
                    </div>
                    <?php }
                    if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_GIVESOURCE?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_GIVESOURCE?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                        <div class="card-header"><?php echo $MSG_PRIVILEGE.$MSG_LIST?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_PRIVILEGE_LIST?></p>
                            </div>
                        </div>
                    </div>
                    <?php }?>
                </div>
                <div class="row">
                    <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_REJUDGE?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_REJUDGE?></p>
                            </div>
                        </div>
                    </div>
                    <?php }
                    if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])||isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])){?>
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_PROBLEM.$MSG_LIST?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_PROBLEM_LIST?></p>
                            </div>
                        </div>
                    </div>
                    <?php }
                    if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_IMPORT.$MSG_PROBLEM?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_IMPORT_PROBLEM?></p>
                            </div>
                        </div>
                    </div>
                    <?php }?>
                </div>
                <div class="row">
                    <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])){?>
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_ADD.$MSG_PROBLEM?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_ADD_PROBLEM?></p>
                            </div>
                        </div>
                    </div>
                    <?php }
                    if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_CONTEST.$MSG_LIST?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_CONTEST_LIST?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_ADD.$MSG_CONTEST?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_ADD_CONTEST?></p>
                            </div>
                        </div>
                    </div>
                    <?php }?>
                </div>
                <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
                <div class="row">
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_TEAMGENERATOR?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_TEAMGENERATOR?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_ADD.$MSG_PRIVILEGE?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_ADD_PRIVILEGE?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_UPDATE_DATABASE?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_UPDATE_DATABASE?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php }
                if (isset($_SESSION[$OJ_NAME.'_'.'contest_creator']) && !isset($_SESSION[$OJ_NAME.'_'.'administrator'])){ ?>
                <div class="row">
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_CONTEST.$MSG_LIST?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_CONTEST_LIST?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-4 ">
                        <div class="card text-white bg-white mb-3"">
                            <div class="card-header"><?php echo $MSG_ADD.$MSG_CONTEST?></div>
                            <div class="card-body">
                                <p class="card-text"><?php echo $MSG_HELP_ADD_CONTEST?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php }?>
                <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])&&!$OJ_SAE){
                    ?>
                    <a href="problem_copy.php" title="Create your own data"><font color="eeeeee">CopyProblem</font></a> <br>
                    <a href="problem_changeid.php" title="Danger,Use it on your own risk"><font color="eeeeee">ReOrderProblem</font></a>
                <?php }
                ?>
            </div>
        </section>
    </div>
</div>
</body>
</html>
