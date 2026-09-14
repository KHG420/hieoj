<?php
header('Content-Type:application/json; charset=utf-8');

$nj = isset($_GET['nj']) ? $_GET['nj'] : '';
$xy = isset($_GET['xy']) ? $_GET['xy'] : '';

// 无筛选参数：保持原 getClass.php 契约，直接返回空数组（不查库、不猜学院）。
if ((string)$nj === '' && (string)$xy === '') {
    exit('[]');
}

require_once('./include/db_info.inc.php');
require_once('./include/cache_start.php');
require_once('./include/academic_directory.php');

$class = academic_directory_classes($nj, $xy);
exit(json_encode($class, 256));

?>
