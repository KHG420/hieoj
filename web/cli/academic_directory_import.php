<?php
/**
 * 学院班级目录同步导入器（PHP CLI）。
 *
 * 用法（在 web 容器内执行）：
 *   php cli/academic_directory_import.php < snapshot.json            # 默认 dry-run
 *   php cli/academic_directory_import.php --apply < snapshot.json    # 实际写入
 *   php cli/academic_directory_import.php --file=/path/snapshot.json --apply
 *
 * 快照格式与 live-directory.json 一致。校验失败或数据库异常时零写入并返回非零。
 * 仅写 collegiate 与 schoolList，绝不修改 users。
 *
 * 锁 / migration 完整性 / 规划 / 事务写入的语义全部在共享执行器
 * academic_directory_sync_execute() 中，与后台“确认同步”完全一致，避免两份实现漂移。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/include/db_info.inc.php';
require_once dirname(__DIR__) . '/include/academic_directory.php';
require_once dirname(__DIR__) . '/include/academic_directory_sync.php';

$apply = in_array('--apply', $argv, true);
$file = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--file=') === 0) {
        $file = substr($arg, 7);
    }
}

function academic_directory_import_out($payload, $exitCode)
{
    fwrite($exitCode === 0 ? STDOUT : STDERR, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    exit($exitCode);
}

if (in_array('--help', $argv, true)) {
    fwrite(STDOUT, "用法: php cli/academic_directory_import.php [--apply] [--file=PATH]\n不传 --file 时从 stdin 读取 JSON；默认 dry-run。\n");
    exit(0);
}

$mode = $apply ? 'apply' : 'dry-run';

// ---------- 1. 读取并校验快照（先于任何数据库写） ----------
try {
    if ($file !== null) {
        if (!is_readable($file)) {
            throw new RuntimeException("快照文件不可读：$file");
        }
        $raw = file_get_contents($file);
    } else {
        $raw = stream_get_contents(STDIN);
    }
    if ($raw === false || trim($raw) === '') {
        throw new RuntimeException('快照为空');
    }
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    // 先校验（不依赖数据库），错误在连接数据库前就返回；共享执行器会再次校验。
    academic_directory_snapshot_validate($decoded);
} catch (Throwable $e) {
    academic_directory_import_out(array('ok' => false, 'mode' => $mode, 'error' => $e->getMessage()), 1);
}

// ---------- 2. 连接数据库 ----------
global $dbh;
try {
    if (!$dbh) {
        pdo_query('SELECT 1');
    }
} catch (Throwable $e) {
    academic_directory_import_out(array('ok' => false, 'mode' => $mode, 'error' => '数据库连接失败'), 1);
}
if (!$dbh) {
    academic_directory_import_out(array('ok' => false, 'mode' => $mode, 'error' => '数据库连接失败'), 1);
}
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---------- 3. 共享执行器：锁 / migration / 规划 / 事务 ----------
try {
    $report = academic_directory_sync_execute($dbh, $decoded, $apply);
} catch (Throwable $e) {
    academic_directory_import_out(array('ok' => false, 'mode' => $mode, 'error' => $e->getMessage()), 1);
}
academic_directory_import_out($report, 0);
