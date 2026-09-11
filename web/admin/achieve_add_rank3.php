<?php require_once("admin-header.php");?>
<?php if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) )){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
require_once("../include/check_post_key.php");
require_once("../include/my_func.inc.php");
require_once("../include/db_info.inc.php");

if(isset($_POST['month'])) {
    $time = $_POST['month'];
    $year = explode("-",$time)[0];
    $month = explode("-",$time)[1];
    $str = $year." ".$month."月";
    $sql = "SELECT COUNT(*) FROM acmer WHERE `value`=?";
    $res = pdo_query($sql, $str);
    if ($res[0][0] > 1) {
        echo "<script>alert('已添加过了');history.go(-1);</script>";
        exit(0);
    }

    $sql_users = "SELECT users.`user_id` FROM `users`
                                        inner join
                                        (select count(distinct problem_id) solved ,user_id from solution 
						where DATE_FORMAT(in_date,'%Y-%m')=? and result=4 
						group by user_id order by solved desc limit 50) s 
					on users.user_id=s.user_id
                                        inner join
                                        (select count( problem_id) submit ,user_id from solution 
						where DATE_FORMAT(in_date,'%Y-%m')=?
						group by user_id order by submit desc ) t 
					on users.user_id=t.user_id
                                ORDER BY s.`solved` DESC,t.submit,reg_time  LIMIT  3";
    $res = pdo_query($sql_users, $time, $time);

    $value = $str;
    $weight = 1;
    $color = "";
    $sql = "INSERT INTO acmer(`user_id`,`value`,`color`,`weight`,`join_time`) VALUES (?,?,?,?,now())";
    for ($i = 0; $i < 3; $i ++) {
        if ($i == 0) $color = "gold";
        else if ($i == 1) $color = "silver";
        else if ($i == 2) $color = "peru";
        $result = pdo_query($sql, $res[$i]['user_id'], $value, $color, $weight);
    }
} else {
    echo "<script>alert('请选择月份。');history.go(-1);</script>";
    exit(0);
}
?>
<script language=javascript>
    history.go(-1);
</script>
