<?php
function pdo_query($sql){
    $num_args = func_num_args();
    $args = func_get_args();       //获得传入的所有参数的数组
    $args=array_slice($args,1,--$num_args);
    
    global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS,$dbh,$OJ_SAE;
    if(!$dbh){
			
		if(isset($OJ_SAE)&&$OJ_SAE)	{
			$OJ_DATA="saestor://data/";
		//  for sae.sina.com.cn
			$DB_NAME=SAE_MYSQL_DB;
			$dbh=new PDO("mysql:host=".SAE_MYSQL_HOST_M.';dbname='.SAE_MYSQL_DB, SAE_MYSQL_USER, SAE_MYSQL_PASS,array(PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"));
		}else{
			$dbh=new PDO("mysql:host=".$DB_HOST.';dbname='.$DB_NAME, $DB_USER, $DB_PASS,array(PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"));
		}
		
    }

    $sth = $dbh->prepare($sql);
    // 兼容：PHP8 默认 ERRMODE_EXCEPTION，查询失败会抛致命异常；
    // 这里捕获后返回 false，保持与旧版一致的容错行为
    try {
        $res = $sth->execute($args);
    } catch (PDOException $e) {
        @$sth->closeCursor();
        return false;
    }
    $result=array();
    if(stripos($sql,"select") === 0){
        $result=$sth->fetchAll();
    }else if(stripos($sql,"insert") === 0){
        if (stripos($sql,"users"))  // 在users里添加用户
            return $res;
        else $result=$dbh->lastInsertId();
    }else{
        $result=$sth->rowCount();
    }
    //print($sql);
    $sth->closeCursor();
    return $result;
}
?>
