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
function editorial_adventure_reward($user, $referenceId, $amount = 10) {
    if (!$user || !$referenceId || $amount <= 0) { return false; }
    $db = editorial_db();
    $db->beginTransaction();
    try {
        // A server without the focused migration would silently coerce the kind
        // to '' outside strict mode and still pay, so require the real value.
        $kind = editorial_query("SELECT COLUMN_TYPE FROM information_schema.columns
            WHERE table_schema=DATABASE() AND table_name='coin_ledger' AND column_name='kind'")->fetchColumn();
        if (!$kind || strpos($kind, "'adventure_reward'") === false) {
            throw new RuntimeException('coin_ledger.kind lacks adventure_reward; apply docker/db/adventure-coins.sql.');
        }
        try {
            editorial_query("INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES(?,'adventure_reward',?,?)",array($user, $referenceId, $amount));
        } catch (PDOException $e) {
            // The only unique key here is the per-event claim itself.
            if (intval($e->errorInfo[1] ?? 0) === 1062) { $db->rollBack(); return false; }
            throw $e;
        }
        editorial_query('INSERT INTO coin_wallet(user_id,balance) VALUES(?,?) ON DUPLICATE KEY UPDATE balance=balance+VALUES(balance)',array($user, $amount));
        $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack();}
        throw $e;
    }
}