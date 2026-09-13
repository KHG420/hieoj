<?php $show_title="$MSG_RANKLIST - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<?php
// 安全修复：回显参数统一转义
$xy_e = htmlentities($xy, ENT_QUOTES, "UTF-8");
$nj_e = htmlentities($nj, ENT_QUOTES, "UTF-8");
$school_e = htmlentities($school, ENT_QUOTES, "UTF-8");
$prefix_e = htmlentities($prefix, ENT_QUOTES, "UTF-8");
?>
<style>
.oj-rk { max-width: 1200px; margin: 0 auto; padding: 16px 0; }
.oj-card { background: #fff; border: 1px solid #e6ebf2; border-radius: 10px; box-shadow: 0 4px 14px rgba(15,23,42,.06); padding: 20px; margin-bottom: 20px; }
.oj-filter { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
.oj-filter .f label { display: block; font-size: 13px; color: #5f6d85; margin-bottom: 4px; }
.oj-filter select, .oj-filter input { padding: 8px 10px; border: 1px solid #d7dee8; border-radius: 6px; font-size: 14px; background: #fff; }
.oj-filter .btn { padding: 9px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; background: #2f6ee5; color: #fff; }
.oj-filter .btn:hover { background: #1e56c6; }
.oj-table-wrap { overflow-x: auto; }
.oj-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.oj-table th { background: #f8fafc; color: #495057; font-weight: 600; padding: 10px 12px; border-bottom: 2px solid #dee2e6; white-space: nowrap; text-align: center; }
.oj-table td { padding: 10px 12px; border-bottom: 1px solid #e9ecef; text-align: center; color: #1f2d3d; }
.oj-table tr:hover td { background: #f6f9ff; }
.oj-empty { text-align: center; color: #8a93a6; padding: 24px 0; }
.oj-pages { text-align: center; margin: 16px 0 24px; }
.oj-pages a { display: inline-block; padding: 6px 12px; border: 1px solid #e6ebf2; border-radius: 6px; color: #2f6ee5; margin: 0 3px; text-decoration: none; font-size: 14px; }
.oj-pages a.active { background: #2f6ee5; color: #fff; border-color: #2f6ee5; }
.oj-pages a.disabled { color: #b8c2d0; pointer-events: none; }
</style>
<div class="oj-rk">
  <div class="oj-card">
    <form class="oj-filter" action="ranklist.php" method="get">
      <div class="f">
        <label for="rank-xy">学院</label>
        <select id="rank-xy" name="xy" onchange="this.form.submit()">
          <option value="">全部</option>
          <?php foreach ($xueYuan as $rows) { ?>
            <option value="<?php echo htmlentities($rows[0],ENT_QUOTES,"UTF-8")?>" <?php if($xy==$rows[0]) echo "selected"; ?>><?php echo htmlentities($rows[0],ENT_QUOTES,"UTF-8")?></option>
          <?php } ?>
        </select>
      </div>
      <div class="f">
        <label for="rank-nj">年级</label>
        <select id="rank-nj" name="nj" onchange="this.form.submit()">
          <option value="">全部</option>
          <?php foreach ($nianJi as $rows) { ?>
            <option value="<?php echo htmlentities($rows,ENT_QUOTES,"UTF-8")?>" <?php if($nj==strval($rows)) echo "selected"; ?>>20<?php echo htmlentities($rows,ENT_QUOTES,"UTF-8")?></option>
          <?php } ?>
        </select>
      </div>
      <div class="f">
        <label for="oj_class_sel">专业班级</label>
        <select name="school" id="oj_class_sel" onchange="this.form.submit()">
          <option value="">全部</option>
          <?php if($school!="" && $school_e!==""){ ?><option value="<?php echo $school_e?>" selected><?php echo $school_e?></option><?php } ?>
        </select>
      </div>
      <div class="f">
        <label for="rank-prefix">姓名 / 学号</label>
        <input type="text" id="rank-prefix" name="prefix" placeholder="关键字" value="<?php echo $prefix_e?>">
      </div>
      <div class="f"><button class="btn" type="submit">搜索</button></div>
    </form>
  </div>

  <div class="oj-card">
    <div class="oj-table-wrap">
      <table class="oj-table">
        <thead>
        <tr>
          <th><?php echo $MSG_Number?></th>
          <th>学号</th>
          <th>专业班级</th>
          <th>姓名</th>
          <th><?php echo $MSG_SOVLED?></th>
          <th><?php echo $MSG_SUBMIT?></th>
          <th><?php echo $MSG_RATIO?></th>
          <th>重复率</th>
        </tr>
        </thead>
        <tbody>
        <?php
        if(empty($view_rank)){
          echo '<tr><td colspan="8" class="oj-empty">暂无数据</td></tr>';
        } else {
          foreach($view_rank as $row){
            echo "<tr>";
            $ci = 0;
            foreach($row as $table_cell){
              echo "<td>";
              if($ci==0 || $ci==6 || $ci==7){ echo $table_cell; }
              else { echo $table_cell; }
              echo "</td>";
              $ci++;
            }
            echo "</tr>";
          }
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="oj-pages">
    <?php
    if(!isset($start)) $start=0;
    if(!isset($prefix)) $prefix='';
    $start=intval($start);
    $section=500;
    $end=$start+$section>$view_total?$view_total:$start+$section;
    $st=$start+1 > $view_total ? $view_total:$start+1;
    $qs = ($prefix?"&prefix=".urlencode($prefix):"").($xy?"&xy=".urlencode($xy):"").($school?"&school=".urlencode($school):"").($nj?"&nj=".urlencode($nj):"");
    ?>
    <a class="<?php if($start<=0) echo "disabled "; ?>" href="<?php echo "ranklist.php?start=".($st-$section-1 < 0 ? 0:$st-$section-1).$qs; ?>" id="page_prev">&laquo; 上一页</a>
    <?php
    for ($i=$st;$i<$end;$i+= 50){
      echo "<a class=\"".($st==$i?"active ":"")."\" href=\"ranklist.php?start=".($i - 1).$qs."\" >".$i.'-'.($i + 49)."</a>";
    }
    ?>
    <a class="<?php if($start>=$view_total) echo "disabled "; ?>" href="<?php echo "ranklist.php?start=".($end).$qs; ?>" id="page_next">下一页 &raquo;</a>
  </div>
</div>
<script>
$(function(){
  var xy = <?php echo json_encode($xy)?>, nj = <?php echo json_encode($nj)?>, school = <?php echo json_encode($school)?>;
  function loadClass(){
    var qnj = nj; if(qnj!=="") qnj = "20"+qnj;
    $.ajax({ url: "getClass.php", data: { nj: qnj, xy: xy }, dataType: "json",
      success: function(data){
        var sel = $("#oj_class_sel");
        sel.empty();
        sel.append("<option value=''>全部</option>");
        if(data && data.length){
          for (var i=0;i<data.length;i++){
            var v = data[i].value !== undefined ? data[i].value : data[i];
            if(v===null || v===undefined) continue;
            sel.append("<option value='"+$("<div>").text(v).html()+"'"+(v===school?" selected":"")+">"+$("<div>").text(v).html()+"</option>");
          }
        }
      }
    });
  }
  loadClass();
});
</script>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
