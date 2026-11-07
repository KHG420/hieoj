<?php
require_once("../include/db_info.inc.php");
require_once("../include/my_func.inc.php");
// Process the POST and redirect before any HTML is emitted, so saving returns to
// the "修改用户信息" list page instead of re-rendering the edit form.
if (!isset($_SESSION[$OJ_NAME.'_'.'administrator'])) {
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}

function user_edit_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// pdo_query() takes variadic bound values, not an argument array.
function user_edit_query($sql, $args = array()) {
    return pdo_query($sql, ...$args);
}

$requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$errors = array();
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
$targetId = '';
$user = null;
$values = array('nick' => '', 'school' => '', 'xueYuan' => '', 'email' => '');
$balance = null;
$password = '';
$confirmPassword = '';

if ($requestMethod === 'POST') {
    require_once("../include/check_post_key.php");
    $targetId = isset($_POST['user_id']) && is_string($_POST['user_id']) ? trim($_POST['user_id']) : '';
    foreach (array('nick','school','xueYuan','email') as $field) {
        if (isset($_POST[$field]) && is_string($_POST[$field])) $values[$field] = trim($_POST[$field]);
    }
    foreach (array('passwd', 'confirm_password') as $field) {
        if (isset($_POST[$field]) && !is_string($_POST[$field])) $errors[] = '密码格式不正确，请重新输入。';
    }
    $password = isset($_POST['passwd']) && is_string($_POST['passwd']) ? $_POST['passwd'] : '';
    $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
} else {
    $targetId = isset($_GET['user_id']) && is_string($_GET['user_id']) ? trim($_GET['user_id']) : '';
}

if ($targetId !== '') {
    $rows = user_edit_query("SELECT user_id,nick,school,xueYuan,email,reg_time,accesstime,defunct,register_num,
        EXISTS (SELECT 1 FROM privilege WHERE privilege.user_id=users.user_id AND rightstr='administrator') AS password_protected
        FROM users WHERE user_id=?", array($targetId));
    if ($rows === false) {
        $errors[] = '用户资料读取失败，请稍后重试。';
    } elseif (!$rows) {
        $errors[] = '用户不存在，可能已被删除。';
    } else {
        $user = $rows[0];
        if ($requestMethod !== 'POST') {
            foreach (array_keys($values) as $field) $values[$field] = (string)$user[$field];
        }
    }
}

if ($requestMethod === 'POST' && $user) {
    if ($values['nick'] === '') {
        $errors[] = '姓名不能为空。';
    } elseif (mb_strlen($values['nick'], 'UTF-8') > 20) {
        $errors[] = '姓名最多 20 个字符。';
    }
    if (mb_strlen($values['school'], 'UTF-8') > 20) {
        $errors[] = '班级最多 20 个字符。';
    }
    if (mb_strlen($values['xueYuan'], 'UTF-8') > 50) {
        $errors[] = '学院最多 50 个字符。';
    }
    if ($values['email'] !== '') {
        if (mb_strlen($values['email'], 'UTF-8') > 100) {
            $errors[] = '邮箱最多 100 个字符。';
        } elseif (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = '邮箱格式不正确，请检查后重试。';
        }
    }
    $changePassword = $password !== '' || $confirmPassword !== '';
    if ($changePassword) {
        if ($user['password_protected']) {
            $errors[] = '管理员账号的密码请由本人在个人设置中修改。';
        } elseif ($password === '' || $confirmPassword === '') {
            $errors[] = '修改密码时，请同时填写密码和确认密码。';
        } elseif ($password !== $confirmPassword) {
            $errors[] = '两次输入的密码不一致，请重新输入。';
        } elseif (strlen($password) < 6) {
            $errors[] = '密码至少需要 6 位。';
        }
    }
    if (!$errors) {
        $sql = 'UPDATE users SET nick=?,school=?,xueYuan=?,email=?';
        $args = array($values['nick'], $values['school'], $values['xueYuan'], $values['email']);
        if ($changePassword) {
            $sql .= ',password=?';
            $args[] = pwGen($password);
        }
        $sql .= ' WHERE user_id=?';
        $args[] = $targetId;
        if ($changePassword) {
            $sql .= " AND user_id NOT IN (SELECT user_id FROM privilege WHERE rightstr='administrator')";
        }
        $updated = user_edit_query($sql, $args);
        if ($updated === false) {
            $errors[] = '用户资料保存失败，请稍后重试。';
        } elseif ($changePassword && $updated === 0) {
            $errors[] = '用户不存在或已成为管理员，本次修改未保存，请刷新后重试。';
        } else {
            // Return to the user picker/list with the success notice.
            header('Location: user_info_edit.php?saved=1', true, 303);
            exit;
        }
    }
}

if ($targetId !== '' && !$user && !$errors) {
    $errors[] = '用户不存在，可能已被删除。';
}

if ($user) {
    $wallet = user_edit_query('SELECT balance FROM coin_wallet WHERE user_id=?', array($targetId));
    // A deployment without the coin tables still renders the page; the balance
    // stays unknown instead of failing the whole form.
    if ($wallet !== false) $balance = $wallet ? intval($wallet[0]['balance']) : 0;
}

$keyword = isset($_GET['keyword']) && is_string($_GET['keyword']) ? trim($_GET['keyword']) : '';
$pickRows = array();
if (!$user && $targetId === '' && $requestMethod !== 'POST') {
    if ($keyword !== '') {
        $like = '%'.$keyword.'%';
        $pickRows = user_edit_query('SELECT user_id,nick,school,xueYuan,email FROM users WHERE user_id LIKE ? OR nick LIKE ? OR school LIKE ? ORDER BY user_id DESC LIMIT 50', array($like, $like, $like));
    } else {
        $pickRows = user_edit_query('SELECT user_id,nick,school,xueYuan,email FROM users ORDER BY reg_time DESC LIMIT 25');
    }
    if ($pickRows === false) {
        $errors[] = '用户列表读取失败，请稍后重试。';
        $pickRows = array();
    }
}
// Render only after the post-save redirect above had a chance to run.
require_once("admin-header.php");
if (isset($OJ_LANG)) require_once("../lang/$OJ_LANG.php");
?>
<title>修改用户信息</title>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include("navbar.php");?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="row">
                <div class="col-sm-1">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </div>
            </div>
        </div>
        <center><h3>修改用户信息</h3></center>
        <section class="content">
            <div class="container-fluid">
                <?php if ($saved) { ?><div class="alert alert-success" role="status">用户信息已保存。</div><?php } ?>
                <?php foreach ($errors as $message) { ?><div class="alert alert-danger" role="alert"><?php echo user_edit_escape($message); ?></div><?php } ?>

                <?php if ($user) { ?>
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title"><?php echo user_edit_escape($user['user_id']); ?> · 基础资料</h3></div>
                    <form method="post" action="user_info_edit.php">
                        <?php require_once("../include/set_post_key.php");?>
                        <input type="hidden" name="user_id" value="<?php echo user_edit_escape($user['user_id']); ?>">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>学号</label>
                                        <input type="text" class="form-control" value="<?php echo user_edit_escape($user['user_id']); ?>" readonly>
                                        <small class="form-text text-muted">学号是账号主键，不能在此修改。</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>金币余额</label>
                                        <input type="text" class="form-control" value="<?php echo $balance === null ? '—' : intval($balance); ?>" readonly>
                                        <small class="form-text text-muted">金币由账本发放与消费，不能在这里直接改数。</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>注册时间</label>
                                        <input type="text" class="form-control" value="<?php echo user_edit_escape($user['reg_time']); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>最近登录</label>
                                        <input type="text" class="form-control" value="<?php echo user_edit_escape($user['accesstime']); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>账号状态</label>
                                        <input type="text" class="form-control" value="<?php echo $user['defunct'] === 'N' ? '正常' : '已停用'; ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>注册审核</label>
                                        <input type="text" class="form-control" value="<?php echo intval($user['register_num']) === 1 ? '已通过' : '待审核'; ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="user-nick">姓名 <span class="text-danger">*</span></label>
                                        <input type="text" id="user-nick" name="nick" class="form-control" maxlength="20" required value="<?php echo user_edit_escape($values['nick']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="user-email">邮箱</label>
                                        <input type="email" id="user-email" name="email" class="form-control" maxlength="100" value="<?php echo user_edit_escape($values['email']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="user-xueyuan">学院</label>
                                        <input type="text" id="user-xueyuan" name="xueYuan" class="form-control" maxlength="50" value="<?php echo user_edit_escape($values['xueYuan']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="user-school">班级</label>
                                        <input type="text" id="user-school" name="school" class="form-control" maxlength="20" value="<?php echo user_edit_escape($values['school']); ?>">
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <h4>登录密码</h4>
                            <p class="text-muted" id="user-password-help"><?php echo $user['password_protected'] ? '管理员账号的密码请由本人在个人设置中修改。' : '两项留空则保留原密码。修改时至少填写 6 位，并再次输入相同密码。'; ?></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="user-password">密码</label>
                                        <input type="password" id="user-password" name="passwd" class="form-control" autocomplete="new-password" minlength="6" aria-describedby="user-password-help"<?php echo $user['password_protected'] ? ' disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="user-confirm-password">确认密码</label>
                                        <input type="password" id="user-confirm-password" name="confirm_password" class="form-control" autocomplete="new-password" minlength="6" aria-describedby="user-password-help"<?php echo $user['password_protected'] ? ' disabled' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">保存修改</button>
                        </div>
                    </form>
                </div>
                <?php } else { ?>
                <div class="card">
                    <div class="card-header"><h3 class="card-title">选择要修改的用户</h3></div>
                    <div class="card-body">
                        <form method="get" action="user_info_edit.php" class="form-inline">
                            <input type="text" name="keyword" class="form-control mr-2" value="<?php echo user_edit_escape($keyword); ?>" placeholder="学号 / 姓名 / 班级">
                            <button type="submit" class="btn btn-primary">搜索</button>
                            <a class="btn btn-link" href="user_info_edit.php">查看最近注册</a>
                        </form>
                        <?php if (!$pickRows) { ?>
                        <p class="text-muted mt-3">没有找到匹配的用户。</p>
                        <?php } else { ?>
                        <table class="table table-bordered table-striped mt-3">
                            <thead><tr><th>学号</th><th>姓名</th><th>学院</th><th>班级</th><th>邮箱</th><th>操作</th></tr></thead>
                            <tbody>
                            <?php foreach ($pickRows as $pickRow) { ?>
                                <tr>
                                    <td><?php echo user_edit_escape($pickRow['user_id']); ?></td>
                                    <td><?php echo user_edit_escape($pickRow['nick']); ?></td>
                                    <td><?php echo user_edit_escape($pickRow['xueYuan']); ?></td>
                                    <td><?php echo user_edit_escape($pickRow['school']); ?></td>
                                    <td><?php echo user_edit_escape($pickRow['email']); ?></td>
                                    <td><a class="btn btn-sm btn-primary" href="user_info_edit.php?user_id=<?php echo rawurlencode($pickRow['user_id']); ?>">编辑信息</a></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
            </div>
        </section>
    </div>
</div>
<script>
    document.getElementById('menu2').classList.remove("menu");
    document.getElementById('menu2').classList.add("menu-open");
    document.getElementById('a33').classList.remove("bg-primary");
    document.getElementById('a33').classList.add("bg-secondary");
    const password = document.getElementById('user-password');
    const confirmPassword = document.getElementById('user-confirm-password');
    if (password && confirmPassword) {
        function validatePasswords() {
            confirmPassword.setCustomValidity(password.value === confirmPassword.value ? '' : '两次输入的密码不一致。');
        }
        password.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }
</script>
</body>
