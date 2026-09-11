<?php require_once("admin-header.php");
require_once("../include/check_get_key.php");
$cid=$_GET['cid'];
$nn=$_GET['num'];
//echo $cid;
if(!(isset($_SESSION[$OJ_NAME.'_'."m$cid"])||isset($_SESSION[$OJ_NAME.'_'.'administrator']))) exit();

if ($nn == 1) {
    $sql = "select `priority` FROM `category` WHERE `id`=?";
    $result = pdo_query($sql, $cid);
//echo $result;
    $num = count($result);
//echo $num;
    if ($num < 1) {
        echo "No Such Category!";
        require_once("../oj-footer.php");
        exit(0);
    }
    $row = $result[0];
    $priority = $row[0] - 1;

    $sql = "select `id` FROM `category` WHERE `priority`=?";
    $result = pdo_query($sql, $priority);
    $row = $result[0];

    $sql = "UPDATE `category` SET `priority`=? WHERE `id`=?";
    pdo_query($sql, $priority, $cid);
    pdo_query($sql, $priority + 1, $row[0]);
}

 else if ($nn == 2) {
    $sql = "select `priority` FROM `category` WHERE `id`=?";
    $result = pdo_query($sql, $cid);
//echo $result;
    $num = count($result);
//echo $num;
    if ($num < 1) {
        echo "No Such Category!";
        require_once("../oj-footer.php");
        exit(0);
    }
    $row = $result[0];
    $priority = $row[0] + 1;

     $sql = "select `id` FROM `category` WHERE `priority`=?";
     $result = pdo_query($sql, $priority);
     $row = $result[0];

    $sql = "UPDATE `category` SET `priority`=? WHERE `id`=?";
    pdo_query($sql, $priority, $cid);
     pdo_query($sql, $priority - 1, $row[0]);
}

else if ($nn == 3) {
    $sql = "select `status` FROM `category` WHERE `id`=?";
    $result = pdo_query($sql, $cid);
//echo $result;
    $num = count($result);
//echo $num;
    if ($num < 1) {
        echo "No Such Category!";
        require_once("../oj-footer.php");
        exit(0);
    }
    $row = $result[0];
    if ($row[0] == 0) {
        $sql = "UPDATE `category` SET `status`=1 WHERE `id`=?";
        pdo_query($sql, $cid);
    } else if ($row[0] == 1) {
        $sql = "UPDATE `category` SET `status`=0 WHERE `id`=?";
        pdo_query($sql, $cid);
    }
}

else if ($nn == 4) {
    $sql = "select `priority` FROM `category` WHERE `id`=?";
    $result = pdo_query($sql, $cid);
//echo $result;
    $num = count($result);
//echo $num;
    if ($num < 1) {
        echo "No Such Category!";
        require_once("../oj-footer.php");
        exit(0);
    }
    $sql = "DELETE FROM category WHERE id=?";
    pdo_query($sql, $cid);

    $sql = "SELECT id FROM `category` ORDER BY priority";
    $result = pdo_query($sql);

    $sql = "UPDATE `category` SET `priority`=? WHERE `id`=?";
    for ($i = 0; $i < count($result); $i ++) {
        pdo_query($sql, $i + 1, $result[$i][0]);
    }
}

?>
<script language=javascript>
    history.go(-1);
</script>

