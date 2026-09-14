<?php
require_once("discuss_func.inc.php");
require_once("include/memcache.php"); // 提供 mysql_query_cache（查询缓存）
$tid=intval($_REQUEST['tid']);
$cid = isset($_GET['cid']) ? intval($_GET['cid']) : 0;
$sql="SELECT t.`title`, `cid`, `pid`, `status`, `top_level` FROM `topic` t left join contest_problem cp on cp.problem_id=t.pid   WHERE `tid` = ? AND `status` <= 1";
$result=pdo_query($sql,$tid) ;
$rows_cnt = count($result) ;
if ($rows_cnt == 0) {
    http_response_code(404);
    $view_errors = "帖子不存在或已删除。";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit;
}
$row= $result[0];
if($row['cid']>0) $cid=$row['cid'];
if($row['pid']>0 && $row['cid'] >0 ) {
    $pid=pdo_query("select num from contest_problem where problem_id=? and contest_id=?",$row['pid'],$row['cid'])[0][0];
    $pid=$PID[$pid];
}else{
    $pid=$row['pid'];
}
$isadmin = isset($_SESSION[$OJ_NAME.'_'.'administrator']);
$meta = array('author_id'=>"", 'created'=>"", 'cnt'=>0);
$resm = mysql_query_cache("SELECT t.author_id, COUNT(r.rid) cnt, MIN(r.time) created FROM topic t LEFT JOIN reply r ON r.topic_id=t.tid WHERE t.tid=? GROUP BY t.tid", $tid);
if(!empty($resm)) $meta = $resm[0];

function oj_avatar_color($name){
    $palette = array("#2f6ee5","#18a058","#f0a020","#d03050","#8b5cf6","#0ea5e9","#f59e0b","#14b8a6","#ef4444","#6366f1");
    $h = hexdec(substr(md5($name),0,6));
    return $palette[$h % count($palette)];
}
function oj_initial($name){
    return strtoupper(mb_substr($name,0,1,"UTF-8"));
}
$acolor = oj_avatar_color($meta['author_id']);
$ainit = oj_initial($meta['author_id']);
?>

<div class="oj-card">
  <div class="oj-thread-actions">
    <div class="oj-breadcrumb">
      <a href="discuss.php<?php
        if ($row['pid']!=0 && $row['cid']!=null) echo "?pid=".$row['pid']."&cid=".$row['cid'];
        else if ($row['pid']!=0) echo"?pid=".$row['pid'];
        else if ($row['cid']!=null) echo"?cid=".$row['cid'];
      ?>">
        <?php if ($row['pid']!=0) echo "Problem $pid"; else echo "主版";?></a>
      <span class="oj-meta-dot">/</span> 帖子详情
    </div>
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
      <a class="oj-btn oj-btn-ghost oj-btn-sm" href="newpost.php<?php if ($cid) echo "?cid=$cid&pid=".$row['pid']; ?>">新建讨论</a>
      <?php if ($isadmin){
        $adminurl = "threadadmin.php?target=thread";
        ?>
        <span style="font-size: 13px; color: var(--oj-muted);">
          <?php
            if ($row['top_level'] == 0){
              echo "[ ".oj_admin_post_form($adminurl, array('tid'=>$tid,'action'=>'sticky','level'=>'3'), '置顶')." ] ";
              echo "[ ".oj_admin_post_form($adminurl, array('tid'=>$tid,'action'=>'sticky','level'=>'2'), '中置')." ] ";
              echo "[ ".oj_admin_post_form($adminurl, array('tid'=>$tid,'action'=>'sticky','level'=>'1'), '低置')." ] ";
            } else {
              echo "[ ".oj_admin_post_form($adminurl, array('tid'=>$tid,'action'=>'sticky','level'=>'0'), '取消置顶')." ] ";
            }
          ?>
          |
          <?php if ($row['status'] != 1) echo " [ ".oj_admin_post_form($adminurl, array('tid'=>$tid,'action'=>'lock'), '锁定')." ]"; else echo " [ ".oj_admin_post_form($adminurl, array('tid'=>$tid,'action'=>'resume'), '解锁')." ]"; ?>
          | [ <?php echo oj_admin_post_form($adminurl, array('tid'=>$tid,'action'=>'delete'), '删除', '删除后不可恢复'); ?> ]
        </span>
      <?php } ?>
    </div>
  </div>

  <div class="oj-thread-head">
    <h1><?php echo nl2br(htmlentities($row['title'],ENT_QUOTES,"UTF-8")); ?></h1>
    <div class="oj-thread-meta">
      <span class="oj-avatar" style="--c:<?php echo $acolor; ?>;"><?php echo $ainit; ?></span>
      <span><a href="userinfo.php?user=<?php echo $meta['author_id']; ?>"><?php echo $meta['author_id']; ?></a></span>
      <span class="oj-meta-dot">·</span>
      <span><?php echo $meta['created']; ?></span>
      <span class="oj-meta-dot">·</span>
      <span><?php echo $meta['cnt']; ?> 条回复</span>
    </div>
  </div>

  <?php
  $sql="SELECT `rid`, `author_id`, `time`, `content`, `status` FROM `reply` WHERE `topic_id` = ? AND `status` <=1 ORDER BY `rid` LIMIT 30";
  $result=pdo_query($sql,$tid) ;
  $reply_rows = count($result);
  $i=0;
  foreach ($result as $row){
    if(isset($_SESSION[$OJ_NAME.'_'.'user_id'])) $isuser = strtolower($row['author_id'])==strtolower($_SESSION[$OJ_NAME.'_'.'user_id']);
    else $isuser=false;
    $rc = oj_avatar_color($row['author_id']);
    $ri = oj_initial($row['author_id']);
    ?>
    <div class="oj-reply">
      <span class="oj-avatar oj-avatar-lg" style="--c:<?php echo $rc; ?>;"><?php echo $ri; ?></span>
      <div class="oj-reply-body">
        <div class="oj-reply-head">
          <span class="oj-reply-author"><a href="userinfo.php?user=<?php echo $row['author_id']; ?>"><?php echo $row['author_id']; ?></a></span>
          <span class="oj-reply-time"><?php echo $row['time']; ?></span>
          <span class="oj-reply-actions">
            <?php if (isset($_SESSION[$OJ_NAME.'_'.'user_id'])){ ?>
              <a href="javascript:void(0);" onclick="quote(<?php echo $row['rid'];?>);">引用</a>
            <?php } ?>
            <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){ ?>
              <?php echo oj_admin_post_form("threadadmin.php?target=reply", array('rid'=>$row['rid'],'tid'=>$tid,'action'=>($row['status']==0?'disable':'resume')), ($row['status']==0?'屏蔽':'恢复')); ?>
              <a href="javascript:void(0);" onclick="reply(<?php echo $row['rid'];?>);">回复</a>
            <?php } ?>
            <?php if ($isuser || $isadmin) echo oj_admin_post_form("threadadmin.php?target=reply", array('rid'=>$row['rid'],'tid'=>$tid,'action'=>'delete'), '删除'); ?>
          </span>
          <span class="oj-reply-no">#<?php echo $i+1; ?></span>
        </div>
        <div class="oj-reply-content" id="post<?php echo $row['rid'];?>">
          <?php if ($row['status'] == 0) echo nl2br(htmlentities($row['content'],ENT_QUOTES,"UTF-8"));
          else {
            if (!$isuser || $isadmin) echo "<div style=\"color:var(--oj-danger); font-style: italic;\">该回复已被管理员屏蔽。</div>";
            if ($isuser || $isadmin) echo nl2br(htmlentities($row['content'],ENT_QUOTES,"UTF-8"));
          }
          ?>
        </div>
      </div>
    </div>
    <?php
    $i++;
  }
  if($reply_rows==0){
    ?>
    <div class="oj-empty">
      <div class="icon">&#128172;</div>
      <div class="t">还没有回复</div>
      <div class="d">分享你的思路，或对楼主的提问作出回应。</div>
    </div>
    <?php
  }
  ?>

  <?php if (isset($_SESSION[$OJ_NAME.'_'.'user_id'])){?>
  <div class="oj-composer">
    <label for="replyContent">写回复</label>
    <form action="post.php?action=reply" method="post">
      <?php require_once('./include/set_post_key.php');?>
      <input type="hidden" name="tid" value="<?php echo $tid;?>" >
      <textarea id="replyContent" name="content" placeholder="友善发言，帮助他人也在帮助自己…"></textarea>
      <div class="foot"><button class="oj-btn oj-btn-primary" type="submit">发表回复</button></div>
    </form>
  </div>
  <?php }else{ ?>
  <div class="oj-composer">
    <div style="text-align:center; color: var(--oj-muted); font-size: 14px;">
      登录后即可参与回复 <a href="loginpage.php" style="color: var(--oj-p);">去登录</a>
    </div>
  </div>
  <?php } ?>
</div>

<script>
  function reply(rid){
    var origin=$("#post"+rid).text();
    origin="Reply to :"+origin+"\n----------------------\n";
    $("#replyContent").text(origin);
    $("#replyContent").focus();
  }
  function quote(rid){
    var origin=$("#post"+rid).text();
    $("#replyContent").val("> "+origin+"\n\n");
    $("#replyContent").focus();
  }
</script>

<?php require_once("template/$OJ_TEMPLATE/discuss.php")?>