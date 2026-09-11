<?php
require_once("discuss_func.inc.php");
if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
    echo "<div class=\"oj-card\"><div class=\"oj-empty\"><div class=\"icon\">&#128273;</div><div class=\"t\">登录后即可提问</div><div class=\"d\">分享问题或思路前，请先登录账号。</div><a class=\"oj-btn oj-btn-primary\" href=\"loginpage.php\">去登录</a></div></div>";
    require_once("template/$OJ_TEMPLATE/discuss.php");
    exit(0);
}
if(isset($_GET['pid']))
    $pid=intval($_GET['pid']);
else
    $pid="";
if(isset($_GET['cid'])){
    $cid=intval($_GET['cid']);
    if($pid>0){
        $pid=pdo_query("select num from contest_problem where problem_id=? and contest_id=?",$pid,$cid)[0][0];
        $pid=$PID[$pid];
    }
}else{
    $cid=0;
}
?>
<div class="oj-card oj-form">
  <h3>新的提问</h3>
  <div class="sub">
    正在发布到
    <?php if ($cid!=0){ ?>Contest <?php echo $cid; ?><?php } else { ?>主讨论版<?php } ?>
  </div>

  <form action="post.php?action=new" method="post">
    <?php require_once('./include/set_post_key.php');?>
    <input type="hidden" name="cid" value="<?php if (array_key_exists('cid',$_REQUEST)) echo intval($_REQUEST['cid']);?>">
    <label for="f_pid">题目 ID <span class="hint">选填，方便别人快速定位题目</span></label>
    <input type="text" id="f_pid" name="pid" value="<?php echo $pid;?>" placeholder="例如 1000">
    <label for="f_title">标题</label>
    <input type="text" id="f_title" name="title" placeholder="一句话概括你的问题或主题">
    <label for="f_content">内容</label>
    <textarea id="f_content" name="content" placeholder="描述你遇到的问题、尝试过的方法，或想分享的思路…"></textarea>
    <div class="foot">
      <a class="oj-btn oj-btn-ghost" href="discuss.php">取消</a>
      <button class="oj-btn oj-btn-primary" type="submit">发表提问</button>
    </div>
  </form>
</div>
<?php require_once("template/$OJ_TEMPLATE/discuss.php")?>