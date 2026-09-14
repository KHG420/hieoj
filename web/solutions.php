<?php
$cache_time = 0;
$OJ_CACHE_SHARE = false;
require_once './include/db_info.inc.php';
require_once './include/setlang.php';
require_once './include/editorial.inc.php';
header('Cache-Control: private, no-store');
if (isset($OJ_ON_SITE_CONTEST_ID)) { header('Location: contest.php?cid='.intval($OJ_ON_SITE_CONTEST_ID)); exit; }
$user = $_SESSION[$OJ_NAME.'_user_id'] ?? null;
$admin = $user && isset($_SESSION[$OJ_NAME.'_administrator']);
$tab = isset($_GET['tab']) && is_string($_GET['tab']) && in_array($_GET['tab'],array('published','mine','review'),true) ? $_GET['tab'] : 'published';
$id = isset($_GET['id']) && is_scalar($_GET['id']) ? max(0,intval($_GET['id'])) : 0;
$problemId = isset($_GET['problem_id']) && is_scalar($_GET['problem_id']) ? max(0,intval($_GET['problem_id'])) : 0;
$returnProblemId = isset($_GET['from_problem']) && is_scalar($_GET['from_problem']) ? max(0,intval($_GET['from_problem'])) : 0;
$page = isset($_GET['page']) && is_scalar($_GET['page']) ? min(100000,max(1,intval($_GET['page']))) : 1;
// Public editorial browsing always belongs to a specific problem.
if ($tab === 'published' && !$id && !$problemId) {
    header('Location: problemset.php', true, $_SERVER['REQUEST_METHOD'] === 'POST' ? 303 : 302); exit;
}
$ready = editorial_ready();
$error = null; $article = null; $problem = null; $rows = array(); $canRead = false; $passed = false; $hasNext = false;
$balance = 0; $submitted = false; $reference = null;
$submitRequest = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? null) === 'submit';
$writing = !$id && $tab === 'published' && (($_GET['write'] ?? null) === '1' || $submitRequest);
// Capture the user's work before authentication/CSRF validation so errors preserve it.
$titleInput = $submitRequest && is_string($_POST['title'] ?? null) ? $_POST['title'] : '';
$contentInput = $submitRequest && is_string($_POST['content'] ?? null) ? $_POST['content'] : '';
$formatInput = $submitRequest && is_string($_POST['content_format'] ?? null) ? $_POST['content_format'] : ($submitRequest ? 'plain' : 'markdown');
$statusNames = array('pending'=>'待审核','approved'=>'审核通过','rejected'=>'已驳回');
if ($tab === 'review' && !$admin) { http_response_code(403); $error = '只有管理员可以访问题解审核。'; }
elseif ($tab === 'mine' && !$user) { http_response_code(401); $error = '请先登录，再查看自己的题解。'; }
elseif ($ready) {
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$user) { http_response_code(401); throw new DomainException('请先登录，再提交或解锁题解。'); }
            if (!isset($_POST['postkey'],$_SESSION[$OJ_NAME.'_postkey']) || !is_string($_POST['postkey']) || !hash_equals($_SESSION[$OJ_NAME.'_postkey'],$_POST['postkey'])) {
                http_response_code(403); throw new DomainException('页面凭证已失效，请刷新后重试。');
            }
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
            if ($action === 'submit' && $problemId && !$id && $tab === 'published') {
                $titleInput = is_string($_POST['title'] ?? null) ? $_POST['title'] : '';
                $contentInput = is_string($_POST['content'] ?? null) ? $_POST['content'] : '';
                $newId = editorial_submit($user,$problemId,$titleInput,$contentInput,$formatInput);
                $_SESSION[$OJ_NAME.'_editorial_submitted'] = (int)$newId;
                header('Location: solutions.php?id='.$newId, true, 303); exit;
            } elseif ($action === 'unlock' && $id) {
                editorial_unlock($user,$id,$admin);
                header('Location: solutions.php?id='.$id, true, 303); exit;
            } elseif ($action === 'unlock' && $problemId && $tab === 'published' && !$writing) {
                editorial_unlock($user,0,$admin,$problemId);
                header('Location: solutions.php?problem_id='.$problemId, true, 303); exit;
            } elseif ($action === 'review' && $admin && $id && $tab === 'review') {
                editorial_review($user,$id,is_string($_POST['decision'] ?? null) ? $_POST['decision'] : '',is_string($_POST['note'] ?? null) ? $_POST['note'] : '');
                header('Location: solutions.php?tab=review'.($returnProblemId ? '&from_problem='.$returnProblemId : ''), true, 303); exit;
            } else { http_response_code(400); throw new DomainException('无效操作，请使用页面中的按钮。'); }
        }
    } catch (DomainException $e) { if (http_response_code() < 400) http_response_code(400); $error = $e->getMessage(); }
    catch (Throwable $e) { http_response_code(503); error_log('Editorial write failed: '.$e->getMessage()); $error = '保存失败，本次操作未完成。请稍后重试。'; }
    try {
        $balance = editorial_balance($user);
        if ($id) {
            // Deliberately omit the body until visibility and payment have been checked.
            $article = editorial_query('SELECT e.id,e.problem_id,e.user_id,e.title,e.content_format,e.status,e.review_note,e.created_at,e.reviewed_at,p.title problem_title FROM problem_editorial e JOIN problem p ON p.problem_id=e.problem_id WHERE e.id=?'.($admin ? '' : ' AND '.editorial_public_sql()),array($id))->fetch(PDO::FETCH_ASSOC);
            if (!$article || (!$admin && $article['user_id'] !== $user && $article['status'] !== 'approved')) {
                $article = null; http_response_code(404); $error = '题解不存在或暂未公开。';
            } else {
                $canRead = $user && ($admin || $article['user_id'] === $user || ($article['status'] === 'approved' && (editorial_passed($user,$article['problem_id']) || editorial_query('SELECT 1 FROM problem_unlock WHERE user_id=? AND problem_id=?',array($user,$article['problem_id']))->fetchColumn())));
                if ($canRead) {
                    $article['content'] = editorial_query('SELECT content FROM problem_editorial WHERE id=?',array($id))->fetchColumn();
                    if ($article['user_id'] === $user && (int)($_SESSION[$OJ_NAME.'_editorial_submitted'] ?? 0) === $id) {
                        $submitted = true;
                        unset($_SESSION[$OJ_NAME.'_editorial_submitted']);
                    }
                }
            }
        } else {
            if ($problemId) {
                $problem = editorial_problem($problemId);
                if (!$problem) { http_response_code(404); $error = '题目不存在或暂未公开。'; }
                else $passed = editorial_passed($user,$problemId);
            }
            if (!$problemId || $problem) {
                $args = array();
                $where = $tab === 'review' ? "e.status='pending'" : editorial_public_sql();
                if ($tab === 'published') { $where .= " AND e.status='approved' AND e.problem_id=?"; $args[] = $problemId; }
                if ($tab === 'mine') { $where .= ' AND e.user_id=?'; $args[] = $user; }
                if ($problemId && $tab !== 'published') { $where .= ' AND e.problem_id=?'; $args[] = $problemId; }
                $offset = ($page-1)*20;
                $rows = editorial_query("SELECT e.id,e.problem_id,e.user_id,e.title,e.status,e.created_at,p.title problem_title
                    FROM problem_editorial e JOIN problem p ON p.problem_id=e.problem_id WHERE $where ORDER BY e.id DESC LIMIT 21 OFFSET $offset",$args)->fetchAll(PDO::FETCH_ASSOC);
                $hasNext = count($rows)>20; $rows = array_slice($rows,0,20);
                if ($tab === 'published' && !$writing && !$rows && $page === 1 && ($reference = editorial_reference($problemId))) {
                    $article = array('problem_id'=>$problemId,'problem_title'=>$problem['title'],
                        'title'=>'AC 参考代码','content_format'=>'plain');
                    $canRead = $user && ($admin || $passed || editorial_query('SELECT 1 FROM problem_unlock WHERE user_id=? AND problem_id=?',array($user,$problemId))->fetchColumn());
                    if ($canRead) {
                        $article['content'] = editorial_query('SELECT source FROM source_code_user WHERE solution_id=?',array($reference['solution_id']))->fetchColumn();
                    }
                }
            }
        }
    } catch (Throwable $e) { http_response_code(503); error_log('Editorial read failed: '.$e->getMessage()); $error = '题解暂时无法加载，请稍后刷新。'; $rows = array(); $article = null; }
}
$contextProblemId = $article ? intval($article['problem_id']) : ($problem ? $problemId : 0);
// Navigation context must not filter global lists or change their headings.
$navProblemId = $contextProblemId;
if (!$navProblemId && $ready && $returnProblemId) {
    try { if (editorial_problem($returnProblemId)) $navProblemId = $returnProblemId; }
    catch (Throwable $e) { error_log('Editorial navigation failed: '.$e->getMessage()); }
}
$navQuery = $navProblemId ? '&amp;from_problem='.$navProblemId : '';
$heading = $contextProblemId ? 'P'.$contextProblemId.' · '.($article ? $article['problem_title'] : $problem['title']).' · 题解' : ($tab === 'mine' ? '我的题解' : ($tab === 'review' ? '题解审核' : '题解'));
$show_title = editorial_escape($heading).' - '.editorial_escape($OJ_NAME);
$OJ_EDITORIAL_VIEWPORT = true;
$draftKey = $user && $contextProblemId ? 'oj-editorial:'.rawurlencode($OJ_NAME).':'.rawurlencode($user).':'.$contextProblemId : '';
$showComposer = $writing && $problem && ($passed || $submitRequest);
$loadMarkdown = $showComposer || ($article && $canRead && $article['content_format'] === 'markdown');
require 'template/syzoj/solutions.php';
