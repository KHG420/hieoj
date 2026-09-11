<?php 
require_once(dirname(__FILE__)."/include/db_info.inc.php");
if(file_exists(dirname(__FILE__)."/template/".$OJ_TEMPLATE."/oj-footer.php"))
  require_once(dirname(__FILE__)."/template/".$OJ_TEMPLATE."/oj-footer.php");
else if(file_exists(dirname(__FILE__)."/template/".$OJ_TEMPLATE."/footer.php"))
  require_once(dirname(__FILE__)."/template/".$OJ_TEMPLATE."/footer.php");
?>