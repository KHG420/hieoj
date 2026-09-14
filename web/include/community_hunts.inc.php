<?php
require_once __DIR__.'/adventure.inc.php';

function hunt_text($data, $key) {
    return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : '';
}

function hunt_query($sql, ...$args) {
    $result = pdo_query($sql, ...$args);
    if ($result === false) throw new RuntimeException('保存或读取失败，请稍后重试。');
    return $result;
}

function hunt_languages() {
    global $OJ_LANGMASK;
    return array_filter(array(1=>'C++', 6=>'Python 3'), function ($id) use ($OJ_LANGMASK) {
        return !(intval($OJ_LANGMASK) & (1 << $id));
    }, ARRAY_FILTER_USE_KEY);
}

function hunt_validate_text($value, $limit, $label, $required = true) {
    if (($required && trim($value) === '') || strlen($value) > $limit ||
        !preg_match('//u', $value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x{10000}-\x{10FFFF}]/u', $value)) {
        throw new InvalidArgumentException($label.'不能为空或超出长度限制；请使用普通文字与代码，不含特殊控制字符或表情。');
    }
}

function hunt_fields($data) {
    $fields = array();
    foreach (array('title'=>array(480,'标题'), 'statement'=>array(16000,'题意与输入范围'),
        'buggy_source'=>array(32000,'错误程序'), 'reference_source'=>array(32000,'参考程序'),
        'validator_source'=>array(32000,'输入校验程序')) as $key=>$rule) {
        $fields[$key] = trim(hunt_text($data, $key));
        hunt_validate_text($fields[$key], $rule[0], $rule[1]);
    }
    if (preg_match_all('/./us', $fields['title']) > 160) throw new InvalidArgumentException('标题最多 160 个字符。');
    $language = hunt_text($data, 'language');
    if (!array_key_exists($language, hunt_languages())) throw new InvalidArgumentException('请选择当前开放的 C++ 或 Python 3。');
    $fields['language'] = intval($language);
    return $fields;
}

function hunt_challenge($id, $user, $admin) {
    $rows = hunt_query('SELECT * FROM hunt_challenge WHERE id=?', $id);
    if (!$rows || ($rows[0]['hidden'] && $rows[0]['author'] !== $user && !$admin)) return null;
    return $rows[0];
}

function hunt_save($data, $id, $user) {
    $f = hunt_fields($data);
    if (!$id) {
        $recent = hunt_query('SELECT COUNT(*) n FROM hunt_challenge WHERE author=? AND created_at>DATE_SUB(NOW(), INTERVAL 1 HOUR)', $user);
        if ($recent[0]['n'] >= 10) throw new InvalidArgumentException('每小时最多发布 10 道题，请稍后再来。');
        return intval(hunt_query('INSERT INTO hunt_challenge(author,title,statement,buggy_source,reference_source,validator_source,language) VALUES(?,?,?,?,?,?,?)', $user, ...array_values($f)));
    }
    $updated = hunt_query('UPDATE hunt_challenge SET title=?,statement=?,buggy_source=?,reference_source=?,validator_source=?,language=?,version=version+1,updated_at=NOW() WHERE id=? AND author=? AND version=?',
        ...array_merge(array_values($f), array($id, $user, intval(hunt_text($data,'version')))));
    if (!$updated) throw new InvalidArgumentException('题目已更新或你没有编辑权限，请刷新后重试。');
    return $id;
}

function hunt_submit($id, $version, $input, $user) {
    global $dbh, $OJ_REDIS, $OJ_REDISSERVER, $OJ_REDISPORT, $OJ_REDISAUTH, $OJ_REDISQNAME;
    hunt_validate_text($input, 8192, '反例输入', false);
    $ids = array(); $redis = null;
    try {
        // Lock the user across sessions; MyISAM payload rows are cleaned explicitly on failure.
        hunt_query('SELECT user_id FROM users WHERE user_id=?', $user);
        $dbh->beginTransaction();
        hunt_query('SELECT user_id FROM users WHERE user_id=? FOR UPDATE', $user);
        $rows = hunt_query('SELECT * FROM hunt_challenge WHERE id=? AND hidden=0 FOR UPDATE', $id);
        if (!$rows || intval($rows[0]['version']) !== $version) throw new InvalidArgumentException('题目已更新或下架，请刷新后重试。');
        $c = $rows[0];
        if (!isset(hunt_languages()[$c['language']])) throw new InvalidArgumentException('这道题的语言暂未开放。');
        $recent = hunt_query('SELECT COUNT(*) n FROM hunt_attempt WHERE user_id=? AND created_at>DATE_SUB(NOW(), INTERVAL 1 MINUTE)', $user);
        $pending = hunt_query('SELECT COUNT(*) n FROM hunt_attempt a JOIN solution s ON s.solution_id IN (a.validator_sid,a.reference_sid,a.buggy_sid) WHERE a.user_id=? AND s.result IN (0,1,2,3,14)', $user);
        if ($recent[0]['n'] >= 3 || $pending[0]['n'] >= 9) throw new InvalidArgumentException('已有多次验证正在等待处理，请稍后再提交。');
        if (!empty($OJ_REDIS)) {
            $redis = new Redis();
            if (!$redis->connect($OJ_REDISSERVER, $OJ_REDISPORT) || (isset($OJ_REDISAUTH) && !$redis->auth($OJ_REDISAUTH))) throw new RuntimeException('判题队列暂不可用，请稍后重试。');
        }
        foreach (array('validator_source','reference_source','buggy_source') as $key) {
            $sid = intval(hunt_query('INSERT INTO solution(problem_id,user_id,in_date,language,ip,code_length,result) VALUES(0,?,NOW(),?,?,?,14)', $user, $c['language'], substr($_SERVER['REMOTE_ADDR'] ?? '',0,46), strlen($c[$key])));
            $ids[] = $sid;
            hunt_query('INSERT INTO source_code(solution_id,source) VALUES(?,?)', $sid, $c[$key]);
            hunt_query('INSERT INTO source_code_user(solution_id,source) VALUES(?,?)', $sid, $c[$key]);
            hunt_query('INSERT INTO custominput(solution_id,input_text) VALUES(?,?)', $sid, $input);
        }
        $attempt = intval(hunt_query('INSERT INTO hunt_attempt(challenge_id,challenge_version,user_id,input_text,validator_sid,reference_sid,buggy_sid) VALUES(?,?,?,?,?,?,?)', $id, $version, $user, $input, ...$ids));
        hunt_query('UPDATE solution SET result=0 WHERE solution_id IN (?,?,?) AND result=14', ...$ids);
        $dbh->commit();
    } catch (Throwable $e) {
        if ($dbh && $dbh->inTransaction()) $dbh->rollBack();
        foreach ($ids as $sid) foreach (array('source_code','source_code_user','custominput') as $table) pdo_query("DELETE FROM $table WHERE solution_id=?", $sid);
        if ($redis) $redis->close();
        throw $e;
    }
    if ($redis) {
        try {
            if (!$redis->lPush($OJ_REDISQNAME, ...$ids)) throw new RuntimeException('Queue notification failed');
        } catch (Throwable $e) {
            error_log('Hunt queue notification failed: '.$attempt);
            throw new InvalidArgumentException('挑战记录 #'.$attempt.' 已保存，但队列通知失败，请联系管理员检查判题服务。');
        } finally { $redis->close(); }
    }
    return $attempt;
}

function hunt_verdict($jobs) {
    foreach (array('validator','reference','buggy') as $role) {
        if (!isset($jobs[$role]) || in_array(intval($jobs[$role]['result']), array(0,1,2,3,14), true)) return array('pending','正在排队或验证中，请稍后刷新结果。');
    }
    $v = $jobs['validator']; $r = $jobs['reference']; $b = $jobs['buggy'];
    foreach ($jobs as $job) if (intval($job['result']) === 11) return array('error','题目程序编译失败，请作者检查并修正。');
    if (intval($v['result']) !== 13 || !isset($v['output']) || !in_array(trim($v['output']), array('VALID','INVALID'), true)) return array('error','输入校验程序未正常完成或未输出 VALID / INVALID，请作者检查。');
    if (trim($v['output']) === 'INVALID') return array('invalid','输入不符合题目约束，请修改后重试。');
    if (intval($r['result']) !== 13 || !isset($r['output'])) return array('error','参考程序未正常完成，请作者检查；本次不计为命中。');
    if (in_array(intval($b['result']), array(7,8,10), true)) return array('hit','命中！合法输入使错误程序超时、超出内存限制或发生运行错误。');
    if (intval($b['result']) !== 13 || !isset($b['output'])) return array('error','错误程序未产生可比较的完整输出，请作者检查。');
    // Whitespace-separated tokens match ordinary OJ output conventions.
    $normalize = function ($s) { return preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY); };
    return $normalize($r['output']) !== $normalize($b['output']) ? array('hit','命中！这组合法输入让两个程序给出了不同答案。') : array('miss','还没有击中：两个程序的输出相同，再试试边界情况。');
}

function hunt_attempt_result($attempt) {
    $jobs = array();
    foreach (array('validator','reference','buggy') as $role) {
        $rows = hunt_query('SELECT s.result,r.error output FROM solution s LEFT JOIN runtimeinfo r ON r.solution_id=s.solution_id WHERE s.solution_id=?', $attempt[$role.'_sid']);
        if ($rows) $jobs[$role] = $rows[0];
    }
    return array(hunt_verdict($jobs), $jobs);
}

function hunt_comment_add($target, $content, $user) {
    hunt_validate_text($content, 6000, '评论');
    $recent = hunt_query('SELECT COUNT(*) n FROM hunt_comment WHERE author=? AND created_at>DATE_SUB(NOW(), INTERVAL 1 MINUTE)', $user);
    if ($recent[0]['n'] >= 5) throw new InvalidArgumentException('评论发送过于频繁，请稍后重试。');
    return hunt_query('INSERT INTO hunt_comment(target,author,content) VALUES(?,?,?)', $target, $user, trim($content));
}
