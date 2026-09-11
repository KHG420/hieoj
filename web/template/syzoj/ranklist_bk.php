<!DOCTYPE html>
<html lang="en">

<?php
// 新增：定义xy、nj、school变量，处理默认值
$xy = isset($_GET['xy']) ? $_GET['xy'] : '';
$nj = isset($_GET['nj']) ? $_GET['nj'] : '';
$school = isset($_GET['school']) ? $_GET['school'] : '';
?>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="../../favicon.ico">
    <link rel="stylesheet" href="./template/sta_sty/layui/css/layui.css">
    <script src="<?php echo $OJ_CDN_URL?>/include/jquery-latest.js"></script>
    <script src="./template/sta_sty/layui/layui.js"></script>
    <title>
        <?php echo $OJ_NAME ?>
    </title>
</head>
<body>

    <?php include("template/$OJ_TEMPLATE/header.php"); ?>

<!--  <form action="ranklist.php" class="ui form" method="get" role="form" style="margin-bottom: 25px; text-align: left; display: inline;">-->
<!--    <div class="ui action left icon input inline" style="width: 180px; margin-right: 77px; ">-->
<!--      <i class="search icon"></i><input name="prefix" placeholder="学号或姓名" type="text" value="--><?php //echo htmlentities(isset($_GET['prefix'])?$_GET['prefix']:"",ENT_QUOTES,"utf-8") ?><!--">-->
<!--        <input name="prefix" placeholder="学号或姓名" type="text" value="--><?php //echo htmlentities(isset($_GET['prefix'])?$_GET['prefix']:"",ENT_QUOTES,"utf-8") ?><!--">-->
<!--      <button class="ui button" type="submit">--><?php //echo $MSG_SEARCH?><!--</button>-->
<!--    </div>-->
<!--  </form>-->
    <form class="layui-form layui-form-pane" action="ranklist.php" method="get" style="margin-top: 20px; margin-bottom: 40px;">
        <div class="layui-form-item">
            <div class="layui-input-inline">
                <select name="xy" id="xy" lay-filter="xy-dropdown">
                    <option value="">学院</option>
                    <?php
                    foreach ($xueYuan as $rows) {
                        if (strcmp($rows[0], $_GET['xy']) == 0)
                            echo '<option value="' . $rows[0] . '" selected>' . $rows[0] . '</option>';
                        else
                            echo '<option value="' . $rows[0] . '">' . $rows[0] . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="layui-input-inline">
                <select name="nj" lay-filter="nj-dropdown">
                    <option value="">年级</option>
                    <?php
                    foreach ($nianJi as $rows) {
                        $nnj = "20".$rows;
                        if (strcmp($rows, $_GET['nj']) == 0)
                            echo '<option value="' . $rows . '" selected>' . $nnj . '</option>';
                        else
                            echo '<option value="' . $rows . '">' . $nnj . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="layui-input-inline" id="classList">
                <select name="school" lay-filter="school-dropdown">
                    <option value="">专业班级</option>
                </select>
            </div>
            <div class="layui-input-inline">
                <input type="text" name="prefix" lay-verify="title" autocomplete="off" placeholder="姓名或学号" class="layui-input" value="<?php echo htmlentities(isset($_GET['prefix'])?$_GET['prefix']:"",ENT_QUOTES,"utf-8") ?>">
            </div>
            <div class="layui-input-inline">
                <button class="layui-btn" lay-submit>搜索</button>
            </div>
        </div>
    </form>
    <script>
        let xy = "<?php echo $xy;?>";
        let nj = "<?php echo $nj;?>";
        let school = "<?php echo $school?>";

        function renderForm() {
            layui.use(['form'], function () {
                var form = layui.form;
                form.render('select');
            });
        }

        function get_class() {
            if (nj !== "") nj = "20" + nj;
            $.ajax({
                url: "/getClass.php",
                data: {
                    nj: nj,
                    xy: xy,
                },
                success: function (resp) {
                    let data = resp;
                    $("#classList select").empty();
                    $("#classList select").append("<option value=''>专业班级</option>")
                    for (var i = 0; i < data.length; i ++) {
                        if (school === data[i].value)
                            $("#classList select").append("<option value='" + data[i].value + "' selected>" + data[i].value + "</option>")
                        else
                            $("#classList select").append("<option value='" + data[i].value + "'>" + data[i].value + "</option>")
                    }
                    renderForm();
                }
            });
        }
        get_class();

        layui.use(['form'], function(){
            var form = layui.form;

            form.on('select(xy-dropdown)', function(data){
                if (data.value !== "")
                    location.href = "<?php echo "ranklist.php?".($start ? "start=$start" : "").($scope?"&scope=$scope":"") . ($prefix?"&prefix=$prefix":""). ($nj?"&nj=$nj":""). "&xy="; ?>" + data.value;
                else
                    location.href = "<?php echo "ranklist.php?".($start ? "start=$start" : "").($scope?"&scope=$scope":"") . ($prefix?"&prefix=$prefix":""). ($nj?"&nj=$nj":""); ?>";
            })
            form.on('select(nj-dropdown)', function(data){
                if (data.value !== "")
                    location.href = "<?php echo "ranklist.php?".($start ? "start=$start" : "").($scope?"&scope=$scope":"") . ($prefix?"&prefix=$prefix":""). ($xy?"&xy=$xy":""). "&nj="; ?>" + data.value;
                else
                    location.href = "<?php echo "ranklist.php?".($start ? "start=$start" : "").($scope?"&scope=$scope":"") . ($prefix?"&prefix=$prefix":""). ($xy?"&xy=$xy":""); ?>";
            })
            form.on('select(school-dropdown)', function(data){
                if (data.value !== "")
                    location.href = "<?php echo "ranklist.php?".($start ? "start=$start" : "").($scope?"&scope=$scope":"") . ($prefix?"&prefix=$prefix":""). ($xy?"&xy=$xy":""). ($nj?"&nj=$nj":""). "&school="; ?>" + data.value;
                else
                    location.href = "<?php echo "ranklist.php?".($start ? "start=$start" : "").($scope?"&scope=$scope":"") . ($prefix?"&prefix=$prefix":""). ($nj?"&nj=$nj":""). ($xy?"&xy=$xy":""); ?>";
            })
        })

    </script>

<!--<form action="ranklist.php" class="ui form" method="get" role="form" style="margin-bottom: 30px; text-align: right;">-->
<!--    <th>按时间排名</th>-->
<!--    <a href="ranklist.php?scope=d">Day</a>-->
<!--    <a href="ranklist.php?scope=w">Week</a>-->
<!--    <a href="ranklist.php?scope=m">Month</a>-->
<!--    <a href="ranklist.php?scope=y">Year</a>-->
<!--    <br>-->
<!--    <th>按年级排名</th>-->
<!--    <a href="ranklist.php?prefix=2018">2018</a>-->
<!--    <a href="ranklist.php?prefix=2019">2019</a>-->
<!--    <a href="ranklist.php?prefix=2020">2020</a>-->
<!--    <a href="ranklist.php?prefix=2021">2021</a>-->
<!--</form>-->

	    <table class="ui very basic center aligned table" style="table-layout: fixed; ">
	        <thead>
	        <tr>
                <th style="width: 6.89%; "><?php echo $MSG_Number?></th>
                <th style="width: 20.69%; "><?php echo "学号"?></th>
                <th style="width: 26.43%; "><?php echo "专业班级"?></th>
                <th style="width: 11.49%;" ><?php echo "姓名"?></th>
                <th style="width: 11.49%; "><?php echo $MSG_SOVLED?></th>
                <th style="width: 11.49%; "><?php echo $MSG_SUBMIT?></th>
                <th style="width: 11.49%; "><?php echo $MSG_RATIO?></th>
                <th style="width: 11.49%; ">重复率</th>
	        </tr>
	        </thead>
	        <tbody>
          <?php
          foreach($view_rank as $row){
          echo "<tr>";
          foreach($row as $table_cell){
          echo "<td>";
          echo "\t".$table_cell;
          echo "</td>";
          }
          echo "</tr>";
          }
          ?>
	        </tbody>
	    </table>
    <br>

    <div style="margin-bottom: 30px; ">

        <?php
        if(!isset($start)) $start=0;
        if(!isset($prefix)) $prefix=0;
        $start=intval($start);
        $section=500;
        $end=$start+$section>$view_total?$view_total:$start+$section;
        $st=$start+1 > $view_total ? $view_total:$start+1;
        ?>
        <div style="text-align: center; ">
            <div class="ui pagination menu" style="box-shadow: none; ">
                <a class="<?php if($start==1) echo "disabled "; ?>icon item" href="<?php echo "ranklist.php?start=".($st-$section-1 < 0 ? 0:$st-$section-1).($scope?"&scope=$scope":"") . ($prefix?"&prefix=$prefix":""). ($xy?"&xy=$xy":""). ($school?"&school=$school":""). ($nj?"&nj=$nj":""); ?>" id="page_prev">
                    <i class="left chevron icon"></i>
                </a>
                <?php
                for ($i=$st;$i<$end;$i+= 50){
                    echo "<a class=\"".($st==$i?"active ":"")."item\" href=\"ranklist.php?start=".($i - 1) .($scope?"&scope=$scope":"") . ($prefix?"&prefix=$prefix":""). ($xy?"&xy=$xy":""). ($school?"&school=$school":""). ($nj?"&nj=$nj":"")."\"  >".$i.'-'.($i + 49)."</a>";
                }
                ?>
                <a class="<?php if($start>=$view_total) echo "disabled "; ?> icon item" href="<?php if($start<>$view_total) echo "ranklist.php?start=".($end).($scope?"&scope=$scope":"") . ($prefix?"&prefix=$prefix":""). ($xy?"&xy=$xy":""). ($school?"&school=$school":""). ($nj?"&nj=$nj":""); ?>"  id="page_next">
                    <i class="right chevron icon"></i>
                </a>
            </div>
        </div>

    </div>
<!--
        <div style="text-align: center; ">
	<div class="ui pagination menu" style="box-shadow: none; ">      
    <?php

    for($i = 0; $i < $view_total ; $i += $page_size) {
        echo "<a href='./ranklist.php?start=" . strval ( $i ).($scope?"&scope=$scope":"") . ($prefix?"&prefix=$prefix":"")  . "'>";
        $str= "<a class=\"icon item\" href='./ranklist.php?start=" . strval ( $i ).($scope?"&scope=$scope":"") . "'>";
        $str.= strval ( $i + 1 );
        $str.= "-";
        $str.= strval ( $i + $page_size );
        $str.= "</a>";
        echo $str;

    }

    ?>
	</div>
  </div>

</div>
</div>
-->
<?php include("template/$OJ_TEMPLATE/footer.php");?>
