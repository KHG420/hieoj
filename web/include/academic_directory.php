<?php
/**
 * 学院 / 班级目录查询助手。
 *
 * 本文件只提供与“学院班级目录”直接相关的只读查询，供注册、修改资料、排名、
 * 登录与 getClass.php 复用。约定保持不变：
 *   - $xueYuan 仍然返回 array(array(学院名称, 两位数字代码), ...)
 *   - getClass.php 仍然返回 JSON 数组，元素含 `value`（班级名称）
 *
 * 数据来源：
 *   - collegiate(id, name, source_id)
 *   - schoolList(school_id, num, value, join_time, collegiate_id, source_id)
 *
 * 年级派生规则：只有长度 10 或 11 且以 19/20 开头的班级编号才把前 4 位当作
 * 入学年份；8 位等旧编号不猜年份，返回 NULL。绝不从班级名称反推年级。
 */

/**
 * 当前页面代码里既有的 17 个学院（名称 => 两位数字代码）。
 * 迁移脚本也以此为准补齐 collegiate 表；与源站快照的 source_id 无关。
 */
function academic_directory_canonical_colleges()
{
    return array(
        array('电气与信息工程学院', '01'),
        array('机械工程学院', '02'),
        array('信息科学与工程学院', '03'),
        array('外国语学院', '04'),
        array('经济学院', '05'),
        array('材料与化工学院', '06'),
        array('商学院', '07'),
        array('纺织服装学院', '08'),
        array('智慧建造与能源工程学院', '09'),
        array('计算科学与电子学院', '10'),
        array('体育科学与工程学院', '11'),
        array('设计艺术学院', '12'),
        array('应用技术学院', '13'),
        array('智能科学与工程学院', '14'),
        array('国际教育学院', '17'),
        array('卓越工程师学院', '45'),
        array('医学工程技术学院', '47'),
    );
}

/**
 * 可识别的入学年份表达式（MySQL/SQLite 通用，测试可注册 CHAR_LENGTH）。
 */
function academic_directory_year_expr($column)
{
    return "CASE WHEN CHAR_LENGTH($column) IN (10,11) AND SUBSTR($column,1,2) IN ('19','20')"
        . " THEN SUBSTR($column,1,4) ELSE NULL END";
}

/**
 * 学院下拉数据：array(array(名称, 两位代码), ...)。
 * collegiate 不可用或为空时回退到页面既有的 17 学院，保证下拉不消失。
 */
function academic_directory_colleges()
{
    $rows = pdo_query("SELECT `id`,`name` FROM `collegiate` WHERE `name` IS NOT NULL AND `name` <> '' ORDER BY `id`");
    if (!is_array($rows) || count($rows) === 0) {
        return academic_directory_canonical_colleges();
    }
    $out = array();
    foreach ($rows as $row) {
        if (!isset($row['id']) || !isset($row['name'])) {
            continue;
        }
        $out[] = array((string)$row['name'], str_pad((string)intval($row['id']), 2, '0', STR_PAD_LEFT));
    }
    if (count($out) === 0) {
        return academic_directory_canonical_colleges();
    }
    return $out;
}

/**
 * 最新可识别入学年份（4 位字符串）或 null。
 */
function academic_directory_latest_year()
{
    $expr = academic_directory_year_expr('`num`');
    $rows = pdo_query("SELECT MAX($expr) AS `y` FROM `schoolList`");
    if (!is_array($rows) || !isset($rows[0]['y']) || $rows[0]['y'] === null || $rows[0]['y'] === '') {
        return null;
    }
    return (string)$rows[0]['y'];
}

/**
 * 可识别的入学年份列表（两位字符串，倒序）。
 */
function academic_directory_years($limit = 8)
{
    $limit = max(1, min(50, (int)$limit));
    $expr = academic_directory_year_expr('`num`');
    $rows = pdo_query("SELECT DISTINCT $expr AS `y` FROM `schoolList` WHERE $expr IS NOT NULL ORDER BY `y` DESC LIMIT $limit");
    $out = array();
    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (isset($row['y']) && $row['y'] !== null && $row['y'] !== '') {
                $out[] = substr((string)$row['y'], 2, 2);
            }
        }
    }
    return $out;
}

/**
 * 某学院名称（或两位代码）对应的本地 collegiate.id；找不到返回 null。
 */
function academic_directory_collegiate_id_by_name_or_code($xy)
{
    $xy = (string)$xy;
    if ($xy === '') {
        return null;
    }
    $rows = pdo_query("SELECT `id` FROM `collegiate` WHERE `name` = ? LIMIT 1", $xy);
    if (is_array($rows) && count($rows) > 0 && isset($rows[0]['id'])) {
        return (int)$rows[0]['id'];
    }
    if (preg_match('/^\d{1,3}$/', $xy)) {
        $rows = pdo_query("SELECT `id` FROM `collegiate` WHERE `id` = ? LIMIT 1", (int)$xy);
        if (is_array($rows) && count($rows) > 0 && isset($rows[0]['id'])) {
            return (int)$rows[0]['id'];
        }
    }
    return null;
}

/**
 * getClass.php 的查询实现。
 *
 * 按名称去重，并处理“同名历史旧行 vs 权威源行”：
 *   - 已同步（source_id 非空）的记录始终保留，按其所归属学院查询；
 *   - 未同步的历史旧行，如果同名班级已存在归属其它学院的源行，则不再返回；
 *   - 完全没有源行时，保留旧行行为。
 *   - `nj` 与 `xy` 都为空时返回空数组（原 getClass.php 无筛选契约）。
 *
 * @param string $nj 入学年份（4 位）或空
 * @param string $xy 学院名称（或两位代码）或空
 * @return array 元素为 array('value' => 班级名称)
 */
function academic_directory_classes($nj = '', $xy = '')
{
    // 无筛选参数时保持原 getClass.php 的契约：返回空数组，不返回全部班级。
    if ((string)$nj === '' && (string)$xy === '') {
        return array();
    }

    $conds = array();
    $params = array();
    $yearExpr = academic_directory_year_expr('s.`num`');

    if ((string)$xy !== '') {
        $collegiateId = academic_directory_collegiate_id_by_name_or_code($xy);
        if ($collegiateId === null) {
            return array();
        }
        $conds[] = "s.`collegiate_id` = ?";
        $params[] = $collegiateId;
    }

    if ((string)$nj !== '') {
        $nj = (string)$nj;
        if (!preg_match('/^(19|20)\d{2}$/', $nj)) {
            return array();
        }
        $conds[] = "$yearExpr = ?";
        $params[] = $nj;
    }

    $extra = count($conds) > 0 ? (' AND ' . implode(' AND ', $conds)) : '';
    $sql = "SELECT DISTINCT s.`value` AS `value` FROM `schoolList` s"
        . " WHERE (s.`source_id` IS NOT NULL OR NOT EXISTS ("
        . " SELECT 1 FROM `schoolList` t WHERE t.`value` = s.`value` AND t.`source_id` IS NOT NULL"
        . " AND (t.`collegiate_id` <> s.`collegiate_id` OR s.`collegiate_id` IS NULL)"
        . "))" . $extra
        . " ORDER BY s.`value`";

    $rows = count($params) > 0 ? pdo_query($sql, ...$params) : pdo_query($sql);
    return is_array($rows) ? $rows : array();
}

/**
 * 近若干年的班级（按可识别入学年份过滤），元素含 value。
 *
 * @param int|string $minYearExclusive 严格大于该年份
 */
function academic_directory_recent_classes($minYearExclusive)
{
    $expr = academic_directory_year_expr('`num`');
    $rows = pdo_query(
        "SELECT `value` FROM `schoolList` WHERE $expr IS NOT NULL AND $expr > ? ORDER BY `num`",
        (string)$minYearExclusive
    );
    return is_array($rows) ? $rows : array();
}

/**
 * 近若干年的班级，附带学院两位代码（xy_num），供修改资料按学院分组。
 *
 * @param int|string $minYearExclusive 严格大于该年份
 */
function academic_directory_recent_classes_by_college($minYearExclusive)
{
    $expr = academic_directory_year_expr('s.`num`');
    $rows = pdo_query(
        "SELECT c.`id` AS `xy_num`, s.`value` AS `value`"
        . " FROM `schoolList` s JOIN `collegiate` c ON c.`id` = s.`collegiate_id`"
        . " WHERE $expr IS NOT NULL AND $expr > ? ORDER BY s.`num`",
        (string)$minYearExclusive
    );
    if (!is_array($rows)) {
        return array();
    }
    foreach ($rows as $i => $row) {
        $rows[$i]['xy_num'] = str_pad((string)intval($row['xy_num']), 2, '0', STR_PAD_LEFT);
    }
    return $rows;
}
