<?php
/**
 * 注册年级范围策略（只读查询 + 管理员策略文件读写）。
 *
 * 目的：新用户注册时只允许选择“当前开放年级范围”内的学院 / 班级；历史年级
 * （已毕业）与未来年级不再出现在新注册下拉中，但既有用户数据、历史查询与
 * 其它页面行为完全不受影响。
 *
 * 持久化：不建表、不加列，只在既有 OJ_DATA 持久目录（/home/judge/data，
 * 由 judge-data 卷挂载、www-data 可写）里保存一个小 JSON 文件。
 *   - 文件缺失 => 自动模式，开放 [当前日历年 - 2, 当前日历年]（含两端）；
 *   - 自动模式每年按当前日历年滚动，不依赖任何导入的旧数据，也不改写文件；
 *   - 管理员可改为自定义闭区间（1900..2099，起始 <= 截止）。
 *   - 读取（含注册页 / getClass / register.php 校验）绝不写文件；
 *     保存使用“同目录临时文件 + rename”原子替换。
 *
 * 约定：本文件不引入新依赖、不访问网络、不触碰 users 表或任何数据库结构。
 */

/** 策略文件名（位于 OJ_DATA 目录下、webroot 之外）。 */
function academic_registration_policy_filename()
{
    return 'registration_year_policy.json';
}

/** 默认数据目录：优先 OJ_DATA，其次生产约定的 /home/judge/data。 */
function academic_registration_policy_data_dir()
{
    $dir = isset($GLOBALS['OJ_DATA']) ? $GLOBALS['OJ_DATA'] : '';
    if (!is_string($dir) || trim($dir) === '') {
        $dir = '/home/judge/data';
    }
    return rtrim($dir, '/\\');
}

/** 由数据目录得到策略文件绝对路径；目录为空时返回空串。 */
function academic_registration_policy_path($dataDir)
{
    $dir = rtrim((string)$dataDir, '/\\');
    if ($dir === '') {
        return '';
    }
    return $dir . '/' . academic_registration_policy_filename();
}

/**
 * 该模板的注册表单是否使用“学院 + 专业班级”下拉，从而必须走注册年级范围校验。
 *
 * 只依据可信的 OJ_TEMPLATE 配置判断（syzoj / sta_sty），**绝不**依据请求里是否
 * 带了 xueYuan 字段：否则空 / 缺失 / 数组的学院字段就能绕过策略。bs3 / sweet 等
 * 自由填写班级的旧模板返回 false，保持既有行为不受影响。
 */
function academic_registration_policy_template_requires_selection($template)
{
    return in_array((string)$template, array('syzoj', 'sta_sty'), true);
}

/**
 * 从请求数组中安全读取一个标量字符串字段。
 *
 * 用于注册提交的学院 / 班级：缺失、数组、布尔、对象、null 都视为“非标量”，
 * 返回空串并通过 $scalar 标记出来，交由调用方显式拒绝，避免 trim() / strlen()
 * 对数组报致命错误或产生 PHP 警告。合法标量则去除首尾空白后返回。
 */
function academic_registration_policy_scalar_request($input, $key, &$scalar = null)
{
    $scalar = false;
    if (!is_array($input) || !array_key_exists($key, $input)) {
        return '';
    }
    $value = $input[$key];
    if ($value === null || is_array($value) || is_bool($value) || is_object($value)) {
        return '';
    }
    $scalar = true;
    return trim((string)$value);
}

/** 当前日历年（测试可注入）。 */
function academic_registration_policy_current_year($now = null)
{
    return (int)date('Y', $now === null ? time() : (int)$now);
}

/** 自动模式的有效范围：当前日历年 - 2 .. 当前日历年（含两端）。 */
function academic_registration_policy_auto_range($currentYear)
{
    $currentYear = (int)$currentYear;
    return array('min' => $currentYear - 2, 'max' => $currentYear);
}

/** 校验单个年份是否落在允许区间。 */
function academic_registration_policy_year_in_bounds($year)
{
    return is_int($year) && $year >= 1900 && $year <= 2099;
}

/**
 * 组装对外统一的状态结构。所有读取路径都返回该结构：
 *   ok / exists / error / mode / min / max / effective_min / effective_max /
 *   auto_min / auto_max / current_year / saved_at
 */
function academic_registration_policy_state($currentYear, $mode, $min, $max, $savedAt = '')
{
    $currentYear = (int)$currentYear;
    $auto = academic_registration_policy_auto_range($currentYear);
    if ($mode !== 'custom') {
        $mode = 'auto';
    }
    if ($mode === 'custom' && academic_registration_policy_year_in_bounds($min)
        && academic_registration_policy_year_in_bounds($max) && $min <= $max) {
        $effectiveMin = (int)$min;
        $effectiveMax = (int)$max;
    } else {
        $mode = 'auto';
        $min = null;
        $max = null;
        $effectiveMin = $auto['min'];
        $effectiveMax = $auto['max'];
    }
    return array(
        'ok' => true,
        'exists' => false,
        'error' => '',
        'mode' => $mode,
        'min' => $min === null ? null : (int)$min,
        'max' => $max === null ? null : (int)$max,
        'effective_min' => $effectiveMin,
        'effective_max' => $effectiveMax,
        'auto_min' => $auto['min'],
        'auto_max' => $auto['max'],
        'current_year' => $currentYear,
        'saved_at' => (string)$savedAt,
    );
}

/** 在状态上标注错误（读取失败时显式报告，而不是默默当默认值）。 */
function academic_registration_policy_state_error(array $state, $message)
{
    $state['ok'] = false;
    $state['error'] = (string)$message;
    return $state;
}

/**
 * 校验管理员提交的字段（$_POST 子集），返回 array(ok, error, policy)。
 * 只接受 'auto'/'custom' 与 1900..2099 的整数年份；数组 / 非法类型一律拒绝。
 */
function academic_registration_policy_validate_fields($input, $currentYear)
{
    if (!is_array($input)) {
        return array('ok' => false, 'error' => '提交数据格式不正确。', 'policy' => null);
    }
    $mode = isset($input['mode']) ? $input['mode'] : '';
    if (!is_string($mode)) {
        return array('ok' => false, 'error' => '请选择“自动更新”或“自定义范围”。', 'policy' => null);
    }
    $mode = trim($mode);
    if ($mode === 'auto') {
        // 自动模式忽略年份输入框（表单可能残留上一次自定义值），但数组等非法类型仍拒绝。
        if ((isset($input['min']) && is_array($input['min'])) || (isset($input['max']) && is_array($input['max']))) {
            return array('ok' => false, 'error' => '提交数据格式不正确。', 'policy' => null);
        }
        return array('ok' => true, 'error' => '', 'policy' => array('mode' => 'auto', 'min' => null, 'max' => null));
    }
    if ($mode !== 'custom') {
        return array('ok' => false, 'error' => '请选择“自动更新”或“自定义范围”。', 'policy' => null);
    }

    $minRaw = isset($input['min']) ? $input['min'] : '';
    $maxRaw = isset($input['max']) ? $input['max'] : '';
    if (is_array($minRaw) || is_array($maxRaw) || is_bool($minRaw) || is_bool($maxRaw)
        || (!is_string($minRaw) && !is_int($minRaw)) || (!is_string($maxRaw) && !is_int($maxRaw))) {
        return array('ok' => false, 'error' => '起始 / 截止年份必须是 1900..2099 的整数。', 'policy' => null);
    }
    $minStr = trim((string)$minRaw);
    $maxStr = trim((string)$maxRaw);
    if (preg_match('/^\d{4}$/', $minStr) !== 1 || preg_match('/^\d{4}$/', $maxStr) !== 1) {
        return array('ok' => false, 'error' => '起始 / 截止年份必须是 4 位年份（1900..2099）。', 'policy' => null);
    }
    $min = (int)$minStr;
    $max = (int)$maxStr;
    if (!academic_registration_policy_year_in_bounds($min) || !academic_registration_policy_year_in_bounds($max)) {
        return array('ok' => false, 'error' => '起始 / 截止年份必须在 1900..2099 之间。', 'policy' => null);
    }
    if ($min > $max) {
        return array('ok' => false, 'error' => '起始入学年份不能晚于截止入学年份。', 'policy' => null);
    }
    return array('ok' => true, 'error' => '', 'policy' => array('mode' => 'custom', 'min' => $min, 'max' => $max));
}

/**
 * 读取策略文件。任何异常（不可读 / 非法 JSON / 结构错误）都返回 ok=false
 * 与明确 error，同时给出自动模式的回退有效范围，绝不写文件。
 */
function academic_registration_policy_load($file, $currentYear = null)
{
    if ($currentYear === null) {
        $currentYear = academic_registration_policy_current_year();
    }
    $state = academic_registration_policy_state($currentYear, 'auto', null, null);
    $file = (string)$file;
    if ($file === '') {
        return academic_registration_policy_state_error($state, '未配置数据目录，无法读取注册年份策略。');
    }
    if (!file_exists($file)) {
        return $state;
    }
    $state['exists'] = true;
    if (!is_readable($file)) {
        return academic_registration_policy_state_error($state, '策略文件不可读。');
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return academic_registration_policy_state_error($state, '策略文件读取失败。');
    }
    $decoded = json_decode($raw);
    if (!is_object($decoded)) {
        return academic_registration_policy_state_error($state, '策略文件不是合法的 JSON 对象。');
    }
    if (!isset($decoded->mode) || !is_string($decoded->mode)) {
        return academic_registration_policy_state_error($state, '策略文件缺少 mode 字段。');
    }
    $savedAt = isset($decoded->saved_at) && is_string($decoded->saved_at) ? $decoded->saved_at : '';
    if ($decoded->mode === 'auto') {
        if (isset($decoded->min) && $decoded->min !== null) {
            return academic_registration_policy_state_error($state, '自动模式下不应包含起始年份。');
        }
        if (isset($decoded->max) && $decoded->max !== null) {
            return academic_registration_policy_state_error($state, '自动模式下不应包含截止年份。');
        }
        $loaded = academic_registration_policy_state($currentYear, 'auto', null, null, $savedAt);
        $loaded['exists'] = true;
        return $loaded;
    }
    if ($decoded->mode !== 'custom') {
        return academic_registration_policy_state_error($state, '策略文件 mode 取值非法。');
    }
    $min = isset($decoded->min) ? $decoded->min : null;
    $max = isset($decoded->max) ? $decoded->max : null;
    if (!academic_registration_policy_year_in_bounds($min) || !academic_registration_policy_year_in_bounds($max) || $min > $max) {
        return academic_registration_policy_state_error($state, '策略文件自定义年份范围非法。');
    }
    $loaded = academic_registration_policy_state($currentYear, 'custom', $min, $max, $savedAt);
    $loaded['exists'] = true;
    return $loaded;
}

/**
 * 保存策略：先严格校验输入（非法输入不落盘、旧文件保持原样），再用
 * “同目录临时文件 + rename” 原子替换，最后重新读取校验写入成功。
 *
 * 返回 array(ok, error, replaced_invalid, existing_error, state)。
 *   - ok=false 时 error 为可直接显示的中文原因，文件未被改动；
 *   - 若旧文件损坏 / 不可读，保存合法配置会替换它，并在结果中显式说明
 *     （replaced_invalid=true），绝不默默假装成功。
 */
function academic_registration_policy_save($file, array $input, $currentYear = null)
{
    if ($currentYear === null) {
        $currentYear = academic_registration_policy_current_year();
    }
    $validation = academic_registration_policy_validate_fields($input, $currentYear);
    if (!$validation['ok']) {
        return array(
            'ok' => false,
            'error' => $validation['error'],
            'replaced_invalid' => false,
            'existing_error' => '',
            'state' => academic_registration_policy_load($file, $currentYear),
        );
    }
    $policy = $validation['policy'];

    $existing = academic_registration_policy_load($file, $currentYear);
    $replacedInvalid = ($existing['exists'] && !$existing['ok']);
    $existingError = $replacedInvalid ? $existing['error'] : '';

    $file = (string)$file;
    if ($file === '') {
        return array(
            'ok' => false,
            'error' => '未配置数据目录，无法保存注册年份策略。',
            'replaced_invalid' => false,
            'existing_error' => '',
            'state' => $existing,
        );
    }
    $dir = dirname($file);
    if (!is_dir($dir)) {
        return array(
            'ok' => false,
            'error' => '数据目录不存在：' . $dir,
            'replaced_invalid' => false,
            'existing_error' => '',
            'state' => $existing,
        );
    }
    if (!is_writable($dir)) {
        return array(
            'ok' => false,
            'error' => '数据目录不可写：' . $dir,
            'replaced_invalid' => false,
            'existing_error' => '',
            'state' => $existing,
        );
    }

    $payload = array(
        'mode' => $policy['mode'],
        'min' => $policy['mode'] === 'custom' ? (int)$policy['min'] : null,
        'max' => $policy['mode'] === 'custom' ? (int)$policy['max'] : null,
        'saved_at' => date('c'),
    );
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return array(
            'ok' => false,
            'error' => '策略序列化失败。',
            'replaced_invalid' => false,
            'existing_error' => '',
            'state' => $existing,
        );
    }

    $tmp = $file . '.tmp.' . bin2hex(random_bytes(6));
    $written = @file_put_contents($tmp, $json, LOCK_EX);
    if ($written === false) {
        @unlink($tmp);
        return array(
            'ok' => false,
            'error' => '写入临时策略文件失败，原配置未改动。',
            'replaced_invalid' => false,
            'existing_error' => '',
            'state' => $existing,
        );
    }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return array(
            'ok' => false,
            'error' => '替换策略文件失败，原配置未改动。',
            'replaced_invalid' => false,
            'existing_error' => '',
            'state' => $existing,
        );
    }

    // 写入后复读校验，避免“保存了但实际没生效”却显示成功。
    $verify = academic_registration_policy_load($file, $currentYear);
    $expectedOk = $verify['ok'] && $verify['mode'] === $policy['mode'];
    if ($expectedOk && $policy['mode'] === 'custom') {
        $expectedOk = $verify['min'] === (int)$policy['min'] && $verify['max'] === (int)$policy['max'];
    }
    if (!$expectedOk) {
        return array(
            'ok' => false,
            'error' => '策略文件写入后校验失败，请检查数据目录权限后重试。',
            'replaced_invalid' => $replacedInvalid,
            'existing_error' => $existingError,
            'state' => $verify,
        );
    }
    return array(
        'ok' => true,
        'error' => '',
        'replaced_invalid' => $replacedInvalid,
        'existing_error' => $existingError,
        'state' => $verify,
    );
}

/** 有效范围的展示文本，例如 “2024 - 2026”。 */
function academic_registration_policy_effective_label(array $state)
{
    return (int)$state['effective_min'] . ' - ' . (int)$state['effective_max'];
}
