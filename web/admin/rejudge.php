<?php require("admin-header.php");

if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
}?>
<?php
function send_post($url, $post_data) {

    $postdata = http_build_query($post_data);
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-type:application/x-www-form-urlencoded',
            'content' => $postdata,
            'timeout' => 15 * 60 // 超时时间（单位:s）
        )
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    return $result;
}

if(isset($_POST['do'])){
	require_once("../include/check_post_key.php");
    require_once('../include/db_info.inc.php');
	if (isset($_POST['rjpid'])){
		$rjpid=intval($_POST['rjpid']);
		if($rjpid == 0) {
		    echo "Rejudge Problem ID should not equal to 0";
		    exit(1);
		}
        $sql = "SELECT spj FROM `problem` WHERE problem_id=?";
        $res = pdo_query($sql, $rjpid);
        if ($res[0]['spj'] != '0' && $res[0]['spj'] != '1') {
            $sql = "SELECT solution_id FROM solution WHERE problem_id=?";
            $result1 = pdo_query($sql, $rjpid);
            $sql="delete from `sim` WHERE `s_id` in (select solution_id from solution where `problem_id`=?)";
            pdo_query($sql,$rjpid) ;
            foreach ($result1 as $row) {
                $sql = "update solution set result=20 where solution_id=?";
                pdo_query($sql, $row[0]);
                $post_data = array(
                    'sid' => $row[0],
                    'tid' => $rjpid,
                );
                $url = "http://" . $micServiceIP . "/mail/SubmitOtherOj/";
                send_post($url, $post_data);
            }
        } else {
            $sql = "UPDATE `solution` SET `result`=1 WHERE `problem_id`=? and problem_id>0";
            pdo_query($sql, $rjpid);
            $sql = "delete from `sim` WHERE `s_id` in (select solution_id from solution where `problem_id`=?)";
            pdo_query($sql, $rjpid);
        }
		$url="../status.php?problem_id=".$rjpid;
		echo "Rejudged Problem ".$rjpid;
		echo "<script>location.href='$url';</script>";
	}
	else if (isset($_POST['rjsid'])){
		$rjsid=intval($_POST['rjsid']);
		$sql="delete from `sim` WHERE `s_id`=?";
		pdo_query($sql,$rjsid);
        $sql = "SELECT spj,problem_id FROM `problem` WHERE problem_id in (SELECT problem_id FROM solution WHERE solution_id=?)";
        $res = pdo_query($sql, $rjsid);
        if ($res[0]['spj'] != '0' && $res[0]['spj'] != '1') {
            $sql = "update solution set result=20 where solution_id=?";
            pdo_query($sql, $rjsid);
            $post_data = array(
                'sid' => $rjsid,
                'tid' => $res[0]['problem_id'],
            );
            $url = "http://" . $micServiceIP . "/mail/SubmitOtherOj/";
            send_post($url, $post_data);
        } else {
            $sql = "UPDATE `solution` SET `result`=1 WHERE `solution_id`=? and problem_id>0";
            pdo_query($sql, $rjsid);
        }
		$url="../status.php?top=".($rjsid);
		echo "Rejudged Runid ".$rjsid;
		echo "<script>location.href='$url';</script>";
	}else if (isset($_POST['rjcid'])){
        $rjcid=intval($_POST['rjcid']);
        $sql = "SELECT spj,problem_id FROM `problem` WHERE problem_id in (SELECT problem_id FROM solution WHERE contest_id=?)";
        $result1 = pdo_query($sql, $rjcid);
        foreach ($result1 as $row) {
            $sql = "SELECT spj FROM `problem` WHERE problem_id=?";
            $res = pdo_query($sql, $row['problem_id']);
            if ($row['spj'] != '0' && $row['spj'] != '1') {
                $sql = "SELECT solution_id FROM solution WHERE problem_id=?";
                $result1 = pdo_query($sql, $row['problem_id']);
                foreach ($result1 as $row1) {
                    $sql = "update solution set result=20 where solution_id=?";
                    pdo_query($sql, $row1[0]);
                    $post_data = array(
                        'sid' => $row1[0],
                        'tid' => $row['problem_id'],
                    );
                    $url = "http://" . $micServiceIP . "/mail/SubmitOtherOj/";
                    send_post($url, $post_data);
                }
            } else {
//                $sql="UPDATE `solution` SET `result`=1 WHERE `contest_id`=? and problem_id>0";
                $sql = "UPDATE `solution` SET `result`=1 WHERE `problem_id`=? and problem_id>0";
                pdo_query($sql,$row['problem_id']) ;
            }
        }
		$url="../status.php?cid=".($rjcid);
		echo "Rejudged Contest id :".$rjcid;
		echo "<script>location.href='$url';</script>";
	}
	echo str_repeat(" ",4096);
	flush();
	if($OJ_REDIS){
           $redis = new Redis();
           $redis->connect($OJ_REDISSERVER, $OJ_REDISPORT);
	   if(isset($OJ_REDISAUTH)) $redis->auth($OJ_REDISAUTH);
                $sql="select solution_id from solution where result=1 and problem_id>0";
                 $result=pdo_query($sql);
                 foreach($result as $row){
                        echo $row['solution_id']."\n";
                        $redis->lpush($OJ_REDISQNAME,$row['solution_id']);
                }
           $redis->close();     
        }

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
        <section class="content">
            <div class="container-fluid">
                <div class="modal-dialog" style="margin-top: 10%;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title text-center">重判</h4>
                            <select class="dropdown btn-default" onchange="choiceShow()" id="selete1">
                                <option class="dropdown-item" value="1">重判问题</option>
                                <option class="dropdown-item" value="2">重判提交</option>
                                <option class="dropdown-item" value="3">重判竞赛&作业</option>
                            </select>
                        </div>
                        <form action='rejudge.php' method=post style="position: relative" id="form1">
                            <?php require_once("../include/set_post_key.php");?>
                            <div class="modal-body">
                                    <div class="form-group">
                                        <input type=input name='rjpid' placeholder="问题编号" class="form-control" autocomplete="off">
                                    </div>
                            </div>
                            <div class="modal-footer">
                                <div class="form-group">
                                    <input type='hidden' name='do' value='do'>
                                    <input type=submit value=submit class="btn btn-primary form-control">
                                </div>
                            </div>
                        </form>
                        <form action='rejudge.php' method=post style="position: relative;display: none" id="form2">
                            <div class="modal-body">
                                <div class="form-group">
                                    <input type=input name='rjsid' placeholder="提交编号" class="form-control" autocomplete="off">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="form-group">
                                    <input type='hidden' name='do' value='do'>
                                    <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
                                    <input type=submit value=submit class="btn btn-primary form-control">
                                </div>
                            </div>
                        </form>
                        <form action='rejudge.php' method=post style="position: relative;display: none" id="form3">
                            <div class="modal-body">
                                <div class="form-group">
                                    <input type=input name='rjcid' placeholder="竞赛编号" class="form-control" autocomplete="off">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="form-group">
                                    <input type='hidden' name='do' value='do'>
                                    <input type=hidden name="postkey" value="<?php echo $_SESSION[$OJ_NAME.'_'.'postkey']?>">
                                    <input type=submit value=submit class="btn btn-primary form-control">
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
    function showSubmit(){
        document.getElementById('form1').style.display = "none";
        document.getElementById('form3').style.display = "none";
        document.getElementById('form2').style.display = "";
    }
    function showQuestion(){
        document.getElementById('form1').style.display = "";
        document.getElementById('form3').style.display = "none";
        document.getElementById('form2').style.display = "none";
    }
    function showContest(){
        document.getElementById('form1').style.display = "none";
        document.getElementById('form3').style.display = "";
        document.getElementById('form2').style.display = "none";
    }
    function choiceShow(){
        var obj = document.getElementById('selete1');
        var a = obj.options[obj.selectedIndex].value;
        if(a === '1'){
            showQuestion();
        }else if(a === '2'){
            showSubmit();
        }else if(a === '3'){
            showContest();
        }
    }
</script>
<script>
    document.getElementById('menu3').classList.remove("menu");
    document.getElementById('menu3').classList.add("menu-open");
    document.getElementById('a22').classList.remove("bg-primary");
    document.getElementById('a22').classList.add("bg-secondary");
</script>
</body>

