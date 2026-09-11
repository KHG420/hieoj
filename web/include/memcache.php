<?php
        
    require_once(dirname(__FILE__)."/db_info.inc.php");
    require_once(dirname(__FILE__)."/cache_layer.php");
    # Connect to memcache:
    global $memcache;
    if ($OJ_MEMCACHE){
	$memcache = new Memcache;
	if($OJ_SAE)
				$memcache=memcache_init();
		else{
				$memcache->connect($OJ_MEMSERVER,  $OJ_MEMPORT);
	}
    }

    # Gets key / value pair into memcache
    function getCache($key) {
        global $memcache;
        return ($memcache) ? $memcache->get($key) : false;
    }

    # Puts key / value pair into memcache
    function setCache($key, $object, $timeout = 60) {
        global $memcache;
        return ($memcache) ? $memcache->set($key,$object,MEMCACHE_COMPRESSED,$timeout) : false;
    }


    function mysql_query_cache($sql){
        global $OJ_NAME,$OJ_MEMCACHE,$OJ_APCU_OK;
        $num_args = func_num_args();
        $args = func_get_args();
        $args = array_slice($args,1,--$num_args);
        // 安全修复：仅缓存 SELECT 查询；写操作直接执行（避免污染缓存）
        if(stripos(trim($sql),"select")!==0){
            return pdo_query($sql, ...$args);
        }
        $key=md5($OJ_NAME.$_SERVER['HTTP_HOST']."mysql_query" . $sql.implode(" ",$args));
        $timeout = 5;

        // 1) APCu 优先
        if($OJ_APCU_OK){
            $apcu_success=false;
            $cache=apcu_fetch($key,$apcu_success);
            if($apcu_success){
                return $cache;
            }
            $s_time=microtime(true);
            $cache = pdo_query($sql, ...$args);
            $u_time=microtime(true)-$s_time;
            if($u_time>$timeout/5) $timeout=round($u_time+1)*5;
            apcu_store($key,$cache,$timeout);
            return $cache;
        }

        // 2) memcached
        if ($OJ_MEMCACHE){
            if (($cache = getCache($key)) !== false) {
                return $cache;
            }
            $s_time=microtime(true);
            $cache = pdo_query($sql, ...$args);
            $u_time=microtime(true)-$s_time;
            if($u_time>$timeout/5) $timeout=round($u_time+1)*5;
            setCache($key,$cache,$timeout);
            return $cache;
        }

        // 3) 降级：临时目录文件缓存
        $cache = cache_get($key);
        if ($cache !== false) {
            return $cache;
        }
        $s_time=microtime(true);
        $cache = pdo_query($sql, ...$args);
        $u_time=microtime(true)-$s_time;
        if($u_time>$timeout/5) $timeout=round($u_time+1)*5;
        cache_set($key,$cache,$timeout);
        return $cache;
    }
?>
