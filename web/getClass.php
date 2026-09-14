<?php
header('Content-Type:application/json; charset=utf-8');
require_once('./include/db_info.inc.php');
require_once('./include/cache_start.php');

$xueYuan = [
    "电气与信息工程学院" => "01", "机械工程学院" => "02", "信息科学与工程学院" => "03", "外国语学院" => "04",
    "经济学院" => "05", "材料与化工学院" => "06", "商学院" => "07", "纺织服装学院" => "08", "智慧建造与能源工程学院" => "09",
    "计算科学与电子学院" => "10", "体育科学与工程学院" => "11", "设计艺术学院" => "12", "应用技术学院" => "13",
    "智能科学与工程学院" => "14", "国际教育学院" => "17", "卓越工程师学院" => "45", "医学工程技术学院" => "47",
];

$nj = isset($_GET['nj']) ? $_GET['nj'] : '';
$xy = isset($_GET['xy']) ? $_GET['xy'] : '';
$class  = array();
if ($xy != "" && $nj != "") {
    $xy_num = $xueYuan[$xy] ?? null;
    $school_sql = "SELECT `value` FROM schoolList WHERE SUBSTR(num, 1, 4) = ? AND SUBSTR(num, 5, 2) = ? ORDER BY num";
    $class = pdo_query($school_sql, $nj, $xy_num);
} else if ($nj != "") {
    $school_sql = "SELECT `value` FROM schoolList WHERE SUBSTR(num, 1, 4) = ? ORDER BY num";
    $class = pdo_query($school_sql, $nj);
} else if ($xy != "") {
    $xy_num = $xueYuan[$xy] ?? null;
    $school_sql = "SELECT `value` FROM schoolList WHERE SUBSTR(num, 5, 2) = ? ORDER BY num";
    $class = pdo_query($school_sql, $xy_num);
}
exit(json_encode($class,256));

?>
