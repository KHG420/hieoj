<?php
$cache_time = 0;
$OJ_CACHE_SHARE = false;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');
require_once('./include/knowledge_graph.inc.php');

$view_title = '知识地图';
$knowledge_graph_ready = kg_schema_ready();
$knowledge_graph_data = null;
if ($knowledge_graph_ready) {
    $knowledge_user_id = isset($_SESSION[$OJ_NAME.'_'.'user_id']) ? $_SESSION[$OJ_NAME.'_'.'user_id'] : null;
    $knowledge_graph_data = kg_load_graph($knowledge_user_id);
}

require('template/'.$OJ_TEMPLATE.'/knowledge_graph.php');
if (file_exists('./include/cache_end.php')) require_once('./include/cache_end.php');
?>
