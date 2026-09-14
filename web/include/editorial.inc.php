<?php
function editorial_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Use PDO directly here: the legacy pdo_query helper suppresses write errors.
// Money operations must roll back on any failed statement.
function editorial_db() {
    global $dbh;
    if (!$dbh) pdo_query('SELECT 1');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbh;
}

function editorial_query($sql, $args = array()) {
    $stmt = editorial_db()->prepare($sql);
    $stmt->execute($args);
    return $stmt;
}

function editorial_ready() {
    $tables = pdo_query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE()
        AND table_name IN ('coin_wallet','coin_first_ac','coin_ledger','problem_editorial','problem_unlock')");
    $triggers = pdo_query("SELECT COUNT(*) n FROM information_schema.triggers WHERE trigger_schema=DATABASE()
        AND trigger_name IN ('coin_solution_accepted_update','coin_solution_accepted_insert')");
    return $tables && intval($tables[0]['n']) === 5 && $triggers && intval($triggers[0]['n']) === 2;
}

// Same public-practice boundary as Adventure, also applied to direct article URLs.
function editorial_public_sql() {
    return "p.defunct='N' AND NOT EXISTS (SELECT 1 FROM contest_problem cp
        JOIN contest c ON c.contest_id=cp.contest_id WHERE cp.problem_id=p.problem_id
        AND (c.private=1 OR (c.defunct='N' AND c.end_time>NOW())))";
}

function editorial_problem($id) {
    return editorial_query('SELECT p.problem_id,p.title FROM problem p WHERE p.problem_id=? AND '.editorial_public_sql(), array($id))->fetch(PDO::FETCH_ASSOC);
}

function editorial_passed($user, $problem) {
    return $user && editorial_query('SELECT 1 FROM solution WHERE user_id=? AND problem_id=? AND result=4 LIMIT 1', array($user,$problem))->fetchColumn();
}

function editorial_balance($user) {
    return $user ? (int)editorial_query('SELECT balance FROM coin_wallet WHERE user_id=?', array($user))->fetchColumn() : 0;
}

function editorial_submit($user, $problem, $title, $content) {
    $title = trim($title); $content = trim($content);
    if (!$user || !editorial_problem($problem) || !editorial_passed($user,$problem)) {
        throw new DomainException('通过这道公开题目后，才能提交题解。');
    }
    if ($title === '' || mb_strlen($title,'UTF-8') > 120 || $content === '' || mb_strlen($content,'UTF-8') > 50000) {
        throw new DomainException('请填写 1–120 字标题和 1–50000 字正文。');
    }
    editorial_query('INSERT INTO problem_editorial(problem_id,user_id,title,content) VALUES(?,?,?,?)', array($problem,$user,$title,$content));
    return editorial_db()->lastInsertId();
}

function editorial_unlock($user, $id, $admin) {
    if (!$user) throw new DomainException('请先登录，再解锁题解。');
    $db = editorial_db();
    $db->beginTransaction();
    try {
        $article = editorial_query('SELECT * FROM problem_editorial WHERE id=? FOR UPDATE', array($id))->fetch(PDO::FETCH_ASSOC);
        if (!$article || !editorial_problem($article['problem_id']) || $article['status'] !== 'approved') {
            throw new DomainException('这篇题解暂时不能解锁，请返回题解列表。');
        }
        $problem = $article['problem_id'];
        if (!$admin && $article['user_id'] !== $user && !editorial_passed($user,$problem)) {
            // Serialize purchases by wallet, including simultaneous requests for
            // different articles on the same problem. Read the unlock after locking.
            editorial_query('INSERT INTO coin_wallet(user_id,balance) VALUES(?,0) ON DUPLICATE KEY UPDATE balance=balance', array($user));
            $unlocked = editorial_query('SELECT 1 FROM problem_unlock WHERE user_id=? AND problem_id=? FOR UPDATE', array($user,$problem))->fetchColumn();
            if (!$unlocked && !editorial_passed($user,$problem)) {
                $debit = editorial_query('UPDATE coin_wallet SET balance=balance-5 WHERE user_id=? AND balance>=5', array($user));
                if ($debit->rowCount() !== 1) throw new DomainException('金币不足，需要 5 金币。通过本题可免费查看全部题解。');
                editorial_query('INSERT INTO problem_unlock(user_id,problem_id) VALUES(?,?)', array($user,$problem));
                editorial_query("INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES(?,'problem_unlock',?,-5)", array($user,$problem));
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
            editorial_query('INSERT INTO coin_wallet(user_id,balance) VALUES(?,10) ON DUPLICATE KEY UPDATE balance=balance+10',array($article['user_id']));
            editorial_query("INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES(?,'editorial_reward',?,10)",array($article['user_id'],$id));
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
