<?php
/**
 * 学院班级目录 MySQL/MariaDB 集成测试（需要真实数据库）。
 *
 * 覆盖离线 SQLite 测试无法检验的部分：
 *   R1 并发：apply 先取 advisory lock、在锁内重新读取并规划；两个同快照并发均成功，
 *            第二个零新增零更新；不同快照并发互不覆盖。
 *   R2 migration 完整性：部分迁移（引擎/宽度/唯一索引缺失）apply 明确拒绝且零写入；
 *            完整迁移后触发器异常触发事务回滚。
 *   R3 total 必填：缺失 total 的真实 importer dry-run/apply 拒绝且零写入。
 *   R4 反向映射冲突：已同步 source_id 的本地 id 与快照 code 不一致时拒绝整次写。
 *   AC3 users 表内容始终不变；旧重复 num 数据在迁移后保留。
 *
 * 不连接生产库：由 scripts/academic-directory-sync/mysql_integration_test.sh 启动一次性
 * MariaDB 容器并注入 db_info.inc.php 后运行本文件。
 *
 * 运行（由 wrapper 调用）：php web/tests/academic_directory_mysql_test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/include/db_info.inc.php';

$GLOBALS['IT_FAIL'] = 0;
$GLOBALS['IT_CHECKS'] = 0;

function it_check($condition, $message)
{
    $GLOBALS['IT_CHECKS']++;
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $message . "\n";
    if (!$condition) {
        $GLOBALS['IT_FAIL']++;
    }
}

function it_query($sql, array $params = array())
{
    global $dbh;
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return $rows;
}

function it_scalar($sql, array $params = array())
{
    $rows = it_query($sql, $params);
    if (count($rows) === 0) {
        return null;
    }
    return array_values($rows[0])[0];
}

/** 重置基线：schoolList 默认 MyISAM、collegiate 默认 InnoDB，含重复 num 与历史学院。 */
function it_reset($schoolEngine = 'MyISAM', $collegeEngine = 'InnoDB')
{
    global $dbh;
    foreach (array('itest_reject', 'users', 'schoolList', 'collegiate') as $table) {
        if (strpos($table, 'itest') === 0) {
            $dbh->exec("DROP TRIGGER IF EXISTS `$table`");
        } else {
            $dbh->exec("DROP TABLE IF EXISTS `$table`");
        }
    }
    $dbh->exec(
        "CREATE TABLE `collegiate` (`id` INT NULL, `name` VARCHAR(100) NULL)"
        . " ENGINE=$collegeEngine DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin"
    );
    $dbh->exec(
        "CREATE TABLE `schoolList` (`school_id` INT NOT NULL AUTO_INCREMENT, `num` VARCHAR(10) NOT NULL,"
        . " `value` VARCHAR(50) DEFAULT NULL, `join_time` DATETIME DEFAULT NULL, `collegiate_id` INT DEFAULT NULL,"
        . " PRIMARY KEY (`school_id`,`num`)) ENGINE=$schoolEngine DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci"
    );
    $dbh->exec(
        "CREATE TABLE `users` (`user_id` VARCHAR(20) NOT NULL, `school` VARCHAR(20) NULL,"
        . " `xueYuan` VARCHAR(50) NULL, PRIMARY KEY (`user_id`)) ENGINE=InnoDB"
    );
    $dbh->exec(
        "INSERT INTO `collegiate` (`id`,`name`) VALUES"
        . " (1,'电气与信息工程学院'),(5,'经济学院'),(7,'管理学院'),(80,'研究生院（研究生工作部）')"
    );
    // 重复 num：(1,2) 同为 2004010101。
    $dbh->exec(
        "INSERT INTO `schoolList` (`school_id`,`num`,`value`,`join_time`,`collegiate_id`) VALUES"
        . " (1,'2004010101','电气工程0401',NOW(),2),"
        . " (2,'2004010101','电气工程0401',NOW(),1),"
        . " (3,'02011202','电气工程0202',NOW(),1),"
        . " (4,'2014360101','动力工程2014',NOW(),80),"
        . " (5,'03010101','经济工程0301',NOW(),5)"
    );
    $dbh->exec(
        "INSERT INTO `users` (`user_id`,`school`,`xueYuan`) VALUES"
        . " ('student','电气工程0401','电气与信息工程学院')"
    );
}

function it_run_php(array $scriptArgs, $stdin = null, $concurrent = null)
{
    $cmd = array_merge(array(PHP_BINARY), $scriptArgs);
    $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $proc = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        return array('code' => 127, 'stdout' => '', 'stderr' => 'proc_open failed');
    }
    if ($stdin !== null) {
        fwrite($pipes[0], $stdin);
    }
    fclose($pipes[0]);
    if ($concurrent !== null) {
        $concurrent($proc, $pipes);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    return array('code' => $code, 'stdout' => $stdout, 'stderr' => $stderr);
}

function it_migrate()
{
    return it_run_php(array(dirname(__DIR__) . '/cli/academic_directory_migrate.php', '--apply'));
}

function it_import($snapshot, $apply = false, $file = null)
{
    $args = array(dirname(__DIR__) . '/cli/academic_directory_import.php');
    if ($apply) {
        $args[] = '--apply';
    }
    if ($file !== null) {
        $args[] = '--file=' . $file;
    }
    return it_run_php($args, $snapshot === null ? null : json_encode($snapshot, JSON_UNESCAPED_UNICODE));
}

function it_class($sourceId, $bh, $bj, $collegeSource, $collegeName)
{
    return array(
        'field0' => $sourceId,
        'bh' => $bh,
        'bj' => $bj,
        'field6' => $collegeSource,
        'xx0301$dwmc' => $collegeName,
    );
}

function it_snapshot(array $classes, array $colleges = null)
{
    if ($colleges === null) {
        $colleges = array(
            array('source_id' => '01', 'code' => '01', 'name' => '电气与信息工程学院'),
            array('source_id' => '36', 'code' => '80', 'name' => '研究生院（研究生工作部）'),
        );
    }
    return array('colleges' => $colleges, 'total' => count($classes), 'classes' => $classes);
}

function it_report($label, $result)
{
    // 成功时 importer 写 stdout，失败时写 stderr，两者都是 JSON。
    $decoded = json_decode(trim($result['stdout']), true);
    if (!is_array($decoded)) {
        $decoded = json_decode(trim($result['stderr']), true);
    }
    return array(
        'label' => $label,
        'code' => $result['code'],
        'json' => is_array($decoded) ? $decoded : null,
        'raw' => trim($result['stdout'] . ' ' . $result['stderr']),
    );
}

function it_engine($table)
{
    return it_scalar(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        array($table)
    );
}

function it_num_length($table, $column)
{
    return it_scalar(
        'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS'
        . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        array($table, $column)
    );
}

function it_has_unique($table, $column)
{
    $rows = it_query(
        'SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS'
        . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0'
        . ' ORDER BY INDEX_NAME, SEQ_IN_INDEX',
        array($table)
    );
    $byIndex = array();
    foreach ($rows as $row) {
        $byIndex[$row['INDEX_NAME']][] = $row['COLUMN_NAME'];
    }
    foreach ($byIndex as $cols) {
        if (count($cols) === 1 && $cols[0] === $column) {
            return true;
        }
    }
    return false;
}

// ===========================================================================
// 前置：确认连到的是可写的 MySQL/MariaDB
// ===========================================================================
if (!isset($dbh) || !($dbh instanceof PDO) || $dbh->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    fwrite(STDERR, "本测试需要 MySQL/MariaDB 连接（由 mysql_integration_test.sh 提供）\n");
    exit(2);
}
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ===========================================================================
// 场景 1：migration 幂等 + 引擎/宽度/唯一索引（AC5）
// ===========================================================================
it_reset('MyISAM', 'InnoDB');
$before = it_scalar('SELECT COUNT(*) FROM schoolList');
$migrate = it_report('migrate-1', it_migrate());
it_check($migrate['code'] === 0 && $migrate['json']['ok'] === true, 'migration 首次 apply 成功');
it_check(it_engine('schoolList') === 'InnoDB', 'schoolList 迁移后为 InnoDB');
it_check(it_engine('collegiate') === 'InnoDB', 'collegiate 迁移后为 InnoDB');
it_check((int)it_num_length('schoolList', 'num') >= 64, 'schoolList.num 扩到 >=64');
it_check((int)it_num_length('schoolList', 'source_id') >= 64, 'schoolList.source_id 宽度 >=64');
it_check((int)it_num_length('collegiate', 'source_id') >= 64, 'collegiate.source_id 宽度 >=64');
it_check(it_has_unique('schoolList', 'source_id'), 'schoolList.source_id 有单列唯一索引');
it_check(it_has_unique('collegiate', 'source_id'), 'collegiate.source_id 有单列唯一索引');
it_check((int)$before === (int)it_scalar('SELECT COUNT(*) FROM schoolList'), '迁移不删除旧行');
it_check((int)it_scalar('SELECT COUNT(*) FROM schoolList WHERE num = ?', array('2004010101')) === 2, '重复 num 旧行保留');
$migrate2 = it_report('migrate-2', it_migrate());
it_check(
    $migrate2['code'] === 0 && is_array($migrate2['json']['actions']) && count($migrate2['json']['actions']) === 0,
    'migration 重复执行 actions 为空（幂等）'
);

// collegiate 为 MyISAM 时 migration 必须转换
it_reset('MyISAM', 'MyISAM');
$migrate3 = it_report('migrate-collegiate-myisam', it_migrate());
it_check($migrate3['code'] === 0 && it_engine('collegiate') === 'InnoDB', 'collegiate MyISAM 会被迁移为 InnoDB');

// ===========================================================================
// 场景 2：部分迁移拒绝（R2）
// ===========================================================================
function it_partial_migrate($convertSchool)
{
    global $dbh;
    $dbh->exec('ALTER TABLE `collegiate` ADD COLUMN `source_id` VARCHAR(64) NULL DEFAULT NULL');
    $dbh->exec('ALTER TABLE `collegiate` ADD UNIQUE INDEX `uniq_collegiate_source_id` (`source_id`)');
    $dbh->exec('ALTER TABLE `schoolList` ADD COLUMN `source_id` VARCHAR(64) NULL DEFAULT NULL');
    $dbh->exec('ALTER TABLE `schoolList` MODIFY COLUMN `num` VARCHAR(64) NOT NULL');
    $dbh->exec('ALTER TABLE `schoolList` ADD UNIQUE INDEX `uniq_schoolList_source_id` (`source_id`)');
    if ($convertSchool) {
        $dbh->exec('ALTER TABLE `schoolList` ENGINE=InnoDB');
    }
}

// 2a：有 source_id 列/唯一索引，但 schoolList 仍是 MyISAM
it_reset('MyISAM', 'InnoDB');
it_partial_migrate(false);
$partialSnapshot = it_snapshot(array(
    it_class('itest-partial-a', '2026010101', '部分迁移A', '01', '电气与信息工程学院'),
));
$partialApply = it_report('partial-apply-school-myisam', it_import($partialSnapshot, true));
it_check($partialApply['code'] !== 0 && $partialApply['json']['ok'] === false, '部分迁移（schoolList MyISAM）apply 明确拒绝');
it_check(mb_strpos($partialApply['json']['error'], 'migration') !== false, '拒绝信息提示重跑 migration');
it_check(
    (int)it_scalar('SELECT COUNT(*) FROM schoolList WHERE source_id = ?', array('itest-partial-a')) === 0,
    '部分迁移拒绝时零写入（schoolList MyISAM）'
);

// 2b：schoolList 已 InnoDB，但 collegiate 仍是 MyISAM
it_reset('MyISAM', 'MyISAM');
it_partial_migrate(true);
$partialApply2 = it_report('partial-apply-collegiate-myisam', it_import($partialSnapshot, true));
it_check($partialApply2['code'] !== 0 && $partialApply2['json']['ok'] === false, '部分迁移（collegiate MyISAM）apply 明确拒绝');
it_check(
    (int)it_scalar('SELECT COUNT(*) FROM schoolList WHERE source_id = ?', array('itest-partial-a')) === 0,
    '部分迁移拒绝时零写入（collegiate MyISAM）'
);

// 2c：缺 num 宽度（列存在但仍是 varchar(10)）
it_reset('InnoDB', 'InnoDB');
$dbh->exec('ALTER TABLE `collegiate` ADD COLUMN `source_id` VARCHAR(64) NULL DEFAULT NULL');
$dbh->exec('ALTER TABLE `collegiate` ADD UNIQUE INDEX `uniq_collegiate_source_id` (`source_id`)');
$dbh->exec('ALTER TABLE `schoolList` ADD COLUMN `source_id` VARCHAR(64) NULL DEFAULT NULL');
$dbh->exec('ALTER TABLE `schoolList` ADD UNIQUE INDEX `uniq_schoolList_source_id` (`source_id`)');
$partialApply3 = it_report('partial-apply-num-width', it_import($partialSnapshot, true));
it_check($partialApply3['code'] !== 0, '部分迁移（num 宽度不足）apply 明确拒绝');
it_check(
    (int)it_scalar('SELECT COUNT(*) FROM schoolList WHERE source_id = ?', array('itest-partial-a')) === 0,
    '部分迁移拒绝时零写入（num 宽度）'
);

// ===========================================================================
// 场景 3：完整迁移 + 触发器异常 → 事务回滚（R2）
// ===========================================================================
it_reset('MyISAM', 'InnoDB');
it_migrate();
$dbh->exec(
    "CREATE TRIGGER `itest_reject` BEFORE INSERT ON `schoolList` FOR EACH ROW"
    . " BEGIN IF NEW.source_id = 'itest-rollback-b' THEN"
    . " SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'itest forced failure'; END IF; END"
);
$rollbackSnapshot = it_snapshot(array(
    it_class('itest-rollback-a', '2026020101', '回滚A', '01', '电气与信息工程学院'),
    it_class('itest-rollback-b', '2026020102', '回滚B', '01', '电气与信息工程学院'),
));
$rollback = it_report('rollback-apply', it_import($rollbackSnapshot, true));
it_check($rollback['code'] !== 0 && $rollback['json']['ok'] === false, '触发器异常时 apply 非零退出');
it_check(
    (int)it_scalar('SELECT COUNT(*) FROM schoolList WHERE source_id IN (?,?)', array('itest-rollback-a', 'itest-rollback-b')) === 0,
    '事务回滚：先插入的行也被回滚，零残留'
);
$dbh->exec('DROP TRIGGER IF EXISTS `itest_reject`');

// ===========================================================================
// 场景 4：total 必填（R3）
// ===========================================================================
it_reset('MyISAM', 'InnoDB');
it_migrate();
$noTotal = it_snapshot(array(
    it_class('itest-nototal', '2026030101', '无总数', '01', '电气与信息工程学院'),
));
unset($noTotal['total']);
$noTotalDry = it_report('nototal-dry', it_import($noTotal, false));
it_check($noTotalDry['code'] !== 0 && $noTotalDry['json']['ok'] === false, '缺少 total 的 dry-run 拒绝');
it_check(mb_strpos($noTotalDry['json']['error'], 'total') !== false, '拒绝信息指出 total');
$noTotalApply = it_report('nototal-apply', it_import($noTotal, true));
it_check($noTotalApply['code'] !== 0, '缺少 total 的 apply 拒绝');
it_check(
    (int)it_scalar('SELECT COUNT(*) FROM schoolList WHERE source_id = ?', array('itest-nototal')) === 0,
    '缺少 total 时零写入'
);

// ===========================================================================
// 场景 5：反向映射冲突（R4）
// ===========================================================================
it_reset('MyISAM', 'InnoDB');
it_migrate();
$baseClasses = array(it_class('itest-r4-1', '2026040101', '反向映射班', '36', '研究生院（研究生工作部）'));
$baseApply = it_report('reverse-base', it_import(it_snapshot($baseClasses), true));
it_check($baseApply['code'] === 0, '基线快照 apply 成功');
it_check(
    (string)it_scalar('SELECT source_id FROM collegiate WHERE id = 80') === '36',
    'source_id 36 正确关联本地学院 80'
);
$conflictColleges = array(
    array('source_id' => '01', 'code' => '01', 'name' => '电气与信息工程学院'),
    array('source_id' => '36', 'code' => '81', 'name' => '研究生院（研究生工作部）'),
);
$conflict = it_report('reverse-conflict', it_import(it_snapshot($baseClasses, $conflictColleges), false));
it_check($conflict['code'] !== 0 && $conflict['json']['ok'] === false, 'source_id 36 的 code 改 81 被拒绝');
it_check(mb_strpos($conflict['json']['error'], '不一致') !== false, '冲突信息说明 code 与本地 id 不一致');
it_check(
    (string)it_scalar('SELECT source_id FROM collegiate WHERE id = 80') === '36'
    && (int)it_scalar('SELECT COUNT(*) FROM collegiate WHERE id = 81') === 0,
    '冲突拒绝后本地 id 未被隐式改写'
);

// ===========================================================================
// 场景 6：并发 apply（R1）
// ===========================================================================
it_reset('MyISAM', 'InnoDB');
it_migrate();
$concurrentClasses = array();
for ($i = 1; $i <= 5; $i++) {
    $concurrentClasses[] = it_class(
        'itest-conc-' . $i,
        '20260501' . sprintf('%02d', $i),
        '并发班' . $i,
        '01',
        '电气与信息工程学院'
    );
}
$concurrentSnapshot = it_snapshot($concurrentClasses);
$concurrentJson = json_encode($concurrentSnapshot, JSON_UNESCAPED_UNICODE);

// 同时启动两个相同快照的 apply；锁在读取/规划之前，第二个必须重新读取后零新增。
$handles = array();
for ($i = 0; $i < 2; $i++) {
    $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $cmd = array(PHP_BINARY, dirname(__DIR__) . '/cli/academic_directory_import.php', '--apply');
    $proc = proc_open($cmd, $descriptors, $pipes);
    fwrite($pipes[0], $concurrentJson);
    fclose($pipes[0]);
    $handles[] = array('proc' => $proc, 'pipes' => $pipes);
}
$results = array();
foreach ($handles as $handle) {
    $stdout = stream_get_contents($handle['pipes'][1]);
    $stderr = stream_get_contents($handle['pipes'][2]);
    fclose($handle['pipes'][1]);
    fclose($handle['pipes'][2]);
    $code = proc_close($handle['proc']);
    $results[] = array(
        'code' => $code,
        'json' => json_decode($stdout, true),
        'raw' => trim($stdout . ' ' . $stderr),
    );
}
$okBoth = $results[0]['code'] === 0 && $results[1]['code'] === 0;
it_check($okBoth, '同快照并发 apply 两个进程都成功（无 unique 错误）');
$inserts = array($results[0]['json']['stats']['class_insert'], $results[1]['json']['stats']['class_insert']);
sort($inserts);
it_check($inserts === array(0, 5), '并发第二个在锁内重新读取后零新增（另一个新增 5）');
$loser = $results[0]['json']['stats']['class_insert'] === 0 ? $results[0] : $results[1];
it_check(
    $loser['json']['stats']['class_update'] === 0 && $loser['json']['stats']['class_keep'] === 5,
    '并发第二个零更新且全部计入保留'
);
it_check(
    (int)it_scalar('SELECT COUNT(*) FROM schoolList WHERE source_id LIKE ?', array('itest-conc-%')) === 5,
    '并发后每个源 ID 恰好一行'
);

// 不同快照并发：互不覆盖，各自写入
$snapA = it_snapshot(array(it_class('itest-seq-a', '2026060101', '序列A', '01', '电气与信息工程学院')));
$snapB = it_snapshot(array(it_class('itest-seq-b', '2026060102', '序列B', '01', '电气与信息工程学院')));
$handles = array();
foreach (array($snapA, $snapB) as $snap) {
    $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $cmd = array(PHP_BINARY, dirname(__DIR__) . '/cli/academic_directory_import.php', '--apply');
    $proc = proc_open($cmd, $descriptors, $pipes);
    fwrite($pipes[0], json_encode($snap, JSON_UNESCAPED_UNICODE));
    fclose($pipes[0]);
    $handles[] = array('proc' => $proc, 'pipes' => $pipes);
}
$seqCodes = array();
foreach ($handles as $handle) {
    $stdout = stream_get_contents($handle['pipes'][1]);
    $stderr = stream_get_contents($handle['pipes'][2]);
    fclose($handle['pipes'][1]);
    fclose($handle['pipes'][2]);
    $seqCodes[] = proc_close($handle['proc']);
}
it_check($seqCodes === array(0, 0), '不同快照并发 apply 都成功');
it_check(
    (int)it_scalar('SELECT COUNT(*) FROM schoolList WHERE source_id IN (?,?)', array('itest-seq-a', 'itest-seq-b')) === 2,
    '不同快照并发都写入且不互相覆盖'
);

// 再次顺序 apply 原并发快照 → 零新增零更新
$again = it_report('concurrent-again', it_import($concurrentSnapshot, true));
it_check(
    $again['code'] === 0
    && $again['json']['stats']['class_insert'] === 0
    && $again['json']['stats']['class_update'] === 0,
    '同一快照再次 apply 新增更新均为 0'
);

// ===========================================================================
// 场景 7（可选）：真实完整快照两次 apply 幂等
// ===========================================================================
$fullSnapshot = getenv('ACADEMIC_ITEST_SNAPSHOT');
if ($fullSnapshot && is_readable($fullSnapshot)) {
    it_reset('MyISAM', 'InnoDB');
    it_migrate();
    $raw = file_get_contents($fullSnapshot);
    $decoded = json_decode($raw, true);
    $expected = is_array($decoded) && isset($decoded['total']) ? (int)$decoded['total'] : -1;
    $first = it_report('full-1', it_import($decoded, true));
    it_check($first['code'] === 0 && $first['json']['stats']['source_classes'] === $expected, '完整快照首次 apply 成功且条数匹配');
    it_check(
        (int)it_scalar('SELECT COUNT(*) FROM schoolList WHERE source_id IS NOT NULL') === $expected,
        '完整快照所有班级都有 source_id'
    );
    $second = it_report('full-2', it_import($decoded, true));
    it_check(
        $second['code'] === 0
        && $second['json']['stats']['class_insert'] === 0
        && $second['json']['stats']['class_update'] === 0,
        '完整快照第二次 apply 新增更新均为 0'
    );
} else {
    echo "SKIP: 未提供 ACADEMIC_ITEST_SNAPSHOT，跳过完整快照场景\n";
}

// ===========================================================================
// AC3：users 内容始终不变（跨所有已跑场景）
// ===========================================================================
$users = it_query('SELECT `user_id`,`school`,`xueYuan` FROM `users` ORDER BY `user_id`');
it_check(count($users) === 1 && $users[0]['school'] === '电气工程0401' && $users[0]['xueYuan'] === '电气与信息工程学院', 'users 内容始终不变');

echo "\n共 {$GLOBALS['IT_CHECKS']} 项检查，失败 {$GLOBALS['IT_FAIL']} 项\n";
exit($GLOBALS['IT_FAIL'] === 0 ? 0 : 1);
