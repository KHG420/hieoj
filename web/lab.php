<?php
require_once './include/db_info.inc.php';
require_once './include/setlang.php';
require_once './include/editorial.inc.php';
header('Cache-Control: private, no-store');
$user = $_SESSION[$OJ_NAME.'_user_id'] ?? null;
$admin = $user && isset($_SESSION[$OJ_NAME.'_administrator']);
$tab = is_string($_GET['tab'] ?? null) && in_array($_GET['tab'],array('about','join','feedback','mine','manage','settings'),true) ? $_GET['tab'] : 'about';
$id = is_scalar($_GET['id'] ?? null) ? max(0,intval($_GET['id'])) : 0;
$page = is_scalar($_GET['page'] ?? null) ? max(1,min(100000,intval($_GET['page']))) : 1;
$kind = in_array($_GET['kind'] ?? '',array('join','bug','suggestion'),true) ? $_GET['kind'] : '';
$states = array('join'=>array('pending'=>'待处理','talking'=>'沟通中','accepted'=>'已通过','rejected'=>'未通过'), 'bug'=>array('pending'=>'待确认','confirmed'=>'已确认','working'=>'处理中','resolved'=>'已解决','declined'=>'暂不处理'), 'suggestion'=>array('pending'=>'待确认','confirmed'=>'已确认','working'=>'处理中','resolved'=>'已采纳','declined'=>'暂不采纳'));
$kinds = array('join'=>'加入申请','bug'=>'问题反馈','suggestion'=>'功能建议');
$fields = array('join'=>array('name'=>'姓名','department'=>'学院 / 专业','grade'=>'年级','contact'=>'QQ / 邮箱','experience'=>'编程基础与常用语言','reason'=>'加入原因与学习方向','availability'=>'每周可参与训练的时间'), 'bug'=>array('url'=>'发生页面','steps'=>'复现步骤','expected'=>'预期结果','actual'=>'实际结果','device'=>'浏览器与设备'), 'suggestion'=>array('description'=>'建议内容','benefit'=>'希望解决的问题'));
function lab_text($name,$max=3000,$required=true) {
    $v = is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
    if (($required && $v==='') || mb_strlen($v,'UTF-8')>$max) throw new DomainException('请完整填写必填项，并遵守字段字数限制。');
    return $v;
}
$error=null; $settings=null; $request=null; $rows=array(); $hasNext=false; $formData=array();
try {
    editorial_db()->exec('SET NAMES utf8mb4');
    $settings=editorial_query('SELECT * FROM acm_lab_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    if (!$settings) throw new RuntimeException('Missing laboratory settings');
    if (in_array($tab,array('manage','settings'),true) && !$admin) { http_response_code(403); throw new DomainException('只有管理员可以进入实验室管理。'); }
    if (in_array($tab,array('join','feedback','mine'),true) && !$user) { http_response_code(401); throw new DomainException('请先登录，再提交或查看申请与反馈。'); }
    if ($id) {
        $request=editorial_query('SELECT id,user_id,kind,title,details,status,reply,created_at,updated_at,screenshot_mime'.($admin?',admin_note':'').' FROM acm_lab_request WHERE id=?'.($admin?'':' AND user_id=?'),$admin?array($id):array($id,$user ?? ''))->fetch(PDO::FETCH_ASSOC);
        if (!$request) { http_response_code(404); throw new DomainException('记录不存在或无权查看。'); }
    }
    if (($_GET['attachment'] ?? '') === '1') {
        if (!$request || !$request['screenshot_mime']) { http_response_code(404); exit; }
        $blob=editorial_query('SELECT screenshot FROM acm_lab_request WHERE id=?',array($id))->fetchColumn();
        header('Content-Type: '.$request['screenshot_mime']); header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="feedback-'.$id.'.'.array('image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp')[$request['screenshot_mime']].'"');
        echo $blob; exit;
    }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!$user) { http_response_code(401); throw new DomainException('请先登录。'); }
        if (!is_string($_POST['postkey'] ?? null) || !isset($_SESSION[$OJ_NAME.'_postkey']) || !hash_equals($_SESSION[$OJ_NAME.'_postkey'],$_POST['postkey'])) { http_response_code(403); throw new DomainException('页面凭证失效，请刷新页面后重试。'); }
        $action=lab_text('action',20);
        if ($action==='settings' && $admin && $tab==='settings') {
            editorial_query('UPDATE acm_lab_settings SET introduction=?,contact=?,recruitment_open=? WHERE id=1',array(lab_text('introduction',10000),lab_text('contact',500,false),isset($_POST['recruitment_open'])?1:0));
            header('Location: lab.php?tab=settings&saved=1',true,303); exit;
        } elseif ($action==='review' && $admin && $request) {
            $status=lab_text('status',24);
            if (!isset($states[$request['kind']][$status])) throw new DomainException('无效的处理状态。');
            $reply=lab_text('reply',5000,$status!==$request['status']);
            editorial_query('UPDATE acm_lab_request SET status=?,reply=?,admin_note=? WHERE id=?',array($status,$reply,lab_text('admin_note',5000,false),$id));
            header('Location: lab.php?tab=manage&id='.$id.'&saved=1',true,303); exit;
        } elseif ($action==='submit' && in_array($tab,array('join','feedback'),true)) {
            $submitKind=$tab==='join'?'join':lab_text('kind',20);
            if (!isset($fields[$submitKind]) || ($tab==='feedback' && $submitKind==='join')) throw new DomainException('无效的反馈类型。');
            $title=$submitKind==='join'?'加入 ACM 实验室':lab_text('title',120);
            $details=array();
            foreach($fields[$submitKind] as $key=>$label) $details[$key]=lab_text($key,3000,!in_array($key,array('url','device'),true));
            $image=null; $mime=null;
            $file=$_FILES['screenshot'] ?? null;
            if ($file && $file['error']!==UPLOAD_ERR_NO_FILE) {
                if ($submitKind==='join' || $file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size']>2*1024*1024) throw new DomainException('截图上传失败，请选择不超过 2 MB 的 PNG、JPEG 或 WebP 图片。');
                $info=@getimagesize($file['tmp_name']);
                if (!$info || !in_array($info['mime'],array('image/png','image/jpeg','image/webp'),true) || $info[0]*$info[1]>20000000) throw new DomainException('截图格式不受支持，或图片尺寸过大。');
                $image=file_get_contents($file['tmp_name']); $mime=$info['mime'];
            }
            $db=editorial_db(); $db->beginTransaction();
            try {
                // Serialize applications from one user, including double-clicks.
                editorial_query('SELECT user_id FROM users WHERE user_id=? FOR UPDATE',array($user));
                if ($submitKind==='join') {
                    $open=editorial_query('SELECT recruitment_open FROM acm_lab_settings WHERE id=1 LOCK IN SHARE MODE')->fetchColumn();
                    if (!$open) throw new DomainException('目前暂未开放申请，请留意实验室介绍中的招新通知。');
                    $active=editorial_query("SELECT id,status FROM acm_lab_request WHERE user_id=? AND kind='join' AND status IN ('pending','talking','accepted') ORDER BY id DESC LIMIT 1 FOR UPDATE",array($user))->fetch(PDO::FETCH_ASSOC);
                    if ($active && $active['status']==='accepted') throw new DomainException('你的加入申请已通过，无需重复申请。');
                    if ($active) {
                        $newId=$active['id'];
                        editorial_query('UPDATE acm_lab_request SET details=? WHERE id=?',array(json_encode($details,JSON_UNESCAPED_UNICODE),$newId));
                    } else {
                        editorial_query("INSERT INTO acm_lab_request(user_id,kind,title,details,reply,admin_note) VALUES(?,'join',?,?,'','')",array($user,$title,json_encode($details,JSON_UNESCAPED_UNICODE)));
                        $newId=$db->lastInsertId();
                    }
                } else {
                    editorial_query("INSERT INTO acm_lab_request(user_id,kind,title,details,reply,admin_note,screenshot,screenshot_mime) VALUES(?,?,?,?,'','',?,?)",array($user,$submitKind,$title,json_encode($details,JSON_UNESCAPED_UNICODE),$image,$mime));
                    $newId=$db->lastInsertId();
                }
                $db->commit();
            } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
            header('Location: lab.php?tab=mine&id='.$newId.'&saved=1',true,303); exit;
        } else { http_response_code(400); throw new DomainException('无效操作。'); }
    }
    if ($tab==='join') {
        $active=editorial_query("SELECT details,status FROM acm_lab_request WHERE user_id=? AND kind='join' AND status IN ('pending','talking','accepted') ORDER BY id DESC LIMIT 1",array($user))->fetch(PDO::FETCH_ASSOC);
        if ($active) $formData=json_decode($active['details'],true);
    }
    if (in_array($tab,array('mine','manage'),true) && !$id) {
        $args=array(); $where='1=1';
        if ($tab==='mine') { $where.=' AND user_id=?'; $args[]=$user; }
        if ($kind) { $where.=' AND kind=?'; $args[]=$kind; }
        $filterStatus=is_string($_GET['status'] ?? null)?$_GET['status']:'';
        if ($filterStatus && in_array($filterStatus,array('pending','talking','accepted','rejected','confirmed','working','resolved','declined'),true)) { $where.=' AND status=?'; $args[]=$filterStatus; }
        $offset=($page-1)*20;
        $rows=editorial_query("SELECT id,user_id,kind,title,status,created_at,updated_at FROM acm_lab_request WHERE $where ORDER BY id DESC LIMIT 21 OFFSET $offset",$args)->fetchAll(PDO::FETCH_ASSOC);
        $hasNext=count($rows)>20; $rows=array_slice($rows,0,20);
    }
} catch (DomainException $e) { if (http_response_code()<400) http_response_code(400); $error=$e->getMessage(); }
catch (Throwable $e) { http_response_code(503); error_log('ACM lab: '.$e->getMessage()); $error='实验室服务暂时不可用，请稍后刷新。'; }
$show_title='ACM 实验室 - '.editorial_escape($OJ_NAME); $OJ_EDITORIAL_VIEWPORT=true;
require 'template/syzoj/lab.php';
