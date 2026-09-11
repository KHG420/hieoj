<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Edit Problem</title>
</head>
<?php
require_once("../include/db_info.inc.php");
require_once("admin-header.php");
require_once("../include/my_func.inc.php");
if(!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'problem_editor']))){
  echo "<a href='../loginpage.php'>Please Login First!</a>";
exit(1);
}
include_once("kindeditor.php") ;
?>

<body class="hold-transition sidebar-mini layout-fixed" >
<div class="wrapper">
<?php include("navbar.php");?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="row">
                <div class="col-sm-1">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </div>
            </div>
        </div>
        <center><h3><?php echo "Edit-"."$MSG_PROBLEM" ?> </h3></center>
  <div class="container">
    <?php
    if(isset($_GET['id'])){
      ;//require_once("../include/check_get_key.php");
    ?>

    <form method=POST action=problem_edit.php>
      <?php
      $sql="SELECT * FROM `problem` WHERE `problem_id`=?";
      $result=pdo_query($sql,intval($_GET['id']));
      $row=$result[0];

      $to_id = "";
      $cf_id = "";
      if ($row['spj'] == "4") {
          $tsql = "SELECT to_id,cf_id FROM `spj` WHERE problem_id=?";
          $res = pdo_query($tsql, intval($_GET['id']));
          $to_id = $res[0][0];
          $cf_id = $res[0][1];
      } else {
          $tsql = "SELECT to_id FROM `spj` WHERE problem_id=?";
          $res = pdo_query($tsql, intval($_GET['id']));
          $to_id = $res[0][0];
      }
      ?>

      <input type=hidden name=problem_id value='<?php echo $row['problem_id']?>'>
        <p align=left>
          <center><h3>
          <?php echo $row['problem_id']?>: <input class="input input-xxlarge" style='width:90%' type=text name=title value='<?php echo htmlentities($row['title'],ENT_QUOTES,"UTF-8")?>'>
          </h3></center>
        </p>
        <p align=left>
          <?php echo $MSG_Time_Limit?><br>
          <input class="input input-mini" type=text name=time_limit size=20 value='<?php echo htmlentities($row['time_limit'],ENT_QUOTES,"UTF-8")?>'> Sec<br><br>
          <?php echo $MSG_Memory_Limit?><br>
          <input class="input input-mini" type=text name=memory_limit size=20 value='<?php echo htmlentities($row['memory_limit'],ENT_QUOTES,"UTF-8")?>'> MB<br><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Description."</h4>"?>
          <textarea class="kindeditor" rows=13 name=description cols=80><?php echo htmlentities($row['description'],ENT_QUOTES,"UTF-8")?></textarea><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Input."</h4>"?>
          <textarea class="kindeditor" rows=13 name=input cols=80><?php echo htmlentities($row['input'],ENT_QUOTES,"UTF-8")?></textarea><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Output."</h4>"?>
          <textarea  class="kindeditor" rows=13 name=output cols=80><?php echo htmlentities($row['output'],ENT_QUOTES,"UTF-8")?></textarea><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Sample_Input."</h4>"?>
          <textarea  class="input input-large" style="width:100%;" rows=13 name=sample_input><?php echo htmlentities($row['sample_input'],ENT_QUOTES,"UTF-8")?></textarea><br><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Sample_Output."</h4>"?>
          <textarea  class="input input-large" style="width:100%;" rows=13 name=sample_output><?php echo htmlentities($row['sample_output'],ENT_QUOTES,"UTF-8")?></textarea><br><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_HINT."</h4>"?>
          <textarea class="kindeditor" rows=13 name=hint cols=80><?php echo htmlentities($row['hint'],ENT_QUOTES,"UTF-8")?></textarea><br>
        </p>
        <p>
          <?php echo "<h4>".$MSG_SPJ."</h4>"?>
          <?php echo "(".$MSG_HELP_SPJ.")"?><br>
<!--          --><?php //echo "No "?><!--<input type=radio name=spj value='0' --><?php //echo $row['spj']=="0"?"checked":""?>
<!--          --><?php //echo "/ Yes "?><!--<input type=radio name=spj value='1' --><?php //echo $row['spj']=="1"?"checked":""?>
            <select name=spj id="spj">
                <option value="0" <?php echo $row['spj']=="0"?"selected":""?>>No</option>
                <option value="1" <?php echo $row['spj']=="1"?"selected":""?>>Yes</option>
                <option value="2" <?php echo $row['spj']=="2"?"selected":""?>>Acwing</option>
                <option value="3" <?php echo $row['spj']=="3"?"selected":""?>>CSG</option>
                <option value="4" <?php echo $row['spj']=="4"?"selected":""?>>codeforces</option>
            </select>
            <br>
        <div>
            <div style="display: inline-block;">
                若要提交至其他平台评测，需输入题目编号（cf输入比赛编号）：<br>
                <input class="input" type=text name=toid value='<?php echo htmlentities($to_id,ENT_QUOTES,"UTF-8")?>'>
            </div>
            <div style="display: inline-block; margin-left: 30px;">
                cf填写题目编号（A,B,C...），其他平台不需要：<br>
                <input class="input" type="text" name="cf_id" value='<?php echo htmlentities($cf_id,ENT_QUOTES,"UTF-8")?>'>
            </div>
        </div>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_SOURCE."（Ctrl+单击多选）"."</h4>"?>
            <select name=source[] multiple id="mySelect" style="height: 300px; width: auto;">
                <?php
                $sql="SELECT `content-1`,`content-2`,`priority` FROM `category`  WHERE `status`=0 ORDER BY `priority`";
                $result=pdo_query($sql);
                echo "<option value=''>none</option>";
                if (count($result)==0){
                }else{
                    $cate = explode(" ",$row['source']);
                    foreach($result as $rw){
                        $str = "";
                        if ($rw['content-2'] == "")
                            $str = $rw['content-1'];
                        else
                            $str = $rw['content-1']."-".$rw['content-2'];

                        foreach ($cate as $cat) {
                            if (strcmp(htmlentities($cat,ENT_QUOTES,"UTF-8"), htmlentities($str,ENT_QUOTES,"UTF-8")) == 0)
                                echo "<option value='$str' selected>$str</option>";
                            else
                                echo "<option value='$str'>$str</option>";
                        }
                    }
                }?>
            </select>
          <br><br>
        </p>
        <div align=center>
          <?php require_once("../include/set_post_key.php");?>
          <input type=submit value=Submit name=submit>
        </div>
      </input>
    </form>
  </div>

    <?php
    }else{
      require_once("../include/check_post_key.php");
      $id=intval($_POST['problem_id']);
      if(!(isset($_SESSION[$OJ_NAME.'_'."p$id"])||isset($_SESSION[$OJ_NAME.'_'.'administrator']))) exit();  

      $title=$_POST['title'];
      $title = str_replace(",", "&#44;", $title);
      $time_limit=$_POST['time_limit'];
      $memory_limit=$_POST['memory_limit'];
      $description=$_POST['description'];
      $description = str_replace("<p>", "", $description); 
      $description = str_replace("</p>", "<br />", $description);
      $description = str_replace(",", "&#44;", $description);
      
      $input=$_POST['input'];
      $input = str_replace("<p>", "", $input); 
      $input = str_replace("</p>", "<br />", $input);
      $input = str_replace(",", "&#44;", $input);
      
      $output=$_POST['output'];
      $output = str_replace("<p>", "", $output); 
      $output = str_replace("</p>", "<br />", $output); 
      $output = str_replace(",", "&#44;", $output);

      $sample_input=$_POST['sample_input'];
      $sample_output=$_POST['sample_output'];
      $hint=$_POST['hint'];
      $hint = str_replace("<p>", "", $hint); 
      $hint = str_replace("</p>", "<br />", $hint);
      $hint = str_replace(",", "&#44;", $hint);

      $source = $_POST['source'];
      $source_str = "";
      foreach($source as $t){
          if ($source_str != "") $source_str = $source_str." ";
          $source_str = $source_str.$t;
      }
      $source = $source_str;
      $spj=$_POST['spj'];

      if((function_exists('get_magic_quotes_gpc')&&get_magic_quotes_gpc())){
        $title = stripslashes($title);
        $time_limit = stripslashes($time_limit);
        $memory_limit = stripslashes($memory_limit);
        $description = stripslashes($description);
        $input = stripslashes($input);
        $output = stripslashes($output);
        $sample_input = stripslashes($sample_input);
        $sample_output = stripslashes($sample_output);
        //$test_input = stripslashes($test_input);
        //$test_output = stripslashes($test_output);
        $hint = stripslashes($hint);
        $source = stripslashes($source); 
        $spj = stripslashes($spj);
        $source = stripslashes($source);
      }

      $title=($title);
      $description=RemoveXSS($description);
      $input=RemoveXSS($input);
      $output=RemoveXSS($output);
      $hint=RemoveXSS($hint);
      $basedir=$OJ_DATA."/$id";

      echo "Sample data file Updated!<br>";

      if($sample_input&&file_exists($basedir."/sample.in")){
        //mkdir($basedir);
        $fp=fopen($basedir."/sample.in","w");
        fputs($fp,preg_replace("(\r\n)","\n",$sample_input));
        fclose($fp);

        $fp=fopen($basedir."/sample.out","w");
        fputs($fp,preg_replace("(\r\n)","\n",$sample_output));
        fclose($fp);
      }

      $spj=intval($spj);
  
      $sql="UPDATE `problem` set `title`=?,`time_limit`=?,`memory_limit`=?,
                   `description`=?,`input`=?,`output`=?,`sample_input`=?,`sample_output`=?,`hint`=?,`source`=?,`spj`=?,`in_date`=NOW()
            WHERE `problem_id`=?";

      @pdo_query($sql,$title,$time_limit,$memory_limit,$description,$input,$output,$sample_input,$sample_output,$hint,$source,$spj,$id) ;

        if ($spj >= 2) {
            $toid = $_POST['toid'];
            $tsql = "SELECT COUNT(to_id) as num FROM `spj` WHERE problem_id=?";
            $res = pdo_query($tsql, $id);
            $num = $res[0][0];

            if ($num >= 1) {
                $sql = "UPDATE `spj` set `to_id`=? WHERE `problem_id`=?";
                pdo_query($sql, $toid, $id);
                if ($spj == 4) {
                    $cf_id = $_POST['cf_id'];
                    $sql = "UPDATE `spj` set `cf_id`=? WHERE `problem_id`=?";
                    pdo_query($sql, $cf_id, $id);
                }
            } else {
                $sql = "INSERT into `spj` (`problem_id`,`to_id`,`source`,`cf_id`) VALUES(?,?,?,?)";
                $cf_id = "0";
                if ($spj == 2) $source = "acwing";
                else if ($spj == 3) $source = "csg";
                else if ($spj == 4) {
                    $source = "codeforces";
                    $cf_id = $_POST['cf_id'];
                }
                //echo $sql;
                pdo_query($sql, $id, $toid, $source, $cf_id);
            }
        }

      echo "Edit OK!";
      echo "<a href='../problem.php?id=$id'>See The Problem!</a>";
    }
    ?>
  </div>
</body>
</html>    
