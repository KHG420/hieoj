<?php
// This file may be included by CLI tests; HTTP must stop before any library/config load.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const SYSTEM_EDITORIAL_MAX_INPUT = 3 * 1024 * 1024;

function system_editorial_user() { return '系统题解'; }

function system_editorial_problem_fingerprint($row) {
    $canonical = array();
    foreach (array('problem_id','title','description','input','output','sample_input','sample_output','hint','source','time_limit','memory_limit','spj') as $field) {
        $value = $row[$field] ?? null;
        $canonical[$field] = in_array($field,array('problem_id','time_limit','memory_limit'),true) ? (int)$value : (string)$value;
    }
    return hash('sha256',json_encode($canonical,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS|JSON_THROW_ON_ERROR));
}


function system_editorial_read_input($stream) {
    $raw = stream_get_contents($stream, SYSTEM_EDITORIAL_MAX_INPUT + 1);
    if ($raw === false) throw new RuntimeException('无法读取标准输入。');
    if (strlen($raw) > SYSTEM_EDITORIAL_MAX_INPUT) throw new DomainException('输入超过 3 MiB。');
    return $raw;
}

function system_editorial_string_length($text) {
    // PCRE is available in the stock CLI image; mbstring is not required.
    $length = preg_match_all('/./us', $text);
    if ($length === false) throw new DomainException('文本必须是合法 UTF-8。');
    return $length;
}

function system_editorial_hash($value) {
    return is_string($value) && preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1;
}

// Return the exact bytes inside the final complete root-level fence, including
// its source's final newline. Neither content nor source is trimmed/normalised.
function system_editorial_final_code($content, $language) {
    $offset = 0;
    $fence = null;
    $last = null;
    foreach (preg_split('/(?<=\n)/', $content) as $line) {
        $plain = rtrim($line, "\r\n");
        if ($fence === null) {
            if (preg_match('/\A {0,3}(`{3,}|~{3,})([^\r\n]*)\z/', $plain, $m)) {
                $fence = array('char'=>$m[1][0], 'length'=>strlen($m[1]),
                    'tag'=>trim($m[2]), 'start'=>$offset + strlen($line));
            }
        } elseif (preg_match('/\A {0,3}'.preg_quote($fence['char'], '/').'{'.$fence['length'].',}[ \t]*\z/', $plain)) {
            $last = array('tag'=>$fence['tag'],
                'source'=>substr($content, $fence['start'], $offset-$fence['start']),
                'end'=>$offset + strlen($line));
            $fence = null;
        }
        $offset += strlen($line);
    }
    if ($fence !== null || $last === null || trim(substr($content, $last['end'])) !== '') {
        throw new DomainException('正文必须以完整参考代码围栏结束。');
    }
    $tags = $language === 'c' ? array('c') : array('cpp', 'c++', 'cpp17');
    if (!in_array($last['tag'], $tags, true) || trim($last['source']) === '' ||
        !preg_match('/\bmain\s*\(/', $last['source'])) {
        throw new DomainException('最后代码块必须是所声明语言的完整程序。');
    }
    return $last['source'];
}

function system_editorial_validate_input($raw) {
    if (!is_string($raw) || strlen($raw) > SYSTEM_EDITORIAL_MAX_INPUT) {
        throw new DomainException('输入超过 3 MiB 或不是文本。');
    }
    try {
        $batch = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new DomainException('输入必须是合法 UTF-8 JSON 对象。');
    }
    if (!($batch instanceof stdClass) || !is_string($batch->batch_id ?? null) ||
        !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,79}\z/D', $batch->batch_id) ||
        !is_array($batch->articles ?? null) || count($batch->articles) < 1 || count($batch->articles) > 25) {
        throw new DomainException('需要有效 batch_id 和 1..25 篇 articles。');
    }
    $seen = array();
    foreach ($batch->articles as $article) {
        if (!($article instanceof stdClass) || !is_int($article->problem_id ?? null) ||
            $article->problem_id <= 0 || $article->problem_id > 2147483647) {
            throw new DomainException('题号必须是正整数。');
        }
        if (isset($seen[$article->problem_id])) throw new DomainException('同批次题号重复。');
        $seen[$article->problem_id] = true;
        if (!is_string($article->title ?? null) || trim($article->title) === '' ||
            system_editorial_string_length($article->title) > 120 ||
            !is_string($article->content ?? null) || trim($article->content) === '' ||
            system_editorial_string_length($article->content) > 50000) {
            throw new DomainException('标题须为 1..120 字，正文须为 1..50000 字。');
        }
        foreach (array('problem_fingerprint', 'article_sha256', 'code_sha256') as $field) {
            if (!system_editorial_hash($article->$field ?? null)) throw new DomainException('需要小写 SHA-256 哈希。');
        }
        if (!hash_equals($article->article_sha256, hash('sha256', $article->content))) {
            throw new DomainException('正文哈希不匹配。');
        }
        if (!in_array($article->language ?? null, array('c', 'cpp17'), true)) {
            throw new DomainException('仅允许已验证的 c 或 cpp17 程序。');
        }
        $validation = $article->validation ?? null;
        if (!($validation instanceof stdClass) || ($validation->status ?? null) !== 'passed' ||
            !is_int($validation->case_count ?? null) || $validation->case_count <= 0 ||
            !system_editorial_hash($validation->code_sha256 ?? null) ||
            !hash_equals($article->code_sha256, $validation->code_sha256)) {
            throw new DomainException('需要通过真实用例的验证记录，且代码哈希须一致。');
        }
        $code = system_editorial_final_code($article->content, $article->language);
        if (!hash_equals($article->code_sha256, hash('sha256', $code))) {
            throw new DomainException('最后参考代码哈希不匹配。');
        }
    }
    // These includes only define functions. No configuration or DB is loaded here.
    require_once getcwd().'/include/editorial.inc.php';
    foreach ($batch->articles as $article) {
        if (editorial_effective_length($article->content, 'markdown') < editorial_min_prose()) {
            throw new DomainException('代码之外的解释至少需要 120 个汉字。');
        }
    }
    return $batch;
}

function system_editorial_fetch($db, $sql, $args = array(), $all = false) {
    $statement = $db->prepare($sql);
    try {
        $statement->execute($args);
        return $all ? $statement->fetchAll(PDO::FETCH_ASSOC) : $statement->fetch(PDO::FETCH_ASSOC);
    } finally {
        $statement->closeCursor();
    }
}

function system_editorial_execute($db, $sql, $args = array()) {
    $statement = $db->prepare($sql);
    try {
        $statement->execute($args);
    } finally {
        $statement->closeCursor();
    }
}

// Called only after validation and the CLI's deferred db_info include.
function system_editorial_publish($batch, $apply) {
    $deadline = hrtime(true) + 30000000000;
    $db = null;
    $locked = false;
    $lockName = null;
    try {
        $db = editorial_db();
        $db->exec('SET SESSION max_statement_time=5');
        $db->exec('SET SESSION innodb_lock_wait_timeout=5');
        $db->exec('SET SESSION lock_wait_timeout=5');
        $db->exec('SET NAMES utf8mb4');
        $database = system_editorial_fetch($db, 'SELECT DATABASE() AS name');
        $lockName = 'system_editorials_'.substr(hash('sha256', $database['name']), 0, 32);
        $lock = system_editorial_fetch($db, 'SELECT GET_LOCK(?,0) AS acquired', array($lockName));
        if ((int)$lock['acquired'] !== 1) throw new DomainException('另一个系统题解导入器正在运行。');
        $locked = true;
        $engines = system_editorial_fetch($db, "SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('users','problem_editorial')", array(), true);
        if (count($engines) !== 2) throw new DomainException('所需数据表不存在。');
        foreach ($engines as $table) {
            if (strcasecmp($table['ENGINE'] ?? '', 'InnoDB') !== 0) {
                throw new DomainException('users 和 problem_editorial 必须均为 InnoDB，禁止自动迁移。');
            }
        }
        $db->beginTransaction();
        $user = system_editorial_user();
        $account = system_editorial_fetch($db,
            'SELECT user_id,defunct,password,nick,register_num FROM users WHERE user_id=?', array($user));
        if ($account && ($account['user_id'] !== system_editorial_user() || $account['defunct'] !== 'Y' ||
            $account['password'] !== '!' || $account['nick'] !== '系统题解' || (int)$account['register_num'] !== 1)) {
            throw new DomainException('系统作者 ID 已被不同身份的账号占用。');
        }
        $result = array('batch_id'=>$batch->batch_id, 'dry_run'=>!$apply,
            'inserted'=>0, 'skipped'=>0, 'articles'=>array());
        $new = array();
        foreach ($batch->articles as $article) {
            if (hrtime(true) >= $deadline) throw new RuntimeException('发布批次超过 30 秒预算。');
            $id = $article->problem_id;
            // Reuse exactly the public-practice predicate used by editorial_problem().
            $problem = system_editorial_fetch($db, 'SELECT p.problem_id,p.title,p.description,p.input,p.output,
                p.sample_input,p.sample_output,p.hint,p.source,p.time_limit,p.memory_limit,p.spj
                FROM problem p WHERE p.problem_id=? AND '.editorial_public_sql().' FOR UPDATE', array($id));
            if (!$problem) throw new DomainException('题目 '.$id.' 不在公开练习范围。');
            if (!hash_equals($article->problem_fingerprint, system_editorial_problem_fingerprint($problem))) {
                throw new DomainException('题目 '.$id.' 已变化，必须重新验证。');
            }
            $existing = system_editorial_fetch($db,
                'SELECT id,title,content,content_format,status FROM problem_editorial
                WHERE problem_id=? AND user_id=? LIMIT 2 FOR UPDATE', array($id,$user), true);
            if (count($existing) > 1) throw new DomainException('题目 '.$id.' 已存在多份系统题解。');
            if ($existing) {
                $row = $existing[0];
                if ($row['title'] !== $article->title || $row['content'] !== $article->content ||
                    $row['content_format'] !== 'markdown' || $row['status'] !== 'approved') {
                    throw new DomainException('题目 '.$id.' 的系统题解与本批次冲突，禁止覆盖。');
                }
                ++$result['skipped'];
                $result['articles'][] = array('problem_id'=>$id,'id'=>(int)$row['id'],'action'=>'skipped');
            } else {
                $new[] = $article;
                $result['articles'][] = array('problem_id'=>$id,'id'=>null,'action'=>$apply ? 'inserted' : 'would_insert');
            }
        }
        if ($apply) {
            if (!$account) {
                system_editorial_execute($db, "INSERT INTO users(user_id,defunct,password,nick,register_num,reg_time)
                    VALUES(?,'Y','!','系统题解',1,NOW())", array($user));
            }
            foreach ($new as $article) {
                if (hrtime(true) >= $deadline) throw new RuntimeException('发布批次超过 30 秒预算。');
                system_editorial_execute($db, "INSERT INTO problem_editorial
                    (problem_id,user_id,title,content,content_format,status,review_note,reviewer,reviewed_at)
                    VALUES(?,?,?,?,'markdown','approved',?,NULL,NOW())",
                    array($article->problem_id,$user,$article->title,$article->content,'system-publish:'.$batch->batch_id));
                $insertId = (int)$db->lastInsertId();
                foreach ($result['articles'] as &$entry) {
                    if ($entry['problem_id'] === $article->problem_id) $entry['id'] = $insertId;
                }
                unset($entry);
                ++$result['inserted'];
            }
            $wallet = system_editorial_fetch($db, 'SELECT balance FROM coin_wallet WHERE user_id=?', array($user));
            $ledger = system_editorial_fetch($db, 'SELECT COUNT(*) AS n FROM coin_ledger WHERE user_id=?', array($user));
            if (($wallet && (int)$wallet['balance'] !== 0) || (int)$ledger['n'] !== 0) throw new RuntimeException('系统作者金币状态异常，整批回滚。');
            $db->commit();
        } else {
            $db->rollBack();
        }
        return $result;
    } finally {
        // Nested finally blocks ensure release/connection close even if rollback fails.
        try {
            if ($db && $db->inTransaction()) $db->rollBack();
        } finally {
            try {
                if ($db && $locked) {
                    $released = system_editorial_fetch($db, 'SELECT RELEASE_LOCK(?) AS released', array($lockName));
                    if ((int)$released['released'] !== 1) throw new RuntimeException('无法确认导入锁已释放。');
                }
            } finally {
                $db = null;
                $GLOBALS['dbh'] = null;
            }
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $options = array_slice($argv, 1);
        if ($options !== array() && $options !== array('--apply')) {
            throw new DomainException('用法：php publish-system-editorials.php [--apply] < private-batch.json');
        }
        $batch = system_editorial_validate_input(system_editorial_read_input(STDIN));
        // Configuration may define globals and starts a session in existing installs.
        // No configuration or connection is touched for invalid input.
        if (!is_file(getcwd().'/include/db_info.inc.php')) throw new RuntimeException('缺少数据库运行配置。');
        require_once getcwd().'/include/db_info.inc.php';
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $result = system_editorial_publish($batch, $options === array('--apply'));
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    } catch (Throwable $e) {
        // PDO exception text may contain connection details. Expose only SQLSTATE.
        $message = $e instanceof PDOException ? '数据库操作失败（SQLSTATE '.(string)$e->getCode().'）。' : $e->getMessage();
        fwrite(STDERR, json_encode(array('error'=>$message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        exit(1);
    }
}
