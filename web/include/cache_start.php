<?php
        require_once(dirname(__FILE__)."/cache_layer.php");
        //cache head start
        if(!isset($cache_time)) $cache_time=60;
        $use_cache=false;
        $write_cache=true;
        // 性能优化：概率触发缓存垃圾回收，避免缓存目录无限膨胀
        if(mt_rand(1,100)==1) cache_gc(3600);
        // 提前开启会话以便缓存键区分语言（db_info 中再次调用无副作用）
        if(!isset($OJ_NAME)) $OJ_NAME=''; // db_info 尚未加载时避免未定义告警
        // 会话 Cookie 标志需在会话启动前设置
        @ini_set("session.cookie_httponly", "1");
        @ini_set("session.cookie_samesite", "Lax");
        if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') @ini_set("session.cookie_secure", "1");
        if(session_status()===PHP_SESSION_NONE) @session_start();
        // 登录态检测：此时 db_info 可能尚未加载（$OJ_NAME 未定义），通过扫描会话键后缀判断
        $session_logged_in=false;
        foreach((array)$_SESSION as $sk=>$sv){
            if(substr($sk,-8)==='_user_id' && !empty($sv)){ $session_logged_in=true; break; }
        }
        // POST 请求不做页面缓存
        if(isset($_SERVER['REQUEST_METHOD'])&&$_SERVER['REQUEST_METHOD']=='POST'){
                ob_start();
        }else{
                $sid=(isset($OJ_NAME)?$OJ_NAME:"").$_SERVER["HTTP_HOST"];
                // 修复：共享缓存仅限未登录用户；登录用户始终使用各自会话的页面缓存。
                //（避免登录/退出后右上角登录态串台，也防止某用户的名字被缓存给所有人看）
                $OJ_CACHE_SHARE=(isset($OJ_CACHE_SHARE)&&$OJ_CACHE_SHARE)&&!$session_logged_in;
                if (!$OJ_CACHE_SHARE&&$session_logged_in){
                        $ip = ($_SERVER['REMOTE_ADDR']);
                        if( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
                            $REMOTE_ADDR = $_SERVER['HTTP_X_FORWARDED_FOR'];
                            $tmp_ip=explode(',',$REMOTE_ADDR);
                            $ip =(htmlentities($tmp_ip[0],ENT_QUOTES,"UTF-8"));
                        }
                        $sid.=session_id().$ip;
                }
                if (isset($_SERVER["REQUEST_URI"])){
                        $sid.=$_SERVER["REQUEST_URI"];
                }
                // 缓存键加入语言，避免切换语言后命中旧语言页面（此时 $OJ_NAME 可能尚未定义，扫描会话键）
                $session_lang='';
                foreach((array)$_SESSION as $sk=>$sv){
                    if(substr($sk,-8)==='_OJ_LANG'){ $session_lang=(string)$sv; break; }
                }
                $sid.=$session_lang;

                $sid=md5($sid);
                $file = page_cache_dir().DIRECTORY_SEPARATOR."cache_$sid.html";

                if(!empty($OJ_MEMCACHE)){ // db_info 未加载时避免未定义告警
                        $mem = new Memcache;
                        if($OJ_SAE)
                                $mem=memcache_init();
                        else{
                                $mem->connect($OJ_MEMSERVER,  $OJ_MEMPORT);
                        }
                        $content=$mem->get($file);
                        if($content){
                                 echo $content;
                                 exit();
                        }
                }else{
                        if (file_exists ( $file ))
                                $last = filemtime ( $file );
                        else
                                $last =0;
                        $use_cache=(time () - $last < $cache_time);

                }
                if ($use_cache) {
                        include ($file);
                        exit ();
                } else {
                        $OJ_CACHE_ACTIVE=true;
                        ob_start ();
                }
        }
//cache head stop
?>