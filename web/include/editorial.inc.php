<?php
require_once __DIR__.'/coins.inc.php';
require_once __DIR__.'/practice.inc.php';
function editorial_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function editorial_db() {
    return oj_transaction_db();
}

function editorial_query($sql, $args = array()) {
    return oj_query($sql, $args);
}

function editorial_ready() {
    $tables = pdo_query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE()
        AND table_name IN ('problem_editorial','problem_unlock')");
    return $tables && intval($tables[0]['n']) === 2 && coin_ready();
}

// Same public-practice boundary as Adventure, also applied to direct article URLs.
function editorial_public_sql() {
    return practice_public_sql();
}

function editorial_problem($id) {
    return practice_problem($id);
}

function editorial_passed($user, $problem) {
    return $user && editorial_query('SELECT 1 FROM solution WHERE user_id=? AND problem_id=? AND result=4 LIMIT 1', array($user,$problem))->fetchColumn();
}

function editorial_balance($user) {
    return coin_balance($user);
}

// A reference is derived from submissions, never stored or rewarded as a post.
// Keep source text out of this query until the reader is authorized.
function editorial_reference($problem) {
    return editorial_query("SELECT s.solution_id FROM solution s
        JOIN source_code_user sc ON sc.solution_id=s.solution_id
        JOIN problem p ON p.problem_id=s.problem_id
        WHERE s.problem_id=? AND s.result=4 AND sc.source REGEXP '[^[:space:]]'
        AND ".editorial_public_sql()."
        AND NOT EXISTS (SELECT 1 FROM problem_editorial e WHERE e.problem_id=s.problem_id AND e.status='approved')
        ORDER BY s.solution_id ASC LIMIT 1",array($problem))->fetch(PDO::FETCH_ASSOC);
}

function editorial_submit($user, $problem, $title, $content, $format = 'plain') {
    if (!in_array($format, array('plain','markdown'), true)) throw new DomainException('不支持的题解格式。');
    $title = trim($title); $content = trim($content);
    if (!$user || !editorial_problem($problem) || !editorial_passed($user,$problem)) {
        throw new DomainException('通过这道公开题目后，才能提交题解。');
    }
    if ($title === '' || mb_strlen($title,'UTF-8') > 120 || $content === '' || mb_strlen($content,'UTF-8') > 50000) {
        throw new DomainException('请填写 1–120 字标题和 1–50000 字正文。');
    }
    editorial_query('INSERT INTO problem_editorial(problem_id,user_id,title,content,content_format) VALUES(?,?,?,?,?)', array($problem,$user,$title,$content,$format));
    return editorial_db()->lastInsertId();
}

function editorial_unlock($user, $id, $admin, $referenceProblem = 0) {
    if (!$user) throw new DomainException('请先登录，再解锁题解。');
    $db = editorial_db();
    $db->beginTransaction();
    try {
        if (!$id && $referenceProblem) {
            if (!editorial_reference($referenceProblem)) throw new DomainException('备用题解已更新，请返回本题题解列表。');
            $problem = $referenceProblem;
            $author = null;
        } else {
            $article = editorial_query('SELECT * FROM problem_editorial WHERE id=? FOR UPDATE', array($id))->fetch(PDO::FETCH_ASSOC);
            if (!$article || !editorial_problem($article['problem_id']) || $article['status'] !== 'approved') {
                throw new DomainException('这篇题解暂时不能解锁，请返回题解列表。');
            }
            $problem = $article['problem_id'];
            $author = $article['user_id'];
        }
        if (!$admin && $author !== $user && !editorial_passed($user,$problem)) {
            // Serialize purchases by wallet, including simultaneous requests for
            // different articles on the same problem. Read the unlock after locking.
            coin_lock_wallet($user);
            $unlocked = editorial_query('SELECT 1 FROM problem_unlock WHERE user_id=? AND problem_id=? FOR UPDATE', array($user,$problem))->fetchColumn();
            if (!$unlocked && !editorial_passed($user,$problem)) {
                coin_debit_unlock($user, $problem);
                editorial_query('INSERT INTO problem_unlock(user_id,problem_id) VALUES(?,?)', array($user,$problem));
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function editorial_review($reviewer, $id, $decision, $note) {
    global $OJ_NAME;
    if (!$reviewer || !isset($_SESSION[$OJ_NAME.'_administrator'])) throw new DomainException('只有管理员可以审核题解。');
    if (!in_array($decision,array('approved','rejected'),true) || mb_strlen($note,'UTF-8') > 500) throw new DomainException('请选择审核结果，审核说明最多 500 字。');
    if ($decision === 'rejected' && trim($note) === '') throw new DomainException('请填写驳回原因，帮助作者改进题解。');
    $db = editorial_db();
    $db->beginTransaction();
    try {
        $article = editorial_query('SELECT * FROM problem_editorial WHERE id=? FOR UPDATE',array($id))->fetch(PDO::FETCH_ASSOC);
        if (!$article) throw new DomainException('题解不存在。');
        if ($article['status'] !== 'pending') throw new DomainException('这篇题解已经审核过，请刷新列表。');
        editorial_query('UPDATE problem_editorial SET status=?,review_note=?,reviewer=?,reviewed_at=NOW() WHERE id=?',array($decision,trim($note),$reviewer,$id));
        if ($decision === 'approved') {
            coin_reward_editorial($article['user_id'], $id);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
// Retain the existing entry point for integrations; Adventure uses coins directly.
function editorial_adventure_reward($user, $referenceId, $amount = 10) {
    return coin_reward_adventure($user, $referenceId, $amount);
}
