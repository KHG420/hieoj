<?php $show_title="$MSG_SOURCE - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>
.oj-cat { padding: 16px 0; }
.oj-cat h1 { font-size: 20px; color: #1f2d3d; margin: 0 0 16px; }
.oj-cat-card { background: #fff; border: 1px solid #e6ebf2; border-radius: 10px; box-shadow: 0 4px 14px rgba(15,23,42,.06); padding: 20px; margin-bottom: 16px; }
.oj-cat-head { font-size: 15px; font-weight: 600; color: #2f6ee5; margin-bottom: 10px; }
.oj-cat-list a { display: inline-block; margin: 3px 10px 3px 0; color: #1f2d3d; text-decoration: none; font-size: 14px; }
.oj-cat-list a:hover { color: #2f6ee5; text-decoration: underline; }
</style>
<div class="padding oj-cat">
  <h1><?php echo $MSG_SOURCE?></h1>
  <?php if(empty($title)){ ?>
    <div class="oj-cat-card">暂无分类数据。</div>
  <?php } else { ?>
    <?php for($i=0;$i<count($title);$i++){ ?>
      <div class="oj-cat-card">
        <div class="oj-cat-head"><?php echo htmlentities($title[$i],ENT_QUOTES,"UTF-8")?></div>
        <div class="oj-cat-list">
          <?php if(isset($category[$i])&&is_array($category[$i])) foreach($category[$i] as $c){ ?>
            <a href="problemset.php?search=<?php echo htmlentities(urlencode($c),ENT_QUOTES,"UTF-8")?>"><?php echo htmlentities($c,ENT_QUOTES,"UTF-8")?></a>
          <?php } ?>
        </div>
      </div>
    <?php } ?>
  <?php } ?>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
