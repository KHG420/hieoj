<?php
require_once("include/db_info.inc.php");
require_once("include/setlang.php");
if(isset($OJ_BBS)&&!$OJ_BBS){
    $view_errors= "$MSG_BBS_NOT_ALLOWED_FOR_EXAM  || $MSG_BBS is not available.";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}
ob_start ();
function problem_exist($pid,$cid){
    $pid=intval($pid);
    $cid=intval($cid);
    if($pid!=0)
        if($cid!=0)
            $sql="SELECT 1 FROM `contest_problem` WHERE `contest_id` = $cid AND `problem_id` = $pid";
        else
            $sql="SELECT 1 FROM `problem` WHERE `problem_id` = $pid";
    else if($cid!=0)
        $sql="SELECT 1 FROM `contest` WHERE `contest_id` = $cid";
    else
        return true;
    $sql.=" LIMIT 1";
    $result=pdo_query($sql);
    return is_array($result) && count($result)>0;
}
function err_msg($msg){
    // Error templates and their header/footer expect the page globals.
    extract($GLOBALS, EXTR_SKIP);
    $view_errors= "$msg";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}
// 安全修复：统一的 CSRF 令牌生成与 POST 表单助手（管理链接改为防 CSRF 提交）
function oj_csrf_input(){
    global $OJ_NAME;
    if(!isset($_SESSION[$OJ_NAME.'_'.'postkey'])){
        if(function_exists('random_bytes')) $k=bin2hex(random_bytes(16));
        elseif(function_exists('openssl_random_pseudo_bytes')) $k=bin2hex(openssl_random_pseudo_bytes(16));
        else $k=bin2hex(mt_rand()).bin2hex(uniqid('',true));
        $_SESSION[$OJ_NAME.'_'.'postkey']=$k;
    }
    return '<input type="hidden" name="postkey" value="'.htmlentities($_SESSION[$OJ_NAME.'_'.'postkey'],ENT_QUOTES,'UTF-8').'">';
}
function oj_admin_post_form($action_url,$params,$label,$title=''){
    $fields=oj_csrf_input();
    foreach($params as $k=>$v){
        $fields.='<input type="hidden" name="'.htmlentities($k,ENT_QUOTES,'UTF-8').'" value="'.htmlentities($v,ENT_QUOTES,'UTF-8').'">';
    }
    return '<form method="post" action="'.htmlentities($action_url,ENT_QUOTES,'UTF-8').'" style="display:inline;margin:0">'.$fields
        .'<button type="submit" style="background:none;border:none;color:#2f6ee5;cursor:pointer;padding:0;font-size:inherit" title="'.htmlentities($title,ENT_QUOTES,'UTF-8').'">'.$label.'</button></form>';
}
?>
