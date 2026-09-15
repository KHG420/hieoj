<?php
header('Content-Type:application/json; charset=utf-8');

$nj = isset($_GET['nj']) ? $_GET['nj'] : '';
$xy = isset($_GET['xy']) ? $_GET['xy'] : '';
$registration = isset($_GET['registration']) && (string)$_GET['registration'] === '1';

// 默认模式无筛选参数：保持原 getClass.php 契约，直接返回空数组（不查库、不猜学院）。
if (!$registration && (string)$nj === '' && (string)$xy === '') {
    exit('[]');
}

require_once('./include/db_info.inc.php');
require_once('./include/academic_directory.php');

// 注册模式（registration=1）：只返回当前开放年级范围内、指定学院的合格班级。
// 该模式不经过页面缓存，保证管理员保存新范围后立即生效；没有 xy 时返回空数组，
// 不提供“无筛选返回全部”的旁路。
if ($registration) {
    require_once('./include/academic_registration.php');
    $policy = academic_registration_policy_load(
        academic_registration_policy_path(academic_registration_policy_data_dir())
    );
    if (!$policy['ok']) {
        exit('[]');
    }
    $class = academic_directory_registration_classes(
        $xy,
        $nj,
        $policy['effective_min'],
        $policy['effective_max']
    );
    exit(json_encode($class, 256));
}

// 默认模式保持原有页面缓存与查询契约。
require_once('./include/cache_start.php');
$class = academic_directory_classes($nj, $xy);
exit(json_encode($class, 256));

?>
