<?php
require_once("../include/db_info.inc.php");
require_once("discuss_func.inc.php");
// 安全修复：管理操作仅接受 POST 并校验 postkey 令牌（防 CSRF）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    err_msg("Wrong request method.");
}
require_once("../include/check_post_key.php");

$tid = intval($_REQUEST['tid']);
$cid = 0;
$tmp = pdo_query("select cid from topic where tid=?", $tid);
if (!empty($tmp)) $cid = $tmp[0][0];

if ($_REQUEST['target']=='reply'){
    $rid = intval($_REQUEST['rid']);
    $stat = -1;
    if ($_REQUEST['action']=='resume') $stat = 0;
    if ($_REQUEST['action']=='disable') $stat = 1;
    if ($_REQUEST['action']=='delete') $stat = 2;
    if ($stat == -1) err_msg("Wrong action.");
    $sql = "update reply SET status =? WHERE rid = ?";
    if (!isset($_SESSION[$OJ_NAME.'_'.'administrator'])){
        if ($stat!=2){
            err_msg("<a href=\"../loginpage.php\">Please Login First</a>");
        }else{
            $sql.=" AND author_id=?";
        }
        pdo_query($sql, $stat, $rid, $_SESSION[$OJ_NAME.'_'.'user_id']);
    }else{
        pdo_query($sql, $stat, $rid);
    }
    header('Location: thread.php?tid='.$tid."&cid=$cid");
    exit();
}
if ($_REQUEST['target']=='thread'){
    $toplevel = -1; $stat = -1;
    if ($_REQUEST['action']=='sticky')
        if(array_key_exists('level',$_REQUEST)&&is_numeric($_REQUEST['level']) &&$_REQUEST['level']>=0 &&$_REQUEST['level']<4)
            $toplevel = intval($_REQUEST['level']);
        else
            err_msg("Invalid sticky level.");
    if ($_REQUEST['action']=='resume') $stat = 0;
    if ($_REQUEST['action']=='lock') $stat = 1;
    if ($_REQUEST['action']=='delete') $stat = 2;
    if (!isset($_SESSION[$OJ_NAME.'_'.'administrator']))
        err_msg("<a href=../loginpage.php>Please Login First</a>");
    if ($toplevel == -1 && $stat == -1)
        err_msg("Wrong action.");
    // 安全修复：参数化更新
    if ($stat == -1)
        $sql = "UPDATE topic SET top_level = ? WHERE tid = ?";
    else
        $sql = "UPDATE topic SET status = ? WHERE tid = ?";
    if ( pdo_query($sql, ($stat==-1?$toplevel:$stat), $tid) > 0) {
        if ($stat!=2) header('Location: thread.php?tid='.$tid."&cid=$cid");
        else header('Location: discuss.php'."?cid=$cid");
    } else {
        err_msg( "The thread does not exist.");
    }
}
?>
