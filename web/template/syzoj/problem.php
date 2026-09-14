<?php
          if($pr_flag){
            $show_title="P$id - ".$row['title']." - $OJ_NAME";
          }else{
            $id=$row['problem_id'];
            $show_title="问题 ".$PID[$pid].": ".$row['title']." - $OJ_NAME";
          }
?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>
/* 题目页（参考 ProblemsPage 风格） */
.oj-pd { max-width: 1200px; margin: 0 auto; }
.oj-card { background: #fff; border: none; border-radius: 10px; box-shadow: 0 4px 14px rgba(15,23,42,.06); }

.oj-pd-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; padding: 20px 24px; margin-bottom: 20px; }
.oj-pd-title { margin: 0; font-size: 24px; color: #1f2d3d; display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; line-height: 1.4; }
.oj-pd-id { font-size: 20px; color: #5f6d85; font-family: Consolas, Monaco, monospace; font-weight: 600; }
.oj-pd-tag-defunct { background: #d03050; color: #fff; border-radius: 6px; padding: 2px 10px; font-size: 12px; font-weight: 600; }
.oj-pd-orig a { font-size: 13px; color: #2f6ee5; margin-right: 12px; }
.oj-pd-stats { display: flex; gap: 26px; font-size: 14px; color: #5f6d85; flex-wrap: wrap; }
.oj-pd-stat { display: flex; align-items: center; gap: 6px; }
.oj-pd-stat b { color: #1f2d3d; font-weight: 600; }
.oj-pd-stat small { color: #8a93a6; }

.oj-pd-grid { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 1fr); gap: 20px; align-items: start; }
.oj-pd-main .oj-card { padding: 24px; margin-bottom: 20px; }
.oj-pd-section-title { font-size: 18px; font-weight: 600; color: #1f2d3d; margin: 0 0 12px; border-left: 4px solid #2f6ee5; padding-left: 12px; line-height: 1.2; }
.oj-pd-content { font-size: 15px; color: #2c3e50; line-height: 1.75; word-break: break-word; }
.oj-pd-content img { max-width: 100%; }

.oj-sample { border: 1px solid #dcdfe6; border-radius: 6px; overflow: hidden; margin-bottom: 10px; background: #fff; }
.oj-sample-head { background: #f5f7fa; padding: 6px 12px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #dcdfe6; font-size: 13px; font-weight: 600; color: #606266; }
.oj-sample-head .copy { font-size: 12px; color: #4d4d4d; background: #fff; border: 1px solid #dcdfe6; padding: 2px 10px; border-radius: 4px; cursor: pointer; }
.oj-sample pre { margin: 0; padding: 12px; font-family: Consolas, Monaco, "Courier New", monospace; font-size: 14px; line-height: 1.5; color: #1f2d3d; white-space: pre-wrap; word-break: break-all; background: #fff; }

.oj-pd-side .oj-card { padding: 16px; margin-bottom: 20px; }
.oj-info-row { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-bottom: 10px; font-size: 14px; line-height: 1.6; }
.oj-info-row .l { color: #5f6d85; flex-shrink: 0; }
.oj-info-row .v { color: #1f2d3d; text-align: right; font-weight: 500; }
.oj-info-row .v a { color: #2f6ee5; }
.oj-tags { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
.oj-tag { background: #eef4ff; color: #2f6ee5; border-radius: 6px; padding: 2px 10px; font-size: 13px; text-decoration: none; }
.oj-tag:hover { background: #dbe8ff; }

.oj-pd-btns { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
.oj-btn { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
.oj-btn-primary { background: #2f6ee5; color: #fff; box-shadow: 0 4px 14px rgba(47,110,229,.3); }
.oj-btn-primary:hover { background: #1e56c6; color: #fff; }
.oj-btn-ghost { background: #fff; color: #3a4759; border: 1px solid #d7dee8; }
.oj-btn-ghost:hover { border-color: #2f6ee5; color: #2f6ee5; }
.oj-btn-green { background: #18a058; color: #fff; }
.oj-btn-green:hover { color: #fff; opacity: .9; }
.oj-btn-orange { background: #f0a020; color: #fff; }
.oj-btn-orange:hover { color: #fff; opacity: .9; }
.oj-btn-red { background: #d03050; color: #fff; }
.oj-btn-red:hover { color: #fff; opacity: .9; }

@media (max-width: 900px) {
  .oj-pd-grid { grid-template-columns: 1fr; }
  .oj-pd-header { flex-direction: column; align-items: flex-start; }
}

.oj-side-title { font-size: 16px; font-weight: 600; color: #1f2d3d; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #e6ebf2; }
.oj-rel-list { display: flex; flex-direction: column; gap: 8px; }
.oj-rel-item { font-size: 14px; line-height: 1.5; }
.oj-rel-item a { color: #2f6ee5; }
.oj-rel-item a:hover { text-decoration: underline; }
.oj-empty { text-align: center; color: #8a93a6; padding: 16px 0; font-size: 14px; }
.oj-btn-sm { padding: 6px 12px; font-size: 13px; }
/* Compact problem sidebar: one primary action, grouped secondary links. */
.oj-pd-side .oj-card { padding:16px; margin-bottom:14px; }
.oj-pd-side .oj-info-row { font-size:13px; line-height:1.5; margin-bottom:8px; }
.oj-pd-side .oj-info-row .v { min-width:0; overflow-wrap:anywhere; }
.oj-pd-side .oj-pd-btns { margin-top:14px; }
.oj-pd-side .oj-btn-primary { min-height:36px; padding:8px 12px; font-size:13px; line-height:20px; border-radius:6px; }
.oj-side-actions { display:flex; flex-wrap:wrap; gap:4px 14px; margin-top:10px; }
.oj-side-actions a { display:inline-flex; align-items:center; min-height:30px; font-size:13px; line-height:20px; color:#245bbd; }
.oj-side-actions a:hover,.oj-side-heading a:hover { text-decoration:underline; text-underline-offset:3px; }
.oj-pd-side .oj-side-note { margin:10px 0 0; font-size:12px; line-height:1.7; color:#596980; }
.oj-side-admin { border-top:1px solid #e2e8f0; padding-top:8px; }
.oj-side-admin a { color:#596980; }
.oj-pd-side .oj-side-title { margin:0 0 10px; padding:0; border:0; font-size:14px; line-height:20px; }
.oj-pd-side .oj-tags { justify-content:flex-start; gap:6px; }
.oj-pd-side .oj-tag { padding:2px 7px; font-size:12px; line-height:18px; border-radius:4px; overflow-wrap:anywhere; max-width:100%; }
.oj-side-heading { display:flex; justify-content:space-between; align-items:baseline; gap:10px; }
.oj-side-heading a { color:#245bbd; font-size:12px; }
body.oj-desktop .oj-pd-side .oj-empty { padding:8px 0 !important; font-size:13px; }
.oj-pd-side .oj-rel-item { font-size:13px; overflow-wrap:anywhere; }
</style>
<script src="<?php echo $OJ_CDN_URL.$path_fix."template/$OJ_TEMPLATE/"?>clipboard.min.js"></script>

<?php
  $ratio = ($row['submit']>0) ? round($row['accepted']/$row['submit']*100, 1) : 0;
  $sinput=str_replace("<","&lt;",$row['sample_input']);
  $sinput=str_replace(">","&gt;",$sinput);
  $soutput=str_replace("<","&lt;",$row['sample_output']);
  $soutput=str_replace(">","&gt;",$soutput);
?>
<div class="oj-pd">
  <!-- 头部卡片：标题 + 元信息 -->
  <div class="oj-card oj-pd-header">
    <div>
      <h1 class="oj-pd-title">
        <span class="oj-pd-id"><?php echo $pr_flag ? $id : $PID[$pid]; ?></span>
        <?php echo $row['title']; ?>
        <?php if($row['defunct']=="Y") echo '<span class="oj-pd-tag-defunct">未公开</span>'; ?>
      </h1>
      <div class="oj-pd-orig">
        <?php
        if ( (isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'."p".$row['problem_id']])) && $row['spj'] > 1  ) {
            $sql = "SELECT to_id,source,cf_id FROM `spj` WHERE problem_id=?";
            $res=pdo_query($sql,$id);
            $row1 = $res[0];
            if (strcmp($row1['source'], "acwing") == 0) {
                echo "<a target=\"_blank\" href='https://www.acwing.com/problem/content/description/".$row1['to_id']."/'>原题链接</a>";
            } else if (strcmp($row1['source'], "csg") == 0) {
                echo "<a target=\"_blank\" href='https://cpc.csgrandeur.cn/csgoj/problemset/problem?pid=".$row1['to_id']."'>原题链接</a>";
            } else if (strcmp($row1['source'], "codeforces") == 0) {
                echo "<a target=\"_blank\" href='https://codeforces.com/problemset/problem/".$row1['to_id']."/".$row1['cf_id']."'>原题链接</a>";
            }
        }
        ?>
      </div>
    </div>
    <div class="oj-pd-stats">
      <div class="oj-pd-stat"><span>时间限制</span><b><?php echo $row['time_limit']; ?> S</b></div>
      <div class="oj-pd-stat"><span>内存限制</span><b><?php echo $row['memory_limit']; ?> MB</b></div>
      <div class="oj-pd-stat"><span>通过率</span><b><?php echo $ratio; ?>%</b><small>(<?php echo $row['accepted']; ?> / <?php echo $row['submit']; ?>)</small></div>
    </div>
  </div>

  <div class="oj-pd-grid">
    <!-- 左侧：题目内容 -->
    <div class="oj-pd-main">
      <div class="oj-card">
        <div class="oj-pd-section-title">题目描述</div>
        <div class="oj-pd-content"><?php echo $row['description']; ?></div>
      </div>

      <?php if($row['input']){ ?>
      <div class="oj-card">
        <div class="oj-pd-section-title">输入格式</div>
        <div class="oj-pd-content"><?php echo $row['input']; ?></div>
      </div>
      <?php } ?>

      <?php if($row['output']){ ?>
      <div class="oj-card">
        <div class="oj-pd-section-title">输出格式</div>
        <div class="oj-pd-content"><?php echo $row['output']; ?></div>
      </div>
      <?php } ?>

      <?php if(strlen($sinput) || strlen($soutput)){ ?>
      <div class="oj-card">
        <div class="oj-pd-section-title">输入输出样例</div>
        <?php if(strlen($sinput)){ ?>
        <div class="oj-sample">
          <div class="oj-sample-head"><span>输入</span><span class="copy" id="copyin" data-clipboard-text="<?php echo ($sinput); ?>">复制</span></div>
          <pre><code class="lang-plain"><?php echo ($sinput); ?></code></pre>
        </div>
        <?php } ?>
        <?php if(strlen($soutput)){ ?>
        <div class="oj-sample">
          <div class="oj-sample-head"><span>输出</span><span class="copy" id="copyout" data-clipboard-text="<?php echo ($soutput); ?>">复制</span></div>
          <pre><code class="lang-plain"><?php echo ($soutput); ?></code></pre>
        </div>
        <?php } ?>
      </div>
      <?php } ?>

      <?php if($row['hint']){ ?>
      <div class="oj-card">
        <div class="oj-pd-section-title">数据范围与提示</div>
        <div class="oj-pd-content"><?php echo $row['hint']; ?></div>
      </div>
      <?php } ?>

      <?php if($row['source']){
        $cats=explode(" ",$row['source']);
      ?>
      <div class="oj-card">
        <div class="oj-pd-section-title">来源</div>
        <div class="oj-tags" style="justify-content: flex-start;">
          <?php foreach($cats as $cat){ if(trim($cat)=="") continue; ?>
            <a class="oj-tag" href="<?php echo "problemset.php?search=".htmlentities($cat,ENT_QUOTES,'utf-8') ?>"><?php echo htmlentities($cat,ENT_QUOTES,'utf-8'); ?></a>
          <?php } ?>
        </div>
      </div>
      <?php } ?>
    </div>

    <!-- 右侧：信息 + 操作 -->
    <div class="oj-pd-side">
      <div class="oj-card">
        <div class="oj-info-row"><span class="l">上传者</span><span class="v"><span id="creator"></span></span></div>
        <div class="oj-info-row"><span class="l">题目类型</span><span class="v">传统</span></div>
        <div class="oj-info-row"><span class="l">评测方式</span><span class="v"><?php if($row['spj']) echo "Special Judge"; else echo "文本比较"; ?></span></div>
        <div class="oj-info-row"><span class="l">提交 / 通过</span><span class="v"><?php echo $row['submit']; ?> / <?php echo $row['accepted']; ?></span></div>

        <div class="oj-pd-btns">
          <a class="oj-btn oj-btn-primary" href="<?php echo $pr_flag ? "submitpage.php?id=$id" : "submitpage.php?cid=$cid&pid=$pid&langmask=$langmask"; ?>">提交代码</a>
        </div>
        <nav class="oj-side-actions" aria-label="题目操作">
          <a href="<?php echo $pr_flag ? "status.php?problem_id=$id" : "status.php?problem_id=$PID[$pid]&cid=$cid"; ?>"><i class="file icon" aria-hidden="true"></i>提交记录</a>
          <?php if($pr_flag){ ?>
          <a href="problemstatus.php?id=<?php echo $id; ?>"><i class="signal icon" aria-hidden="true"></i>统计</a>
          <a href="discuss.php?pid=<?php echo $id; ?>"><i class="clipboard icon" aria-hidden="true"></i><?php echo $MSG_BBS; ?></a>
          <?php }else{ ?>
          <a href="contest.php?cid=<?php echo $cid; ?>"><i class="arrow left icon" aria-hidden="true"></i>返回比赛</a>
          <?php } ?>
          <?php if($pr_flag && !isset($OJ_ON_SITE_CONTEST_ID)){ ?>
          <a href="solutions.php?problem_id=<?php echo intval($id); ?>"><i class="book icon" aria-hidden="true"></i>查看 / 提交题解</a>
          <?php } ?>
        </nav>
        <?php if($pr_flag && !isset($OJ_ON_SITE_CONTEST_ID)){ ?>
        <p class="oj-side-note">通过本题可免费查看并提交题解；未通过可花 5 金币永久解锁本题全部题解；题解审核通过奖励 10 金币。</p>
        <?php } ?>

        <?php
          if ( isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'."p".$row['problem_id']])  ) {
            require_once("include/set_get_key.php");
        ?>
        <div class="oj-side-actions oj-side-admin">
          <a href="admin/problem_edit.php?id=<?php echo $id?>&getkey=<?php echo $_SESSION[$OJ_NAME.'_'.'getkey']?>">编辑题目</a>
          <a href="javascript:phpfm(<?php echo $row['problem_id'];?>)">测试数据</a>
        </div>
        <?php } ?>
      </div>

      <?php if($row['source']){ ?>
      <div class="oj-card">
        <div class="oj-side-title">标签</div>
        <div class="oj-tags">
          <?php $cats2=explode(" ",$row['source']); foreach($cats2 as $cat){ if(trim($cat)=="") continue; ?>
          <a class="oj-tag" href="<?php echo "problemset.php?search=".htmlentities($cat,ENT_QUOTES,'utf-8'); ?>"><?php echo htmlentities($cat,ENT_QUOTES,'utf-8'); ?></a>
          <?php } ?>
        </div>
      </div>
      <?php } ?>

      <!-- 相关讨论 -->
      <div class="oj-card">
        <div class="oj-side-heading"><div class="oj-side-title">相关讨论</div><a href="discuss.php?pid=<?php echo $id; ?>">进入讨论版</a></div>
        <?php
          $rel_topics=array();
          $sql="SELECT t.tid,t.title,t.author_id,MAX(r.time) last FROM topic t LEFT JOIN reply r ON r.topic_id=t.tid WHERE t.pid=? AND t.status!=2 GROUP BY t.tid ORDER BY t.tid DESC LIMIT 5";
          $res=pdo_query($sql,$id);
          if(!empty($res)) $rel_topics=$res;
        ?>
        <?php if(count($rel_topics)>0){ ?>
          <div class="oj-rel-list">
            <?php foreach($rel_topics as $t){ ?>
              <div class="oj-rel-item"><a href="thread.php?tid=<?php echo intval($t['tid']); ?>"><?php echo htmlentities($t['title'],ENT_QUOTES,"UTF-8"); ?></a></div>
            <?php } ?>
          </div>
        <?php }else{ ?>
          <div class="oj-empty" style="padding: 6px 0;">暂无讨论</div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

  <script>
  function phpfm(pid){
    $.post("admin/phpfm.php",{'frame':3,'pid':pid,'pass':''},function(data,status){
      if(status=="success"){
        document.location.href="admin/phpfm.php?frame=3&pid="+pid;
      }
    });
  }

  $(document).ready(function(){
    $("#creator").load("problem-ajax.php?pid=<?php echo $id?>");
  });
  </script>

  <script>
    var clipboardin=new Clipboard(copyin);
    clipboardin.on('success', function(e){
      $("#copyin").text("复制成功!");
          setTimeout(function () {$("#copyin").text("复制"); }, 1500);
    });
    clipboardin.on('error', function(e){
      $("#copyin").text("复制失败!");
          setTimeout(function () {$("#copyin").text("复制"); }, 1500);
    });

    var clipboardout=new Clipboard(copyout);
    clipboardout.on('success', function(e){
      $("#copyout").text("复制成功!");
          setTimeout(function () {$("#copyout").text("复制"); }, 1500);
    });
    clipboardout.on('error', function(e){
      $("#copyout").text("复制失败!");
          setTimeout(function () {$("#copyout").text("复制"); }, 1500);
    });
  </script>
<?php include("template/$OJ_TEMPLATE/footer.php");?>