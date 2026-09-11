<?php
// cache foot start
if (!empty($OJ_CACHE_ACTIVE) && isset($file) && $file && (!isset($_SERVER['REQUEST_METHOD'])||$_SERVER['REQUEST_METHOD']!='POST')) {
    // 判断是否启用 Memcache
    if ($OJ_MEMCACHE) {
        // 使用 Memcache 存储缓存
        $mem->set($file, ob_get_contents(), 0, $cache_time);
    } else {
        // 写入 webroot 之外的文件缓存目录
        if (!file_exists(page_cache_dir())) {
            @mkdir(page_cache_dir(), 0700, true);
        }
        @file_put_contents($file, ob_get_contents(), LOCK_EX);
    }
}
// cache foot stop
?>