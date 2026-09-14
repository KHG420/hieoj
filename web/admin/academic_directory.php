<?php
/**
 * 管理员手动同步学院班级（采集教务源 -> 预览 -> 确认同步）。
 *
 * 安全约束：
 *   - 仅 administrator；未登录 / 普通用户 / contest_creator 一律 403；
 *   - 所有动作走 POST + 现有 postkey + hash_equals，CSRF 失败 403 且无网络 / DB 写；
 *   - GET 只渲染页面；
 *   - 账号 / 密码 / 验证码 / encoded / 远端响应绝不写入 session、文件、URL、日志或 hidden；
 *   - 预览零写库；确认只使用服务端 session 里那份已校验快照。
 */
require_once dirname(__FILE__) . '/../include/db_info.inc.php';
require_once dirname(__FILE__) . '/../include/academic_directory.php';
require_once dirname(__FILE__) . '/../include/academic_directory_sync.php';
require_once dirname(__FILE__) . '/../include/academic_directory_source.php';

// ---------- 1. 权限：必须在任何输出 / 远端调用之前 ----------
if (!academic_directory_source_is_admin($_SESSION, $OJ_NAME)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>403 禁止访问</title></head>'
        . '<body><p>403 Forbidden：仅管理员可以同步学院班级目录。</p></body></html>';
    exit;
}

// ---------- 2. 状态不缓存 ----------
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$config = academic_directory_source_config();
$now = time();

$notice = '';
$error = '';
$warning = '';
$state = academic_directory_source_state_get($_SESSION, $OJ_NAME);
$previewNonce = (is_array($state) && !empty($state['preview_nonce'])) ? (string)$state['preview_nonce'] : '';
$challengeNonce = (is_array($state) && !empty($state['challenge_nonce'])) ? (string)$state['challenge_nonce'] : '';
$captchaUri = (is_array($state) && !empty($state['captcha_uri']) && is_string($state['captcha_uri'])) ? $state['captcha_uri'] : '';
$preview = null;
$planView = null;
$previewReady = false;
$syncResult = null;

/** 清空本次临时状态（页面局部变量与 session 同步）。 */
function ad_sync_reset_state(&$state, &$previewNonce, &$challengeNonce, &$captchaUri, $ojName)
{
    academic_directory_source_state_clear($_SESSION, $ojName);
    $state = null;
    $previewNonce = '';
    $challengeNonce = '';
    $captchaUri = '';
}

/**
 * 取得可用 PDO 句柄。
 *
 * db_info.inc.php 只在首次查询时惰性初始化 $dbh（见 include/pdo.php），
 * 因此必须先触发一次 pdo_query 再判断 $dbh 是否可用。
 */
function ad_sync_db()
{
    global $dbh;
    pdo_query('SELECT 1');
    if (!($dbh instanceof PDO)) {
        throw new RuntimeException('数据库连接不可用');
    }
    return $dbh;
}

$action = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) ? (string)$_POST['action'] : '';
if ($action !== '') {
    // CSRF 第一优先：失败 403，且不触发任何网络 / 数据库写。
    if (!academic_directory_source_csrf_check(
        $_SESSION,
        $OJ_NAME,
        isset($_POST['postkey']) ? $_POST['postkey'] : null
    )) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '403 Forbidden：会话校验失败，请刷新页面后重试。';
        exit;
    }

    if ($action === 'cancel') {
        ad_sync_reset_state($state, $previewNonce, $challengeNonce, $captchaUri, $OJ_NAME);
        $notice = '已取消本次同步并清理临时验证码会话。';
    } elseif ($action === 'captcha') {
        ad_sync_reset_state($state, $previewNonce, $challengeNonce, $captchaUri, $OJ_NAME);
        try {
            $http = new AcademicDirectoryHttp($config);
            $challenge = academic_directory_source_challenge($http, $config);
            $http->close();
            $state = academic_directory_source_new_challenge_state(
                $challenge['cookies'],
                $challenge['body'],
                $challenge['content_type'],
                $now
            );
            academic_directory_source_state_set($_SESSION, $OJ_NAME, $state);
            $challengeNonce = (string)$state['challenge_nonce'];
            $captchaUri = (string)$state['captcha_uri'];
            $notice = '验证码已获取，请填写临时教务账号、密码和验证码。';
        } catch (Throwable $e) {
            ad_sync_reset_state($state, $previewNonce, $challengeNonce, $captchaUri, $OJ_NAME);
            $error = '获取验证码失败：' . academic_directory_source_safe_message($e) . '请稍后重试。';
        }
    } elseif ($action === 'preview') {
        $nonce = isset($_POST['challenge_nonce']) ? (string)$_POST['challenge_nonce'] : '';
        $account = isset($_POST['account']) ? (string)$_POST['account'] : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $captcha = isset($_POST['captcha']) ? (string)$_POST['captcha'] : '';

        if (!academic_directory_source_challenge_check($state, $nonce, $now)) {
            ad_sync_reset_state($state, $previewNonce, $challengeNonce, $captchaUri, $OJ_NAME);
            $error = '验证码会话已失效，请重新获取验证码。';
        } elseif ($account === '' || $password === '' || $captcha === '') {
            $error = '请填写临时教务账号、密码和验证码。';
        } else {
            $snapshot = null;
            try {
                $http = new AcademicDirectoryHttp($config);
                $http->setCookies($state['cookies']);
                $snapshot = academic_directory_source_preview($http, $config, $account, $password, $captcha);
                $http->close();
            } catch (Throwable $e) {
                ad_sync_reset_state($state, $previewNonce, $challengeNonce, $captchaUri, $OJ_NAME);
                $error = '登录或采集失败：' . academic_directory_source_safe_message($e) . '请重新获取验证码后重试。';
            }
            // 密码 / encoded / 验证码只在本请求内存里存在，随后丢弃；绝不写入 session。
            unset($account, $password, $captcha);
            if (is_array($snapshot)) {
                // 采集成功后丢弃全部远端 cookie，只保留已校验快照 + preview nonce。
                $state = academic_directory_source_new_preview_state($snapshot, $now);
                academic_directory_source_state_set($_SESSION, $OJ_NAME, $state);
                $previewNonce = (string)$state['preview_nonce'];
                $challengeNonce = '';
                $captchaUri = '';
                $notice = '采集完成，请核对下方预览后点击“确认同步”。';
            }
        }
    } elseif ($action === 'apply') {
        $nonce = isset($_POST['preview_nonce']) ? (string)$_POST['preview_nonce'] : '';
        // 只有服务端记录“预览已成功生成计划”的 nonce 才能确认；伪造 POST 一律拒绝。
        if (!academic_directory_source_preview_check($state, $nonce, $now) || empty($state['preview_ok'])) {
            ad_sync_reset_state($state, $previewNonce, $challengeNonce, $captchaUri, $OJ_NAME);
            $error = '预览已失效，请重新获取验证码、采集并预览后再确认同步。';
        } else {
            $snapshot = $state['snapshot'];
            try {
                $dbh = ad_sync_db();
                $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // 共享执行器：锁 -> migration 完整性 -> 锁内重算计划 -> 事务写入
                $syncResult = academic_directory_sync_execute($dbh, $snapshot, true);
                $notice = '同步完成：' . (int)$syncResult['stats']['class_insert'] . ' 个新增、'
                    . (int)$syncResult['stats']['class_update'] . ' 个更新、'
                    . (int)$syncResult['stats']['class_keep'] . ' 个保留。本次密码未保存。';
            } catch (Throwable $e) {
                $error = '同步失败（数据库未更改）：' . academic_directory_source_safe_message($e) . '请重新采集并预览后再试。';
            } finally {
                ad_sync_reset_state($state, $previewNonce, $challengeNonce, $captchaUri, $OJ_NAME);
            }
        }
    } else {
        $error = '未知操作。';
    }
}

// ---------- 3. 预览只读计划（绝不写库） ----------
if ($previewNonce !== '' && is_array($state) && isset($state['snapshot'])) {
    $snapshot = $state['snapshot'];
    try {
        $dbh = ad_sync_db();
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $migration = academic_directory_migration_gaps($dbh);
        if (count($migration['missing_columns']) > 0) {
            ad_sync_reset_state($state, $previewNonce, $challengeNonce, $captchaUri, $OJ_NAME);
            $warning = '数据库尚未完成必要升级（缺少 ' . implode('、', $migration['missing_columns'])
                . '）。请联系系统管理员处理后再试，本次不会写入任何数据。';
        } else {
            $colleges = $dbh->query('SELECT `id`,`name`,`source_id` FROM `collegiate`')->fetchAll(PDO::FETCH_ASSOC);
            $classes = $dbh->query('SELECT `school_id`,`num`,`value`,`collegiate_id`,`source_id` FROM `schoolList`')->fetchAll(PDO::FETCH_ASSOC);
            // 预览与确认使用同一范围：先完整校验，再收窄到 2024 级及以后（含 2024）。
            // 这样无论 session 里那份快照如何产生，预览统计都与确认同步一致。
            $scopedSnapshot = academic_directory_sync_scope_snapshot($snapshot);
            $plan = academic_directory_plan(academic_directory_snapshot_validate($scopedSnapshot), $colleges, $classes);
            $preview = array(
                'stats' => $plan['stats'],
                'conflicts' => array_slice($plan['conflicts'], 0, 10),
                'conflict_total' => count($plan['conflicts']),
            );
            $planView = academic_directory_source_plan_view($scopedSnapshot, $plan);
            $previewReady = true;
            // 记录“这份 nonce 的预览确实成功生成过计划”，供确认同步校验；确认后即被消费。
            if (empty($state['preview_ok'])) {
                $state['preview_ok'] = true;
                academic_directory_source_state_set($_SESSION, $OJ_NAME, $state);
            }
        }
    } catch (Throwable $e) {
        ad_sync_reset_state($state, $previewNonce, $challengeNonce, $captchaUri, $OJ_NAME);
        $error = '无法生成变更预览：' . academic_directory_source_safe_message($e) . '请重新获取验证码并采集后再试。';
    }
}

require_once dirname(__FILE__) . '/admin-header.php';
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>同步学院班级</title>
<style>
.ad-sync{padding:18px;max-width:1100px;margin:auto}.ad-sync h1{font-size:25px}.ad-card{background:#fff;border:1px solid #dfe4ea;border-radius:10px;padding:16px;margin-bottom:16px}.ad-card h2{font-size:17px;margin:0 0 12px}.ad-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.ad-grid .wide{grid-column:1/-1}.ad-grid label{display:block;font-size:13px;color:#40516a}.ad-grid input{width:100%;height:38px;border:1px solid #cfd6df;border-radius:5px;padding:8px 10px;box-sizing:border-box}.ad-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.ad-btn{border:0;border-radius:5px;padding:9px 14px;background:#2864d7;color:#fff;cursor:pointer}.ad-btn.secondary{background:#eef2f7;color:#28405f;border:1px solid #cfd6df}.ad-btn.danger{background:#b93e48}.ad-btn[disabled]{opacity:.6;cursor:default}.ad-btn:hover{filter:brightness(.94)}.ad-btn:focus{outline:2px solid #123a7a;outline-offset:2px}.ad-grid input:focus{border-color:#2864d7;outline:2px solid #9dbcf0;outline-offset:1px}.ad-thumb{max-width:200px;border:1px solid #cfd6df;border-radius:6px;background:#fff;padding:4px}.ad-table{width:100%;border-collapse:collapse;font-size:13px}.ad-table th,.ad-table td{border-bottom:1px solid #edf0f3;padding:7px 8px;text-align:left}.ad-badge{display:inline-block;background:#edf3fc;color:#31558d;border-radius:20px;padding:3px 8px;margin:2px}.ad-success{background:#eaf8f1;color:#126342;padding:10px;border-radius:7px}.ad-warning{background:#fff6e7;color:#8a5000;padding:10px;border-radius:7px}.ad-error{background:#fff0f1;color:#9c2934;padding:10px;border-radius:7px}.ad-muted{color:#5b6b80;font-size:13px}.ad-stats{display:flex;gap:18px;flex-wrap:wrap;margin:4px 0 10px}.ad-stats b{font-size:20px;color:#1f3f74;display:block}@media(max-width:700px){.ad-grid{grid-template-columns:1fr}}
</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
<?php include dirname(__FILE__) . '/navbar.php'; ?>
<div class="content-wrapper">
<main class="ad-sync">
<h1>同步学院班级</h1>
<p class="ad-muted">
临时填写教务账号、密码和验证码。采集后先预览，确认后更新；密码不会保存。
仅同步 2024 级及以后（含 2024）的班级及其所属学院，库内历史记录与用户保留、不会删除。
</p>

<?php if ($notice !== '') { ?><p class="ad-success" role="status"><?php echo htmlentities($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php } ?>
<?php if ($warning !== '') { ?><p class="ad-warning" role="alert"><?php echo htmlentities($warning, ENT_QUOTES, 'UTF-8'); ?></p><?php } ?>
<?php if ($error !== '') { ?><p class="ad-error" role="alert"><?php echo htmlentities($error, ENT_QUOTES, 'UTF-8'); ?></p><?php } ?>

<?php if ($syncResult !== null) { ?>
<section class="ad-card" role="status">
<h2>同步结果</h2>
<?php $rs = $syncResult['stats']; ?>
<div class="ad-stats">
<div><b><?php echo (int)$rs['source_colleges']; ?></b>源学院</div>
<div><b><?php echo (int)$rs['source_classes']; ?></b>源班级</div>
<div><b><?php echo (int)$rs['college_insert']; ?></b>学院新增</div>
<div><b><?php echo (int)$rs['college_update']; ?></b>学院更新</div>
<div><b><?php echo (int)$rs['class_insert']; ?></b>班级新增</div>
<div><b><?php echo (int)$rs['class_update']; ?></b>班级更新</div>
<div><b><?php echo (int)$rs['class_keep']; ?></b>班级保留</div>
<div><b><?php echo (int)$syncResult['conflict_total']; ?></b>冲突</div>
</div>
<?php if ((int)$rs['class_insert'] === 0 && (int)$rs['class_update'] === 0
    && (int)$rs['college_insert'] === 0 && (int)$rs['college_update'] === 0) { ?>
<p class="ad-muted">源数据与本地目录一致，本次无需更新。</p>
<?php } ?>
<p class="ad-muted">本次密码未保存；如需再次同步，请重新获取验证码。</p>
</section>
<?php } ?>

<?php if ($previewReady) { ?>
<section class="ad-card">
<h2>变更预览（尚未写入）</h2>
<p class="ad-muted">仅同步 2024 级及以后（含 2024）的班级及其所属学院；库内历史记录保留。</p>
<?php if (is_array($preview)) { $ps = $preview['stats']; ?>
<div class="ad-stats">
<div><b><?php echo (int)$ps['source_colleges']; ?></b>源学院</div>
<div><b><?php echo (int)$ps['source_classes']; ?></b>源班级</div>
<div><b><?php echo (int)$ps['college_insert']; ?></b>学院新增</div>
<div><b><?php echo (int)$ps['college_update']; ?></b>学院更新</div>
<div><b><?php echo (int)$ps['class_insert']; ?></b>班级新增</div>
<div><b><?php echo (int)$ps['class_update']; ?></b>班级更新</div>
<div><b><?php echo (int)$ps['class_keep']; ?></b>班级保留</div>
<div><b><?php echo (int)$preview['conflict_total']; ?></b>冲突</div>
</div>
<?php if ($preview['conflict_total'] > 0) { ?>
<p class="ad-warning">检测到 <?php echo (int)$preview['conflict_total']; ?> 条无法唯一匹配旧记录的冲突；冲突不会删除旧数据，无法合并的源班级会新增为独立记录。前 10 条样例：</p>
<table class="ad-table"><thead><tr><th>类型</th><th>班级编号</th><th>班级名称</th><th>候选数</th></tr></thead><tbody>
<?php foreach ($preview['conflicts'] as $conflict) { ?>
<tr><td><?php echo htmlentities((string)$conflict['type'], ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlentities((string)$conflict['bh'], ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlentities((string)$conflict['bj'], ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo isset($conflict['candidates']) ? (int)$conflict['candidates'] : ''; ?></td></tr>
<?php } ?>
</tbody></table>
<?php } ?>
<?php if (is_array($planView) && count($planView['affected_colleges']) > 0) { ?>
<h2 style="margin-top:14px">受影响学院</h2>
<p>
<?php foreach ($planView['affected_colleges'] as $college) {
    echo '<span class="ad-badge">' . htmlentities($college['name'], ENT_QUOTES, 'UTF-8')
        . '（新增 ' . (int)$college['insert'] . ' / 更新 ' . (int)$college['update'] . '）</span>';
} ?>
</p>
<?php } ?>
<?php if (is_array($planView) && $planView['total_class_changes'] > 0) { ?>
<h2 style="margin-top:14px">变更样例（共 <?php echo (int)$planView['total_class_changes']; ?> 条班级变更）</h2>
<table class="ad-table"><thead><tr><th>类型</th><th>班级编号</th><th>班级名称</th><th>学院</th></tr></thead><tbody>
<?php foreach ($planView['class_changes'] as $change) { ?>
<tr><td><?php echo htmlentities($change['type'], ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlentities($change['bh'], ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlentities($change['bj'], ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo htmlentities($change['college'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
<?php } ?>
</tbody></table>
<?php } elseif (is_array($planView) && empty($planView['has_changes'])) { ?>
<p class="ad-muted">源数据与本地目录一致，本次无需更新。</p>
<?php } ?>
<?php } ?>
<form method="post" class="ad-actions" data-busy="同步中…">
<?php require dirname(__FILE__) . '/../include/set_post_key.php'; ?>
<input type="hidden" name="action" value="apply">
<input type="hidden" name="preview_nonce" value="<?php echo htmlentities($previewNonce, ENT_QUOTES, 'UTF-8'); ?>">
<button class="ad-btn" type="submit">确认同步</button>
</form>
<form method="post" class="ad-actions" data-busy="取消中…">
<?php require dirname(__FILE__) . '/../include/set_post_key.php'; ?>
<input type="hidden" name="action" value="cancel">
<button class="ad-btn secondary" type="submit">取消并清理</button>
</form>
</section>
<?php } ?>

<section class="ad-card">
<h2>第一步：获取验证码</h2>
<p class="ad-muted">验证码有效期10分钟，刷新后请使用新验证码。</p>
<form method="post" class="ad-actions" data-busy="获取中…">
<?php require dirname(__FILE__) . '/../include/set_post_key.php'; ?>
<input type="hidden" name="action" value="captcha">
<button class="ad-btn secondary" type="submit"><?php echo $challengeNonce !== '' ? '重新获取验证码' : '获取验证码'; ?></button>
</form>

<?php if ($challengeNonce !== '') { ?>
<h2 style="margin-top:16px">第二步：临时登录并采集</h2>
<?php if ($captchaUri !== '') { ?>
<p><img class="ad-thumb" alt="教务验证码" src="<?php echo htmlentities($captchaUri, ENT_QUOTES, 'UTF-8'); ?>"></p>
<?php } ?>
<form method="post" class="ad-grid" data-busy="采集中…" autocomplete="off">
<?php require dirname(__FILE__) . '/../include/set_post_key.php'; ?>
<input type="hidden" name="action" value="preview">
<input type="hidden" name="challenge_nonce" value="<?php echo htmlentities($challengeNonce, ENT_QUOTES, 'UTF-8'); ?>">
<label>临时教务账号
<input type="text" name="account" autocomplete="off" required>
</label>
<label>临时教务密码（不保存）
<input type="password" name="password" autocomplete="new-password" value="" required>
</label>
<label>验证码
<input type="text" name="captcha" autocomplete="off" required>
</label>
<label class="wide">登录成功后只会读取学院 / 班级目录，不会修改教务数据。
</label>
<button class="ad-btn wide" type="submit">采集并预览</button>
</form>
<?php } ?>
</section>

<script>
(function () {
  var forms = document.querySelectorAll('form[data-busy]');
  for (var i = 0; i < forms.length; i++) {
    forms[i].addEventListener('submit', function () {
      var button = this.querySelector('button[type=submit]');
      if (button) {
        button.disabled = true;
        button.textContent = this.getAttribute('data-busy') || '处理中…';
      }
    });
  }
})();
</script>
</main>
</div>
</div>
</body>
</html>
