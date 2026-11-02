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

// Remove everything a reader skips: fenced blocks (backticks or tildes) even when
// nested inside block quotes or list items, genuine indented code blocks (but NOT a
// paragraph's lazy continuation), inline code that may span lines, HTML comments
// and <code>/<pre>. Only prose is an explanation. Mirrors the JS proseLength().
function editorial_strip_code($text) {
    $kept = array();
    $inFence = false; $fenceChar = ''; $fenceLen = 0;
    $prevBlank = true; $inIndented = false;
    foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
        // Containers may alternate: a list can contain a quote and vice versa.
        // Advance an offset rather than repeatedly copying a deeply nested line.
        $offset = 0;
        while (preg_match('/\G[ \t]*(?:>[ \t]?|(?:[-*+]|\d+[.)])[ \t]+)/', $line, $prefix, 0, $offset)) {
            $offset += strlen($prefix[0]);
        }
        $d = substr($line, $offset);
        $dt = ltrim($d, " \t");
        if ($inFence) {
            if (preg_match('/^'.$fenceChar.'{'.$fenceLen.',}[ \t]*$/', $dt)) {
                $inFence = false; $prevBlank = true; $kept[] = '';
            }
            continue;
        }
        if (preg_match('/^(`{3,}|~{3,})/u', $dt, $m)) {
            $inFence = true; $fenceChar = $m[1][0]; $fenceLen = strlen($m[1]);
            $inIndented = false; $kept[] = '';
            continue;
        }
        if ($dt === '') { $prevBlank = true; $kept[] = ''; continue; }
        // The blank line starts an indented block; subsequent indented lines stay
        // in that block. Indentation alone cannot interrupt a prose paragraph.
        if (preg_match('/^(?: {4,}|\t)/', $d) && ($prevBlank || $inIndented)) {
            $inIndented = true; $prevBlank = false; continue;
        }
        $inIndented = false;
        $kept[] = $line;
        $prevBlank = false;
    }
    // Match exact backtick-run lengths within each paragraph. Precompute the next
    // matching run so many unmatched delimiters do not repeatedly scan the body.
    $paragraphs = preg_split('/\n[ \t]*\n/', implode("\n", $kept));
    foreach ($paragraphs as &$body) {
        preg_match_all('/`+/', $body, $matches, PREG_OFFSET_CAPTURE);
        $runs = $matches[0]; $next = array(); $shortNext = array(); $last = array();
        for ($i = count($runs)-1; $i >= 0; $i--) {
            $length = strlen($runs[$i][0]);
            $next[$i] = $last[$length] ?? -1;
            $shortNext[$i] = $last[$length-1] ?? -1;
            $last[$length] = $i;
        }
        $parts = array(); $cursor = 0;
        for ($i = 0; $i < count($runs); $i++) {
            $start = $runs[$i][1]; $slashes = 0;
            for ($j = $start-1; $j >= 0 && $body[$j] === '\\'; $j--) $slashes++;
            // Outside code, a backslash escapes only the first backtick of a run.
            $escaped = $slashes % 2;
            $length = strlen($runs[$i][0]) - $escaped;
            $close = $escaped ? $shortNext[$i] : $next[$i];
            if (!$length || $close < 0) continue;
            $start += $escaped;
            $parts[] = substr($body, $cursor, $start-$cursor);
            $parts[] = ' ';
            $cursor = $runs[$close][1] + strlen($runs[$close][0]);
            $i = $close;
        }
        $parts[] = substr($body, $cursor);
        $body = implode('', $parts);
    }
    unset($body);
    return implode("\n\n", $paragraphs);
}

// One source of truth for validation, error text, editor hints and tests.
function editorial_min_prose() { return 120; }

function editorial_effective_length($content, $format) {
    $text = (string)$content;
    if ($format === 'markdown') {
        $text = editorial_strip_code($text);
        $text = preg_replace('/<!--.*?-->/s', ' ', $text);
        $text = preg_replace('/<(code|pre)\b[^>]*>.*?<\/\1>/is', ' ', $text);
    }
    preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text, $m);
    return count($m[0]);
}

function editorial_submit($user, $problem, $title, $content, $format = 'plain') {
    if (!in_array($format, array('plain','markdown'), true)) throw new DomainException('不支持的题解格式。');
    $title = trim($title);
    // Keep the body verbatim: a leading indented Markdown code block, and plain
    // text whose indentation belongs to the answer, must survive review and
    // reading. Only line endings are normalised; blank bodies are still rejected.
    $content = str_replace(array("\r\n", "\r"), "\n", $content);
    if (!$user || !editorial_problem($problem) || !editorial_passed($user,$problem)) {
        throw new DomainException('通过这道公开题目后，才能提交题解。');
    }
    if ($title === '' || mb_strlen($title,'UTF-8') > 120 || trim($content) === '' || mb_strlen($content,'UTF-8') > 50000) {
        throw new DomainException('请填写 1–120 字标题和 1–50000 字正文。');
    }
    $effective = editorial_effective_length($content, $format);
    if ($effective < editorial_min_prose()) {
        throw new DomainException('请把思路写出来再提交：代码之外的解释只有 '.$effective.' 个汉字，至少 '.editorial_min_prose().' 个。请说明关键思路及为什么可行、算法步骤、时间与空间复杂度，可以点编辑器里的「插入题解模板」按结构填写。');
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
