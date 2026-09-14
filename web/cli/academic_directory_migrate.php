<?php
/**
 * 学院班级目录迁移（独立 CLI，幂等，保留全部旧行）。
 *
 * 用法（在 web 容器内执行）：
 *   php cli/academic_directory_migrate.php            # 只检查并打印计划（默认 dry-run）
 *   php cli/academic_directory_migrate.php --apply    # 实际执行
 *
 * 只做结构性 DDL 与补齐 17 个现有页面学院，不删除/合并且不修改 users。
 * 不会自动对生产库执行：必须显式 --apply。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/include/db_info.inc.php';
require_once dirname(__DIR__) . '/include/academic_directory.php';
require_once dirname(__DIR__) . '/include/academic_directory_sync.php';

$apply = in_array('--apply', $argv, true);

function academic_directory_migrate_out($payload, $exitCode)
{
    fwrite($exitCode === 0 ? STDOUT : STDERR, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    exit($exitCode);
}

if (in_array('--help', $argv, true)) {
    fwrite(STDOUT, "用法: php cli/academic_directory_migrate.php [--apply]\n默认只检查并打印计划；--apply 才执行 DDL。\n");
    exit(0);
}

global $dbh;
try {
    if (!$dbh) {
        pdo_query('SELECT 1');
    }
} catch (Throwable $e) {
    academic_directory_migrate_out(array('ok' => false, 'mode' => $apply ? 'apply' : 'dry-run', 'error' => '数据库连接失败'), 1);
}
if (!$dbh) {
    academic_directory_migrate_out(array('ok' => false, 'mode' => $apply ? 'apply' : 'dry-run', 'error' => '数据库连接失败'), 1);
}
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function academic_directory_migrate_scalar(PDO $pdo, $sql, array $params = array())
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $stmt->closeCursor();
    return $row === false ? null : $row[0];
}

function academic_directory_migrate_table_exists(PDO $pdo, $table)
{
    return (int)academic_directory_migrate_scalar(
        $pdo,
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        array($table)
    ) > 0;
}

function academic_directory_migrate_column_exists(PDO $pdo, $table, $column)
{
    return (int)academic_directory_migrate_scalar(
        $pdo,
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        array($table, $column)
    ) > 0;
}

function academic_directory_migrate_index_exists(PDO $pdo, $table, $index)
{
    return (int)academic_directory_migrate_scalar(
        $pdo,
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
        array($table, $index)
    ) > 0;
}

try {
    if (!academic_directory_migrate_table_exists($dbh, 'collegiate')) {
        throw new RuntimeException('缺少 collegiate 表');
    }
    if (!academic_directory_migrate_table_exists($dbh, 'schoolList')) {
        throw new RuntimeException('缺少 schoolList 表');
    }

    // 迁移前安全检查：collegiate 重复/空 id 会破坏按 id 的映射
    $duplicateIds = academic_directory_migrate_scalar(
        $dbh,
        'SELECT COUNT(*) FROM (SELECT `id` FROM `collegiate` WHERE `id` IS NOT NULL GROUP BY `id` HAVING COUNT(*) > 1) d'
    );
    if ((int)$duplicateIds > 0) {
        throw new RuntimeException('collegiate 存在重复 id，请先人工修复（不做任何写入）');
    }
    $nullIds = academic_directory_migrate_scalar($dbh, 'SELECT COUNT(*) FROM `collegiate` WHERE `id` IS NULL');
    if ((int)$nullIds > 0) {
        throw new RuntimeException('collegiate 存在 NULL id，请先人工修复（不做任何写入）');
    }

    $actions = array();
    $collegiateHasSource = academic_directory_migrate_column_exists($dbh, 'collegiate', 'source_id');
    $schoolHasSource = academic_directory_migrate_column_exists($dbh, 'schoolList', 'source_id');

    // 事务性：两张参与写的表都必须是 InnoDB，否则 apply 阶段会明确拒绝。
    $collegiateEngine = academic_directory_migrate_scalar(
        $dbh,
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        array('collegiate')
    );
    if ($collegiateEngine !== null && strcasecmp((string)$collegiateEngine, 'InnoDB') !== 0) {
        $actions[] = 'ALTER TABLE `collegiate` ENGINE=InnoDB';
    }

    if (!$collegiateHasSource) {
        $actions[] = 'ALTER TABLE `collegiate` ADD COLUMN `source_id` VARCHAR(64) NULL DEFAULT NULL';
    }
    if (!academic_directory_migrate_index_exists($dbh, 'collegiate', 'uniq_collegiate_source_id')) {
        $actions[] = 'ALTER TABLE `collegiate` ADD UNIQUE INDEX `uniq_collegiate_source_id` (`source_id`)';
    }
    if (!academic_directory_migrate_index_exists($dbh, 'collegiate', 'idx_collegiate_id')) {
        $actions[] = 'ALTER TABLE `collegiate` ADD INDEX `idx_collegiate_id` (`id`)';
    }

    if (!$schoolHasSource) {
        $actions[] = 'ALTER TABLE `schoolList` ADD COLUMN `source_id` VARCHAR(64) NULL DEFAULT NULL';
    }
    $numLength = academic_directory_migrate_scalar(
        $dbh,
        'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        array('schoolList', 'num')
    );
    if ($numLength !== null && (int)$numLength < 64) {
        $actions[] = 'ALTER TABLE `schoolList` MODIFY COLUMN `num` VARCHAR(64) NOT NULL';
    }
    $engine = academic_directory_migrate_scalar(
        $dbh,
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        array('schoolList')
    );
    if ($engine !== null && strcasecmp((string)$engine, 'InnoDB') !== 0) {
        $actions[] = 'ALTER TABLE `schoolList` ENGINE=InnoDB';
    }
    if (!academic_directory_migrate_index_exists($dbh, 'schoolList', 'uniq_schoolList_source_id')) {
        $actions[] = 'ALTER TABLE `schoolList` ADD UNIQUE INDEX `uniq_schoolList_source_id` (`source_id`)';
    }
    if (!academic_directory_migrate_index_exists($dbh, 'schoolList', 'idx_schoolList_collegiate_id')) {
        $actions[] = 'ALTER TABLE `schoolList` ADD INDEX `idx_schoolList_collegiate_id` (`collegiate_id`)';
    }
    if (!academic_directory_migrate_index_exists($dbh, 'schoolList', 'idx_schoolList_value')) {
        $actions[] = 'ALTER TABLE `schoolList` ADD INDEX `idx_schoolList_value` (`value`)';
    }
    if (!academic_directory_migrate_index_exists($dbh, 'schoolList', 'idx_schoolList_num')) {
        $actions[] = 'ALTER TABLE `schoolList` ADD INDEX `idx_schoolList_num` (`num`)';
    }

    // 补齐页面既有 17 学院；已有同名 id 的旧行在未被源同步接管时按当前名称改名
    if ($collegiateHasSource) {
        $existing = pdo_query('SELECT `id`,`name`,`source_id` FROM `collegiate`');
    } else {
        $existing = pdo_query('SELECT `id`,`name` FROM `collegiate`');
    }
    $existingById = array();
    if (is_array($existing)) {
        foreach ($existing as $row) {
            $existingById[(int)$row['id']] = $row;
        }
    }
    $seedInserts = array();
    $seedUpdates = array();
    foreach (academic_directory_canonical_colleges() as $pair) {
        $name = $pair[0];
        $code = (int)$pair[1];
        if (isset($existingById[$code])) {
            $row = $existingById[$code];
            $rowSource = $collegiateHasSource ? academic_directory_sync_norm($row['source_id'] ?? null) : null;
            if ((string)$row['name'] === $name) {
                continue;
            }
            if ($rowSource === null) {
                $seedUpdates[] = array('id' => $code, 'name' => $name, 'old_name' => (string)$row['name']);
                $actions[] = "UPDATE `collegiate` SET `name` = " . json_encode($name, JSON_UNESCAPED_UNICODE) . " WHERE `id` = $code";
            }
            continue;
        }
        $seedInserts[] = array('id' => $code, 'name' => $name);
        $actions[] = "INSERT INTO `collegiate` (`id`,`name`) VALUES ($code, " . json_encode($name, JSON_UNESCAPED_UNICODE) . ")";
    }

    $summary = array(
        'ok' => true,
        'mode' => $apply ? 'apply' : 'dry-run',
        'checks' => array(
            'collegiate_rows' => (int)academic_directory_migrate_scalar($dbh, 'SELECT COUNT(*) FROM `collegiate`'),
            'schoolList_rows' => (int)academic_directory_migrate_scalar($dbh, 'SELECT COUNT(*) FROM `schoolList`'),
            'duplicate_collegiate_id' => 0,
            'collegiate_has_source_id' => $collegiateHasSource,
            'schoolList_has_source_id' => $schoolHasSource,
            'collegiate_engine' => (string)$collegiateEngine,
            'schoolList_engine' => (string)$engine,
            'schoolList_num_length' => $numLength === null ? null : (int)$numLength,
        ),
        'seed_college_insert' => count($seedInserts),
        'seed_college_update' => count($seedUpdates),
        'actions' => $actions,
    );

    if (!$apply) {
        $summary['applied'] = 0;
        academic_directory_migrate_out($summary, 0);
    }

    $applied = 0;
    foreach ($actions as $sql) {
        $dbh->exec($sql);
        $applied++;
    }
    $summary['applied'] = $applied;
    // apply 后重新读取引擎，报告真实结果而非迁移前状态。
    $summary['checks']['collegiate_engine'] = (string)academic_directory_migrate_scalar(
        $dbh,
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        array('collegiate')
    );
    $summary['checks']['schoolList_engine'] = (string)academic_directory_migrate_scalar(
        $dbh,
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        array('schoolList')
    );
    academic_directory_migrate_out($summary, 0);
} catch (Throwable $e) {
    academic_directory_migrate_out(array(
        'ok' => false,
        'mode' => $apply ? 'apply' : 'dry-run',
        'error' => $e->getMessage(),
    ), 1);
}
