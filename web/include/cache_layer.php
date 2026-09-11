<?php
/**
 * 轻量缓存层（代码层性能优化用）
 * 优先使用 APCu，不可用时降级为临时目录文件缓存。
 * 只提供统一的 get/set 接口，不改变任何业务逻辑。
 */
if (!function_exists('cache_get')) {

    global $OJ_APCU_OK;
    if (!isset($OJ_APCU_OK)) {
        $OJ_APCU_OK = function_exists('apcu_fetch');
    }

    /**
     * 文件缓存根目录（webroot 之外，防止被直接访问）
     */
    function cache_root_dir() {
        $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'hustoj_cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }

    /**
     * 页面级缓存目录（webroot 之外）
     */
    function page_cache_dir() {
        $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'hustoj_page_cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }

    function cache_key_file($key) {
        return cache_root_dir() . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    function cache_get($key) {
        global $OJ_APCU_OK;
        if ($OJ_APCU_OK) {
            $ok = false;
            $val = apcu_fetch($key, $ok);
            if ($ok) {
                return $val;
            }
            return false;
        }
        $file = cache_key_file($key);
        if (!is_file($file)) {
            return false;
        }
        $data = @file_get_contents($file);
        if ($data === false) {
            return false;
        }
        $data = @unserialize($data);
        if (!is_array($data) || !isset($data['exp'], $data['val'])) {
            @unlink($file);
            return false;
        }
        if (time() >= $data['exp']) {
            @unlink($file);
            return false;
        }
        return $data['val'];
    }

    function cache_set($key, $val, $ttl = 60) {
        global $OJ_APCU_OK;
        if ($OJ_APCU_OK) {
            return apcu_store($key, $val, intval($ttl));
        }
        $file = cache_key_file($key);
        $data = serialize(array('exp' => time() + intval($ttl), 'val' => $val));
        return @file_put_contents($file, $data, LOCK_EX) !== false;
    }

    /**
     * 简单清理过期的文件缓存
     */
    function cache_gc($max_age = 3600) {
        $dirs = array(cache_root_dir(), page_cache_dir());
        foreach ($dirs as $dir) {
            $files = @glob($dir . DIRECTORY_SEPARATOR . '*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (@filemtime($file) < time() - $max_age) {
                        @unlink($file);
                    }
                }
            }
        }
    }
}
?>