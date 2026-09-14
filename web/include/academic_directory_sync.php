<?php
/**
 * 学院班级目录同步的纯逻辑：快照校验 + 变更计划。
 *
 * 本文件不做任何数据库写入，也不连接数据库，方便在无数据库的测试环境里直接
 * 构造数据验证。真正的写入由 web/cli/academic_directory_import.php 负责。
 * 末尾的 academic_directory_migration_gaps() 只读取传入 PDO 的 information_schema，
 * 用于确认 migration 是否完整，不建立连接也不写库。
 *
 * 快照格式与 live-directory.json 一致：
 *   {
 *     "colleges": [ {"source_id": "...", "code": "80", "name": "..."}, ... ],
 *     "total": 4141,
 *     "classes":  [ {"field0": "<稳定源ID>", "bh": "<显示编号>", "bj": "<班级名>",
 *                    "field6": "<学院 source_id>", "xx0301$dwmc": "<学院名>", ...}, ... ]
 *   }
 */

/** 空字符串与 null 统一成 null，其余转字符串。 */
function academic_directory_sync_norm($value)
{
    if ($value === null) {
        return null;
    }
    $value = (string)$value;
    return $value === '' ? null : $value;
}

/**
 * 校验完整快照，返回规范化后的结构；任何问题抛 InvalidArgumentException。
 */
function academic_directory_snapshot_validate($snapshot)
{
    if (!is_array($snapshot)) {
        throw new InvalidArgumentException('快照不是 JSON 对象');
    }
    foreach (array('colleges', 'classes') as $key) {
        if (!isset($snapshot[$key]) || !is_array($snapshot[$key])) {
            throw new InvalidArgumentException("快照缺少数组字段 $key");
        }
    }
    if (count($snapshot['colleges']) === 0) {
        throw new InvalidArgumentException('快照学院列表为空');
    }
    if (count($snapshot['classes']) === 0) {
        throw new InvalidArgumentException('快照班级列表为空');
    }

    $colleges = array();
    $bySource = array();
    $byCode = array();
    foreach ($snapshot['colleges'] as $index => $raw) {
        if (!is_array($raw)) {
            throw new InvalidArgumentException("第 $index 个学院不是对象");
        }
        $sourceId = academic_directory_sync_norm($raw['source_id'] ?? null);
        $name = academic_directory_sync_norm($raw['name'] ?? null);
        $codeRaw = academic_directory_sync_norm($raw['code'] ?? null);
        if ($sourceId === null || strlen($sourceId) > 64) {
            throw new InvalidArgumentException("第 $index 个学院 source_id 缺失或超长（>64）");
        }
        if ($name === null || mb_strlen($name, 'UTF-8') > 100) {
            throw new InvalidArgumentException("学院 $sourceId 名称缺失或超长（>100）");
        }
        if ($codeRaw === null || !preg_match('/^\d{1,3}$/', $codeRaw)) {
            throw new InvalidArgumentException("学院 $sourceId 的 code 不是 1-3 位数字");
        }
        $code = (int)$codeRaw;
        if ($code <= 0) {
            throw new InvalidArgumentException("学院 $sourceId 的 code 非法：$codeRaw");
        }
        if (isset($bySource[$sourceId])) {
            throw new InvalidArgumentException("快照存在重复学院 source_id：$sourceId");
        }
        if (isset($byCode[$code])) {
            throw new InvalidArgumentException("快照存在重复学院 code：$codeRaw");
        }
        $bySource[$sourceId] = $name;
        $byCode[$code] = $sourceId;
        $colleges[] = array('source_id' => $sourceId, 'code' => $code, 'code_raw' => $codeRaw, 'name' => $name);
    }

    $classes = array();
    $seenSource = array();
    $seenBh = array();
    foreach ($snapshot['classes'] as $index => $raw) {
        if (!is_array($raw)) {
            throw new InvalidArgumentException("第 $index 个班级不是对象");
        }
        $sourceId = academic_directory_sync_norm($raw['field0'] ?? null);
        $bh = academic_directory_sync_norm($raw['bh'] ?? null);
        $bj = academic_directory_sync_norm($raw['bj'] ?? null);
        $collegeSource = academic_directory_sync_norm($raw['field6'] ?? null);
        $collegeName = academic_directory_sync_norm($raw['xx0301$dwmc'] ?? null);
        if ($sourceId === null || strlen($sourceId) > 64) {
            throw new InvalidArgumentException("第 $index 个班级稳定源ID缺失或超长（>64）");
        }
        if ($bh === null || strlen($bh) > 64) {
            throw new InvalidArgumentException("班级 $sourceId 显示编号缺失或超长（>64）");
        }
        if ($bj === null || mb_strlen($bj, 'UTF-8') > 50) {
            throw new InvalidArgumentException("班级 $sourceId 名称缺失或超长（>50）");
        }
        if ($collegeSource === null || !isset($bySource[$collegeSource])) {
            throw new InvalidArgumentException("班级 $sourceId 引用了未知学院 source_id：" . ($collegeSource === null ? '(空)' : $collegeSource));
        }
        if ($collegeName !== null && $collegeName !== $bySource[$collegeSource]) {
            throw new InvalidArgumentException("班级 $sourceId 的所属学院名称与学院表不一致");
        }
        if (isset($seenSource[$sourceId])) {
            throw new InvalidArgumentException("快照存在重复班级稳定源ID：$sourceId");
        }
        if (isset($seenBh[$bh])) {
            throw new InvalidArgumentException("快照存在重复班级显示编号：$bh");
        }
        $seenSource[$sourceId] = true;
        $seenBh[$bh] = true;
        $classes[] = array(
            'source_id' => $sourceId,
            'bh' => $bh,
            'bj' => $bj,
            'college_source_id' => $collegeSource,
            'college_name' => $collegeName,
        );
    }

    if (!array_key_exists('total', $snapshot)) {
        throw new InvalidArgumentException('快照缺少 total');
    }
    $rawTotal = $snapshot['total'];
    if (!is_int($rawTotal) || $rawTotal <= 0) {
        throw new InvalidArgumentException('快照 total 必须是正整数');
    }
    if ($rawTotal !== count($classes)) {
        throw new InvalidArgumentException('快照 total 与班级条数不一致');
    }

    return array(
        'total' => count($classes),
        'colleges' => $colleges,
        'classes' => $classes,
    );
}

/**
 * 生成变更计划。
 *
 * @param array $snapshot academic_directory_snapshot_validate() 的返回值
 * @param array $colleges 现有 collegiate 行：id, name, source_id
 * @param array $classes  现有 schoolList 行：school_id, num, value, collegiate_id, source_id
 * @return array 计划（含统计与冲突），不含删除操作
 */
function academic_directory_plan($snapshot, array $colleges, array $classes)
{
    if (!is_array($snapshot) || !isset($snapshot['colleges'], $snapshot['classes'])) {
        throw new InvalidArgumentException('计划输入不是规范化快照');
    }

    // ---------- 现有学院索引 ----------
    $byId = array();
    $bySource = array();
    foreach ($colleges as $row) {
        if (!array_key_exists('id', $row) || $row['id'] === null || $row['id'] === '') {
            throw new RuntimeException('collegiate 存在 NULL id，请先人工修复后再迁移');
        }
        $id = (int)$row['id'];
        if (isset($byId[$id])) {
            throw new RuntimeException("collegiate 存在重复 id：{$id}，请先人工修复后再迁移");
        }
        $byId[$id] = array(
            'id' => $id,
            'name' => (string)($row['name'] ?? ''),
            'source_id' => academic_directory_sync_norm($row['source_id'] ?? null),
        );
    }
    foreach ($byId as $id => $row) {
        if ($row['source_id'] !== null) {
            if (isset($bySource[$row['source_id']])) {
                throw new RuntimeException('collegiate 存在重复 source_id，请先人工修复后再迁移');
            }
            $bySource[$row['source_id']] = $row;
        }
    }
    if (count($byId) === 0) {
        throw new RuntimeException('collegiate 表为空，请先执行迁移脚本');
    }

    // ---------- 学院计划 ----------
    $collegeInserts = array();
    $collegeUpdates = array();
    $collegeKeeps = 0;
    $localBySource = array();
    $usedLocalIds = array();

    foreach ($snapshot['colleges'] as $sc) {
        $sourceId = $sc['source_id'];
        $code = (int)$sc['code'];
        $name = $sc['name'];

        $target = null;
        if (isset($bySource[$sourceId])) {
            $target = $bySource[$sourceId];
            // 本地 id 必须等于源 code；已同步 source_id 的本地 id 不可被隐式改写。
            if ((int)$target['id'] !== $code) {
                throw new RuntimeException(
                    "学院 source_id {$sourceId} 已映射到本地 id {$target['id']}，与快照 code {$sc['code_raw']} 不一致，拒绝整次写"
                );
            }
        } elseif (isset($byId[$code])) {
            $candidate = $byId[$code];
            if ($candidate['source_id'] !== null && $candidate['source_id'] !== $sourceId) {
                throw new RuntimeException(
                    "学院 code {$sc['code_raw']} 已映射到 source_id {$candidate['source_id']}，与快照 {$sourceId} 冲突"
                );
            }
            $target = $candidate;
        }

        if ($target !== null) {
            $localId = (int)$target['id'];
            if (isset($usedLocalIds[$localId])) {
                throw new RuntimeException("多个源学院映射到同一本地学院 id：$localId");
            }
            $usedLocalIds[$localId] = true;
            $localBySource[$sourceId] = $localId;
            if ($target['name'] !== $name || $target['source_id'] !== $sourceId) {
                $collegeUpdates[] = array(
                    'id' => $localId,
                    'name' => $name,
                    'source_id' => $sourceId,
                    'old_name' => $target['name'],
                );
                $byId[$localId]['name'] = $name;
                $byId[$localId]['source_id'] = $sourceId;
                $bySource[$sourceId] = $byId[$localId];
            } else {
                $collegeKeeps++;
            }
        } else {
            if (isset($usedLocalIds[$code])) {
                throw new RuntimeException("多个源学院映射到同一本地学院 id：$code");
            }
            $usedLocalIds[$code] = true;
            $localBySource[$sourceId] = $code;
            $collegeInserts[] = array('id' => $code, 'name' => $name, 'source_id' => $sourceId);
            $byId[$code] = array('id' => $code, 'name' => $name, 'source_id' => $sourceId);
            $bySource[$sourceId] = $byId[$code];
        }
    }

    // ---------- 现有班级索引 ----------
    $classBySource = array();
    $legacyRows = array();
    foreach ($classes as $row) {
        if (!array_key_exists('school_id', $row) || $row['school_id'] === null) {
            throw new RuntimeException('schoolList 存在 NULL school_id，请先人工修复');
        }
        $sid = academic_directory_sync_norm($row['source_id'] ?? null);
        if ($sid !== null) {
            if (isset($classBySource[$sid])) {
                throw new RuntimeException("schoolList 存在重复 source_id：{$sid}，请先人工修复");
            }
            $classBySource[$sid] = $row;
        } else {
            $legacyRows[(int)$row['school_id']] = array(
                'school_id' => (int)$row['school_id'],
                'num' => (string)($row['num'] ?? ''),
                'value' => (string)($row['value'] ?? ''),
                'collegiate_id' => ($row['collegiate_id'] === null || $row['collegiate_id'] === '') ? null : (int)$row['collegiate_id'],
                'used' => false,
            );
        }
    }

    // ---------- 班级计划 ----------
    $classInserts = array();
    $classUpdates = array();
    $classKeeps = 0;
    $conflicts = array();

    foreach ($snapshot['classes'] as $sc) {
        $sourceId = $sc['source_id'];
        $bh = $sc['bh'];
        $bj = $sc['bj'];
        if (!isset($localBySource[$sc['college_source_id']])) {
            throw new RuntimeException("班级 $sourceId 引用了未知学院映射");
        }
        $localCollege = $localBySource[$sc['college_source_id']];

        if (isset($classBySource[$sourceId])) {
            $row = $classBySource[$sourceId];
            $same = ((string)($row['num'] ?? '') === $bh)
                && ((string)($row['value'] ?? '') === $bj)
                && ($row['collegiate_id'] !== null && (int)$row['collegiate_id'] === $localCollege);
            if ($same) {
                $classKeeps++;
            } else {
                $classUpdates[] = array(
                    'school_id' => (int)$row['school_id'],
                    'source_id' => $sourceId,
                    'num' => $bh,
                    'value' => $bj,
                    'collegiate_id' => $localCollege,
                    'adopted' => false,
                );
            }
            continue;
        }

        // 旧班级仅用唯一匹配的 “num+name” 或 “name+学院id” 建立映射
        $candidates = array();
        foreach ($legacyRows as $legacy) {
            if ($legacy['used']) {
                continue;
            }
            $numName = ($legacy['num'] === $bh && $legacy['value'] === $bj);
            $nameCollege = ($legacy['value'] === $bj && $legacy['collegiate_id'] !== null && $legacy['collegiate_id'] === $localCollege);
            if ($numName || $nameCollege) {
                $candidates[] = $legacy;
            }
        }

        if (count($candidates) === 1) {
            $legacy = $candidates[0];
            $legacyRows[$legacy['school_id']]['used'] = true;
            $classUpdates[] = array(
                'school_id' => $legacy['school_id'],
                'source_id' => $sourceId,
                'num' => $bh,
                'value' => $bj,
                'collegiate_id' => $localCollege,
                'adopted' => true,
            );
        } else {
            if (count($candidates) > 1) {
                $conflicts[] = array(
                    'type' => 'legacy_match_ambiguous',
                    'source_id' => $sourceId,
                    'bh' => $bh,
                    'bj' => $bj,
                    'candidates' => count($candidates),
                );
            }
            $classInserts[] = array(
                'source_id' => $sourceId,
                'num' => $bh,
                'value' => $bj,
                'collegiate_id' => $localCollege,
            );
        }
    }

    return array(
        'college_inserts' => $collegeInserts,
        'college_updates' => $collegeUpdates,
        'college_keeps' => $collegeKeeps,
        'class_inserts' => $classInserts,
        'class_updates' => $classUpdates,
        'class_keeps' => $classKeeps,
        'conflicts' => $conflicts,
        'stats' => array(
            'source_colleges' => count($snapshot['colleges']),
            'source_classes' => count($snapshot['classes']),
            'existing_colleges' => count($colleges),
            'existing_classes' => count($classes),
            'college_insert' => count($collegeInserts),
            'college_update' => count($collegeUpdates),
            'college_keep' => $collegeKeeps,
            'class_insert' => count($classInserts),
            'class_update' => count($classUpdates),
            'class_keep' => $classKeeps,
            'class_adopted' => count(array_filter($classUpdates, function ($op) {
                return !empty($op['adopted']);
            })),
            'class_conflict' => count($conflicts),
        ),
    );
}

/**
 * 检查 migration 是否完整（只读 information_schema，MySQL/MariaDB）。
 *
 * 返回：
 *   missing_columns: 必需列缺失（说明完全没跑 migration 或只跑了一部分）
 *   gaps:            所有未完成项（列、唯一索引、字段宽度、事务引擎）
 *
 * importer 在 apply 前必须要求 gaps 为空，否则拒绝写入并要求重跑 migration。
 * 不能因为“有 source_id 列”就假定迁移完整（可能是 DDL 中途失败的状态）。
 */
function academic_directory_migration_gaps(PDO $pdo)
{
    $missing = array();
    $gaps = array();
    $tables = array('collegiate', 'schoolList');
    $columns = array();
    $engines = array();

    foreach ($tables as $table) {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute(array($table));
        $columns[$table] = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[$table][$row['COLUMN_NAME']] = $row['CHARACTER_MAXIMUM_LENGTH'];
        }
        $stmt->closeCursor();

        $stmt = $pdo->prepare(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute(array($table));
        $engines[$table] = (string)$stmt->fetchColumn();
        $stmt->closeCursor();
    }

    foreach ($tables as $table) {
        if (!isset($columns[$table]['source_id'])) {
            $missing[] = "$table.source_id 缺失";
            $gaps[] = "$table.source_id 缺失";
        } elseif ((int)$columns[$table]['source_id'] < 64) {
            $gaps[] = "$table.source_id 宽度不足 64";
        }
    }
    if (!isset($columns['schoolList']['num'])) {
        $gaps[] = 'schoolList.num 缺失';
    } elseif ((int)$columns['schoolList']['num'] < 64) {
        $gaps[] = 'schoolList.num 宽度不足 64';
    }

    foreach ($tables as $table) {
        if (strcasecmp($engines[$table], 'InnoDB') !== 0) {
            $gaps[] = "$table 引擎不是 InnoDB（当前 " . ($engines[$table] === '' ? '未知' : $engines[$table]) . '）';
        }
    }

    foreach ($tables as $table) {
        if (!isset($columns[$table]['source_id'])) {
            continue;
        }
        if (!academic_directory_has_unique_single_column($pdo, $table, 'source_id')) {
            $gaps[] = "$table.source_id 缺少单列唯一索引";
        }
    }

    return array('missing_columns' => $missing, 'gaps' => $gaps);
}

/** source_id 上是否存在仅包含该列的唯一索引。 */
function academic_directory_has_unique_single_column(PDO $pdo, $table, $column)
{
    $stmt = $pdo->prepare(
        'SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS'
        . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0'
        . ' ORDER BY INDEX_NAME, SEQ_IN_INDEX'
    );
    $stmt->execute(array($table));
    $byIndex = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byIndex[$row['INDEX_NAME']][] = $row['COLUMN_NAME'];
    }
    $stmt->closeCursor();
    foreach ($byIndex as $cols) {
        if (count($cols) === 1 && $cols[0] === $column) {
            return true;
        }
    }
    return false;
}

/** 释放本进程持有的目录同步锁（没有锁时为空操作）。 */
function academic_directory_sync_release_lock(PDO $dbh, $driver, $locked)
{
    if ($locked && $driver === 'mysql') {
        try {
            $dbh->query("SELECT RELEASE_LOCK('hnieoj_academic_directory_sync')");
        } catch (Throwable $ignored) {
        }
    }
}

/** 读取全部行；读失败抛 RuntimeException（不暴露底层 SQL）。 */
function academic_directory_sync_fetch_all(PDO $dbh, $sql)
{
    $stmt = $dbh->query($sql);
    if ($stmt === false) {
        throw new RuntimeException('读取现有目录数据失败');
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return $rows;
}

/**
 * 目录同步的唯一执行器：CLI importer 与管理后台确认同步共用。
 *
 * 语义与原 CLI 完全一致：
 *   - apply 时先取 MySQL advisory lock，再在锁内校验 migration、读取现状并重新规划；
 *   - 未迁移 / 半迁移 / 锁失败 / 规划冲突一律零写入；
 *   - apply 写入在单个事务内完成，任何异常回滚并释放锁；
 *   - dry-run 只读、不加锁。
 *
 * @param PDO   $dbh      已连接的数据库句柄
 * @param array $snapshot 原始快照（格式同 live-directory.json，函数内部会再次校验）
 * @param bool  $apply    false = dry-run，true = 实际写入
 * @return array 成功报告（ok/mode/stats/conflict_total/conflicts[/applied]）
 * @throws RuntimeException 任何已脱敏的可读错误（migration / 锁 / 冲突 / 写失败）
 */
function academic_directory_sync_execute(PDO $dbh, array $snapshot, $apply = false)
{
    $apply = (bool)$apply;
    $mode = $apply ? 'apply' : 'dry-run';
    $driver = $dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
    $locked = false;

    $normalized = academic_directory_snapshot_validate($snapshot);

    try {
        if ($apply && $driver === 'mysql') {
            try {
                $lockStmt = $dbh->query("SELECT GET_LOCK('hnieoj_academic_directory_sync', 30)");
                $locked = ($lockStmt !== false && (int)$lockStmt->fetchColumn() === 1);
                if ($lockStmt !== false) {
                    $lockStmt->closeCursor();
                }
            } catch (Throwable $e) {
                $locked = false;
            }
            if (!$locked) {
                throw new RuntimeException('未能获得同步锁（可能有另一次同步在运行）');
            }
        }

        try {
            $migration = academic_directory_migration_gaps($dbh);
        } catch (Throwable $e) {
            throw new RuntimeException('数据库 migration 检查失败，请确认数据库可用后重试（零写入）');
        }
        if (count($migration['missing_columns']) > 0) {
            throw new RuntimeException(
                '数据库尚未执行 migration：' . implode('；', $migration['missing_columns'])
                . '，请先运行 cli/academic_directory_migrate.php --apply'
            );
        }
        if ($apply && count($migration['gaps']) > 0) {
            throw new RuntimeException(
                '数据库 migration 未完整执行：' . implode('；', $migration['gaps'])
                . '，请重新运行 cli/academic_directory_migrate.php --apply（零写入）'
            );
        }

        try {
            $colleges = academic_directory_sync_fetch_all($dbh, 'SELECT `id`,`name`,`source_id` FROM `collegiate`');
            $classes = academic_directory_sync_fetch_all($dbh, 'SELECT `school_id`,`num`,`value`,`collegiate_id`,`source_id` FROM `schoolList`');
        } catch (Throwable $e) {
            throw new RuntimeException('读取现有目录数据失败');
        }

        $plan = academic_directory_plan($normalized, $colleges, $classes);

        $report = array(
            'ok' => true,
            'mode' => $mode,
            'stats' => $plan['stats'],
            'conflict_total' => count($plan['conflicts']),
            'conflicts' => array_slice($plan['conflicts'], 0, 50),
        );

        if (!$apply) {
            $report['applied'] = false;
            return $report;
        }

        try {
            $dbh->beginTransaction();
            $insertCollege = $dbh->prepare('INSERT INTO `collegiate` (`id`,`name`,`source_id`) VALUES (?,?,?)');
            $updateCollege = $dbh->prepare('UPDATE `collegiate` SET `name` = ?, `source_id` = ? WHERE `id` = ?');
            $insertClass = $dbh->prepare('INSERT INTO `schoolList` (`num`,`value`,`join_time`,`collegiate_id`,`source_id`) VALUES (?,?,NOW(),?,?)');
            $updateClass = $dbh->prepare('UPDATE `schoolList` SET `num` = ?, `value` = ?, `collegiate_id` = ?, `source_id` = ? WHERE `school_id` = ?');

            foreach ($plan['college_inserts'] as $op) {
                $insertCollege->execute(array($op['id'], $op['name'], $op['source_id']));
            }
            foreach ($plan['college_updates'] as $op) {
                $updateCollege->execute(array($op['name'], $op['source_id'], $op['id']));
            }
            foreach ($plan['class_inserts'] as $op) {
                $insertClass->execute(array($op['num'], $op['value'], $op['collegiate_id'], $op['source_id']));
            }
            foreach ($plan['class_updates'] as $op) {
                $updateClass->execute(array($op['num'], $op['value'], $op['collegiate_id'], $op['source_id'], $op['school_id']));
            }

            $dbh->commit();
            $report['applied'] = true;
        } catch (Throwable $e) {
            if ($dbh->inTransaction()) {
                try {
                    $dbh->rollBack();
                } catch (Throwable $ignored) {
                }
            }
            throw new RuntimeException('数据库写入失败，已回滚，未更改任何数据');
        }

        return $report;
    } finally {
        academic_directory_sync_release_lock($dbh, $driver, $locked);
    }
}
