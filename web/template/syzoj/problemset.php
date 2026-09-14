<?php $show_title="$MSG_PROBLEMS - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>

<style>
/* 自定义样式增强 */
.problemset-container {
  background-color: #f8f9fa;
  min-height: calc(100vh - 100px);
  padding: 20px 0;
}

.search-section {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
  margin-bottom: 25px;
}

.problems-table-container {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
  margin-bottom: 25px;
}

.problems-table {
  border-collapse: collapse;
  width: 100%;
}

.problems-table thead th {
  background-color: #f1f3f5;
  color: #495057;
  font-weight: 600;
  padding: 15px 12px;
  border-bottom: 2px solid #e9ecef;
}

.problems-table tbody tr {
  transition: background-color 0.2s ease;
  border-bottom: 1px solid #f1f3f5;
}

.problems-table tbody tr:hover {
  background-color: #f8f9fa;
}

.problems-table tbody td {
  padding: 15px 12px;
  vertical-align: middle;
}

.problem-title {
  font-weight: 500;
  color: #212529;
  transition: color 0.2s ease;
}

.problem-title:hover {
  color: #0d6efd;
  text-decoration: none;
}

.status.accepted {
  color: #198754;
}

.status.wrong_answer {
  color: #dc3545;
}

.status i.icon {
  color: #6c757d;
}

.tag-container {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.problem-tag {
  font-size: 0.75rem;
  padding: 4px 8px;
  border-radius: 4px;
  font-weight: 500;
}

.pagination-container {
  display: flex;
  justify-content: center;
  margin: 25px 0;
}

.pagination-menu {
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.pagination-menu .item {
  padding: 10px 15px;
  border-right: 1px solid rgba(34,36,38,.1);
}

.pagination-menu .item:last-child {
  border-right: none;
}

.pagination-menu .item.active {
  background-color: #0d6efd;
  color: white;
  font-weight: 600;
}

.control-panel {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 15px;
}

.search-forms {
  display: flex;
  gap: 15px;
  align-items: center;
  flex-wrap: wrap;
}

.search-box {
  position: relative;
  width: 280px;
}

.search-box .search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}

.search-box input {
  padding-left: 35px !important;
  border-radius: 6px;
  border: 1px solid #ced4da;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.search-box input:focus {
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.stats-cell {
  font-weight: 500;
}

.ac-rate-high {
  color: #198754;
}

.ac-rate-medium {
  color: #fd7e14;
}

.ac-rate-low {
  color: #dc3545;
}

.defunct-badge {
  font-size: 0.7rem;
  padding: 2px 6px;
  margin-left: 8px;
  vertical-align: middle;
}

@media (max-width: 768px) {
  .pagination-container .ui.pagination.menu {
    flex-wrap: wrap;
    max-width: 100%;
    justify-content: center;
  }
  .control-panel {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .search-forms {
    width: 100%;
  }
  
  .search-box {
    width: 100%;
  }
  
  .problems-table thead th:nth-child(1),
  .problems-table tbody td:nth-child(1) {
    display: none;
  }
}
</style>

<div class="problemset-container">
  <div class="ui container">
    <!-- 搜索和控制面板 -->
    <div class="search-section">
      <div class="control-panel">
        <div class="search-forms">
          <form action="" method="get" class="search-box">
              <i class="search icon search-icon"></i>
              <input id="problem-search" aria-label="搜索题目标题或标签" class="prompt" type="search" value="<?php echo htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : ''); ?>" placeholder="搜索题目标题或知识标签" name="search"><button class="ui primary button" type="submit">搜索</button>
          </form>

          <form action="problem.php" method="get" class="search-box" style="width: 150px;">
              <i class="search icon search-icon"></i>
              <input class="prompt" type="number" min="1" aria-label="题目编号" placeholder="题目编号" name="id" required><button class="ui basic button" type="submit">跳转</button>
          </form>
        </div>

        <div class="controls-right">
          <div class="ui toggle checkbox" id="show_tag" style="margin-right: 15px;">
            <style id="show_tag_style"></style>
            <script>
            // 检查localStorage，如果没有设置默认值为1
            if (localStorage.getItem('show_tag') === null) {
              localStorage.setItem('show_tag', '1');
            }
            if (localStorage.getItem('show_tag') === '1') {
              document.write('<input id="show-tags-input" type="checkbox" checked>');
              document.getElementById('show_tag_style').innerHTML = '.show_tag_controled { display: block; }';
            } else {
              document.write('<input id="show-tags-input" type="checkbox">');
              document.getElementById('show_tag_style').innerHTML = '.show_tag_controled { display: none; }';
            }
            </script>

            <script>
            $(function () {
              $('#show_tag').checkbox('setting', 'onChange', function () {
                let checked = $('#show_tag').checkbox('is checked');
                localStorage.setItem('show_tag', checked ? '1' : '0');
                if (checked) {
                  document.getElementById('show_tag_style').innerHTML = '.show_tag_controled { display: block; }';
                } else {
                  document.getElementById('show_tag_style').innerHTML = '.show_tag_controled { display: none; }';
                }
              });
            });
            </script>
            <label for="show-tags-input"><?php echo $MSG_SHOW_TAGS;?></label>
          </div>
          
          <a href="category.php" class="ui labeled icon basic button">
            <i class="tags icon"></i> 
            <?php echo $MSG_SHOW_ALL_TAGS;?>
          </a>
        </div>
      </div>
    </div>

    <!-- 分页器 (顶部) -->
    <?php
      if(!isset($page)) $page=1;
      $page=intval($page);
      $section=8;
      $start=$page>$section?$page-$section:1;
      $end=$page+$section>$view_total_page?$view_total_page:$page+$section;
      // 交互连贯：分页链接保留搜索关键字，避免翻页后丢失筛选条件
      $ps_extra="";
      if(isset($_GET['search']) && trim($_GET['search'])!="") $ps_extra="&search=".urlencode(trim($_GET['search']));
      if(isset($_GET['list']) && trim($_GET['list'])!="") $ps_extra="&list=".urlencode(trim($_GET['list']));
    ?>
    
    <?php if ($view_total_count > 0 && $view_total_page > 1) { ?><div class="pagination-container">
      <div class="ui pagination menu">
        <a class="<?php if($page==1) echo "disabled "; ?>icon item" href="<?php if($page<>1) echo "problemset.php?page=".($page-1).$ps_extra; ?>" id="page_prev" aria-label="上一页">  
          <i class="left chevron icon"></i>
        </a>
        <?php
          for ($i=$start;$i<=$end;$i++){
            echo "<a class=\"".($page==$i?"active ":"")."item\" href=\"problemset.php?page=".$i.$ps_extra."\">".$i."</a>";
          }
        ?>
        <a class="<?php if($page==$view_total_page) echo "disabled "; ?> icon item" href="<?php if($page<>$view_total_page) echo "problemset.php?page=".($page+1).$ps_extra; ?>" id="page_next" aria-label="下一页">
          <i class="right chevron icon"></i>
        </a>  
      </div>
    </div>

    <?php } ?>
    <p class="oj-result-summary">共 <?php echo $view_total_count; ?> 道题目<?php if ($view_total_count) echo " · 第 ".$page." / ".$view_total_page." 页"; ?><?php if (!empty($_GET['search'])) { ?> · <a href="problemset.php">清除搜索</a><?php } ?></p>
    <!-- 题目列表 -->
    <div class="problems-table-container">
      <table class="ui very basic table problems-table">
        <thead>
          <tr>
            <?php if (isset($_SESSION[$OJ_NAME.'_'.'user_id'])){?>
              <th class="center aligned"><?php echo $MSG_STATUS?></th>
            <?php } ?>
            <th class="center aligned"><?php echo $MSG_PROBLEM_ID?></th>
            <th class="left aligned"><?php echo $MSG_TITLE?></th>
            <th class="center aligned"><?php echo $MSG_SOVLED?></th>
            <th class="center aligned"><?php echo $MSG_SUBMIT?></th>
            <th class="center aligned">正确率</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$result) { ?><tr><td colspan="<?php echo isset($_SESSION[$OJ_NAME.'_user_id']) ? 6 : 5; ?>" class="oj-empty">未找到匹配题目。请尝试其他关键词，或<a href="problemset.php">清除搜索</a>。</td></tr><?php } ?>
          <?php
            $color=array("blue","teal","orange","pink","olive","red","yellow","green","purple");
            $tcolor=0;
            $i=0;
            foreach ($result as $row){
              echo "<tr>";
              
              if (isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
                if (isset($sub_arr[$row['problem_id']])){
                  if (isset($acc_arr[$row['problem_id']])) 
                    echo "<td class='center aligned'><span class=\"status accepted\"><i class=\"checkmark icon\"></i></span></td>";
                  else 
                    echo "<td class='center aligned'><span class=\"status wrong_answer\"><i class=\"remove icon\"></i></span></td>";
                } else {
                  echo "<td class='center aligned'><span class=\"status\"><i class=\"icon\"></i></span></td>";
                }
              }

              echo "<td class='center aligned'><b>".$row['problem_id']."</b></td>";
              echo "<td class='left aligned'>";
              echo "<a class='problem-title' href=\"problem.php?id=".$row['problem_id']."\">";
              echo $row['title'];
              echo "</a>";
              
              if($row['defunct']=='Y')
                echo "<span class=\"ui tiny red label defunct-badge\">未公开</span>";

              echo "<div class=\"show_tag_controled tag-container\">";
              $category=array();
              $cate=explode(" ",$row['source']);
              foreach($cate as $cat){
                array_push($category,trim($cat));	
              }
              $tcolor=0;
              foreach($category as $cat){
                if(trim($cat)=="") continue;
                $hash_num=hexdec(substr(md5($cat),0,15));
                $label_theme=$color[$tcolor%count($color)];
                $tcolor++;
                echo "<a href=\"problemset.php?search=".htmlentities(urlencode($cat),ENT_QUOTES,'UTF-8')."\" class=\"ui mini ".$label_theme." label problem-tag\">";
                echo htmlentities($cat,ENT_QUOTES,'UTF-8');
                echo "</a>";
              }
              echo "</div>";
              echo "</td>";
              
              echo "<td class='center aligned stats-cell'><a href=\"status.php?problem_id=".$row['problem_id']."&jresult=4\">".$row['accepted']."</a></td>";
              echo "<td class='center aligned stats-cell'><a href='status.php?problem_id=".$row['problem_id']."'>".$row['submit']."</a></td>";
              
              if ($row['submit'] == 0) {
                echo "<td class='center aligned'>0.000%</td>";
              } else {
                $tt = sprintf ( "%.03lf%%", 100 * $row['accepted'] / $row['submit'] );
                $rate_class = "";
                $rate = 100 * $row['accepted'] / $row['submit'];
                if ($rate >= 70) $rate_class = "ac-rate-high";
                else if ($rate >= 30) $rate_class = "ac-rate-medium";
                else $rate_class = "ac-rate-low";
                echo "<td class='center aligned stats-cell $rate_class'>".$tt."</td>";
              }
              echo "</tr>";
            }
          ?>
        </tbody>
      </table>
    </div>

    <!-- 分页器 (底部) -->
    <?php if ($view_total_count > 0 && $view_total_page > 1) { ?><div class="pagination-container">
      <div class="ui pagination menu">
        <a class="<?php if($page==1) echo "disabled "; ?>icon item" href="<?php if($page<>1) echo "problemset.php?page=".($page-1).$ps_extra; ?>" aria-label="上一页">  
          <i class="left chevron icon"></i>
        </a>
        <?php
          for ($i=$start;$i<=$end;$i++){
            echo "<a class=\"".($page==$i?"active ":"")."item\" href=\"problemset.php?page=".$i.$ps_extra."\">".$i."</a>";
          }
        ?>
        <a class="<?php if($page==$view_total_page) echo "disabled "; ?> icon item" href="<?php if($page<>$view_total_page) echo "problemset.php?page=".($page+1).$ps_extra; ?>" aria-label="下一页">
          <i class="right chevron icon"></i>
        </a>  
      </div>
    </div><?php } ?>
  </div>
</div>



<?php include("template/$OJ_TEMPLATE/footer.php");?>
