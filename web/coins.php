<?php
$cache_time = 0;
$OJ_CACHE_SHARE = false;
require_once './include/db_info.inc.php';
require_once './include/setlang.php';
require_once './include/editorial.inc.php';
header('Cache-Control: private, no-store');
if (isset($OJ_ON_SITE_CONTEST_ID)) { header('Location: contest.php?cid='.intval($OJ_ON_SITE_CONTEST_ID)); exit; }
$user = $_SESSION[$OJ_NAME.'_user_id'] ?? null;
$tab = ($_GET['tab'] ?? '') === 'history' ? 'history' : 'ranking';
$page = isset($_GET['page']) && is_scalar($_GET['page']) ? min(100000,max(1,intval($_GET['page']))) : 1;
$ready = editorial_ready(); $error = null; $rows = array(); $balance = 0; $hasNext = false;
if ($tab === 'history' && !$user) { http_response_code(401); $error = '请先登录，再查看自己的金币明细。'; }
elseif ($ready) {
    try {
        $balance = editorial_balance($user); $offset = ($page-1)*30;
        if ($tab === 'history') {
            $rows = editorial_query("SELECT kind,reference_id,amount,created_at FROM coin_ledger WHERE user_id=? ORDER BY id DESC LIMIT 31 OFFSET $offset",array($user))->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = editorial_query("SELECT u.user_id,u.nick,COALESCE(w.balance,0) balance FROM users u LEFT JOIN coin_wallet w ON w.user_id=u.user_id
                WHERE u.defunct='N' AND u.register_num=1 ORDER BY balance DESC,u.user_id ASC LIMIT 31 OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
        }
        $hasNext = count($rows)>30; $rows = array_slice($rows,0,30);
    } catch (Throwable $e) { http_response_code(503); error_log('Coins read failed: '.$e->getMessage()); $error = '金币记录暂时无法加载，请稍后刷新。'; }
}
$navProblemId = 0;
$returnProblemId = isset($_GET['from_problem']) && is_scalar($_GET['from_problem']) ? max(0,intval($_GET['from_problem'])) : 0;
if ($ready && $returnProblemId) {
    try { if (editorial_problem($returnProblemId)) $navProblemId = $returnProblemId; }
    catch (Throwable $e) { error_log('Coins navigation failed: '.$e->getMessage()); }
}
$navQuery = $navProblemId ? '&amp;from_problem='.$navProblemId : '';
$show_title = '金币榜单 - '.editorial_escape($OJ_NAME);
$OJ_EDITORIAL_VIEWPORT = true;
require 'template/syzoj/coins.php';
