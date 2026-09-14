<?php
$cache_time = 0;
$OJ_CACHE_SHARE = false;
require_once './include/db_info.inc.php';
require_once './include/setlang.php';
require_once './include/community_hunts.inc.php';
header('Cache-Control: private, no-store');
if (isset($OJ_ON_SITE_CONTEST_ID)) { header('Location: contest.php?cid='.intval($OJ_ON_SITE_CONTEST_ID)); exit; }
$user = $_SESSION[$OJ_NAME.'_user_id'] ?? null;
$admin = !empty($_SESSION[$OJ_NAME.'_administrator']);
$id = max(0, intval(hunt_text($_GET,'id')));
$builtin = hunt_text($_GET,'builtin');
$builtins = adv_hunts();
$mode = hunt_text($_GET,'mode');
$mine = $mode === 'mine';
$editing = in_array($mode, array('new','edit'), true);
$page = min(100000, max(1, intval(hunt_text($_GET,'page'))));
$offset = ($page-1)*20;
$error = null; $challenge = null; $items = array(); $comments = array(); $attempt = null; $outcome = null;
$target = ''; $huntUrl = 'hunt.php'; $hasMore = false; $commentMore = false;
$form = array('title'=>'','statement'=>'','buggy_source'=>'','reference_source'=>'','validator_source'=>'','language'=>'1','version'=>'1');
try {
    if ($id) {
        $challenge = hunt_challenge($id, $user, $admin);
        if (!$challenge) { http_response_code(404); throw new InvalidArgumentException('题目不存在或已下架。'); }
        $target = 'custom:'.$id; $huntUrl = 'hunt.php?id='.$id;
        $form = $challenge;
    } elseif ($builtin !== '') {
        if (!isset($builtins[$builtin])) { http_response_code(404); throw new InvalidArgumentException('内置题目不存在。'); }
        $target = 'builtin:'.$builtin; $huntUrl = 'hunt.php?builtin='.$builtin;
    }
    if ($editing && (!$user || ($mode === 'edit' && (!$challenge || $challenge['author'] !== $user)) || ($mode === 'new' && ($id || $builtin)))) {
        http_response_code($user ? 403 : 401); throw new InvalidArgumentException('请登录并从自己的题目进入编辑。');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$user) { http_response_code(401); throw new InvalidArgumentException('请先登录后再操作。'); }
        $active = hunt_query("SELECT user_id FROM users WHERE user_id=? AND defunct='N' AND (register_num=1 OR register_num IS NULL)", $user);
        if (!$active) { http_response_code(403); throw new InvalidArgumentException('当前账号不可用，请重新登录。'); }
        if (!isset($_SESSION[$OJ_NAME.'_postkey']) || !hash_equals($_SESSION[$OJ_NAME.'_postkey'], hunt_text($_POST,'postkey'))) {
            http_response_code(403); throw new InvalidArgumentException('页面凭证已失效，请刷新页面后重试。');
        }
        $action = hunt_text($_POST,'action');
        if ($action === 'save' && $editing) {
            foreach (array('title','statement','buggy_source','reference_source','validator_source','language','version') as $field) $form[$field] = hunt_text($_POST, $field);
            $saved = hunt_save($_POST, $mode === 'edit' ? $id : 0, $user);
            $huntUrl = 'hunt.php?id='.$saved;
        } elseif ($action === 'visibility' && $challenge && ($challenge['author'] === $user || $admin)) {
            $hidden = hunt_text($_POST,'hidden') === '1' ? 1 : 0;
            // Only authors republish; admins may take public content down.
            if (!$hidden && $challenge['author'] !== $user) { http_response_code(403); throw new InvalidArgumentException('只有作者可重新公开题目。'); }
            $changed = hunt_query('UPDATE hunt_challenge SET hidden=?,version=version+1,updated_at=NOW() WHERE id=? AND version=?', $hidden, $id, intval(hunt_text($_POST,'version')));
            if (!$changed) throw new InvalidArgumentException('题目已更新，请刷新后重试。');
        } elseif ($action === 'attempt' && $challenge && !$challenge['hidden']) {
            $aid = hunt_submit($id, intval(hunt_text($_POST,'version')), hunt_text($_POST,'input'), $user);
            $huntUrl .= '&attempt='.$aid.'#attempt';
        } elseif ($action === 'comment' && $target && (!$challenge || !$challenge['hidden'])) {
            hunt_comment_add($target, hunt_text($_POST,'content'), $user);
            $huntUrl .= '#comments';
        } elseif ($action === 'delete_comment' && $target) {
            $cid = intval(hunt_text($_POST,'comment_id'));
            $deleted = $admin ? hunt_query('UPDATE hunt_comment SET deleted=1 WHERE id=? AND target=? AND deleted=0', $cid, $target) :
                hunt_query('UPDATE hunt_comment SET deleted=1 WHERE id=? AND target=? AND author=? AND deleted=0', $cid, $target, $user);
            if (!$deleted) { http_response_code(403); throw new InvalidArgumentException('评论不存在或你没有删除权限。'); }
            $huntUrl .= '#comments';
        } else { http_response_code(403); throw new InvalidArgumentException('无权执行此操作，请使用页面中的按钮。'); }
        header('Location: '.$huntUrl, true, 303); exit;
    }

} catch (InvalidArgumentException $e) {
    if (http_response_code() < 400) http_response_code(400);
    $error = $e->getMessage();
} catch (Throwable $e) {
    http_response_code(503); $error = '反例社区暂不可用，请稍后重试。';
    error_log('Community hunts: '.$e->getMessage());
}
// Reload visible content after a validation error so existing discussion remains visible.
try {
    if ($target && !$editing) {
        $comments = hunt_query("SELECT id,author,content,created_at FROM hunt_comment WHERE target=? AND deleted=0 ORDER BY id DESC LIMIT 21 OFFSET $offset", $target);
        $commentMore = count($comments) > 20; $comments = array_slice($comments,0,20);
        if ($challenge && $user) {
            $aid = max(0, intval(hunt_text($_GET,'attempt')));
            $rows = $aid ? hunt_query('SELECT * FROM hunt_attempt WHERE id=? AND challenge_id=? AND user_id=?', $aid, $id, $user) :
                hunt_query('SELECT * FROM hunt_attempt WHERE challenge_id=? AND user_id=? ORDER BY id DESC LIMIT 1', $id, $user);
            if ($rows) { $attempt = $rows[0]; $outcome = hunt_attempt_result($attempt); }
            elseif ($aid) { http_response_code(404); $error = '挑战记录不存在或不属于当前账号。'; }
        }
    } elseif (!$editing && !$error) {
        if ($mine && !$user) { http_response_code(401); throw new InvalidArgumentException('请先登录查看自己的题目。'); }
        $items = $mine ? hunt_query("SELECT id,title,author,language,hidden,version,created_at FROM hunt_challenge WHERE author=? ORDER BY id DESC LIMIT 21 OFFSET $offset", $user) :
            hunt_query("SELECT id,title,author,language,hidden,version,created_at FROM hunt_challenge WHERE hidden=0 ORDER BY id DESC LIMIT 21 OFFSET $offset");
        $hasMore = count($items)>20; $items = array_slice($items,0,20);
    }
} catch (InvalidArgumentException $e) {
    if (http_response_code() < 400) http_response_code(400);
    $error = $e->getMessage();
} catch (Throwable $e) {
    http_response_code(503); $error = '反例社区暂不可用，请稍后重试。';
    error_log('Community hunts read: '.$e->getMessage());
}
require 'template/syzoj/hunt.php';
