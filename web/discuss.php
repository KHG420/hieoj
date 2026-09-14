<?php
require_once("discuss_func.inc.php");
require_once("include/memcache.php"); // 提供 mysql_query_cache（查询缓存）
$parm="";
if(isset($_GET['pid'])){
    $pid=intval($_GET['pid']);
    $parm="pid=".$pid;
}else{
    $pid=0;
}
if(isset($_GET['cid'])){
    $cid=intval($_GET['cid']);
}else{
    $cid=0;
}
$parm.="&cid=".$cid;
$prob_exist = problem_exist($pid, $cid);

// 搜索关键字 / 排序 / 分页
$keyword = "";
if(isset($_GET['keyword']) && trim($_GET['keyword'])!=""){
    $keyword = trim($_GET['keyword']);
}
$sort = (isset($_GET['sort']) && $_GET['sort']=="hot") ? "hot" : "latest";
$page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$page_size = 30;
$offset = ($page-1)*$page_size;

// 查询条件
$where = "t.`status`!=2 AND (`cid`=0 OR `top_level`=3)";
if (isset($_REQUEST['cid']) && $_REQUEST['cid']!=''){
    $cid=intval($_REQUEST['cid']);
    $where = "t.`status`!=2 AND (`cid`='".$cid."' OR `top_level`=3)";
}
if (isset($_REQUEST['pid']) && $_REQUEST['pid']!=''){
    $pid_f=intval($_REQUEST['pid']);
    $where .= " AND (`pid`='".$pid_f."' OR `top_level`>=2)";
    $level="";
}else{
    $level=" - ( `top_level` = 1 )";
}
$discuss_params = array();
if($keyword!=""){
    // 安全修复：关键字改用绑定参数，并转义 LIKE 通配符
    $kw = str_replace(array('\\','%','_'), array('\\\\','\\%','\\_'), $keyword);
    $where .= " AND (t.`title` LIKE ? OR t.`author_id` LIKE ?";
    $discuss_params[] = "%".$kw."%";
    $discuss_params[] = "%".$kw."%";
    if (ctype_digit($keyword)) {
        $where .= " OR t.`pid`=?";
        $discuss_params[] = intval($keyword);
    }
    $where .= ")";
}

// 主题列表
if(isset($_REQUEST['cid']) && $_REQUEST['cid']!=''){
    $sql = "SELECT `tid`, t.`title`, `top_level`, `t`.`status`, `cid`, `pid`, CONVERT(MIN(`r`.`time`),DATE) `posttime`,
	MAX(`r`.`time`) `lastupdate`, `t`.`author_id`, COUNT(`rid`) `count`,cp.num
	FROM `topic` t left join `reply` r on t.tid=r.topic_id left join contest_problem cp on t.pid=cp.problem_id and cp.contest_id=".intval($_REQUEST['cid'])."
	WHERE ".$where;
}else{
    $sql = "SELECT `tid`, `title`, `top_level`, `t`.`status`, `cid`, `pid`, CONVERT(MIN(`r`.`time`),DATE) `posttime`,
	MAX(`r`.`time`) `lastupdate`, `t`.`author_id`, COUNT(`rid`) `count`, users.nick
	FROM `topic` t left join `reply` r on t.tid=r.topic_id left join `users` users on users.user_id=t.author_id 
	WHERE ".$where;
}
$orderby = ($sort=="hot") ? "COUNT(`rid`) DESC" : "MAX(`r`.`time`) DESC";
$sql.=" GROUP BY t.tid ORDER BY `top_level`$level DESC, ".$orderby." LIMIT ".$offset.", ".$page_size;
$result = pdo_query($sql, ...$discuss_params);
$rows_cnt = count($result);
$isadmin = isset($_SESSION[$OJ_NAME.'_'.'administrator']);

// 统计
$total_cnt = 0;
$res2 = pdo_query("SELECT COUNT(DISTINCT t.tid) FROM `topic` t WHERE ".$where, ...$discuss_params);
if(!empty($res2)) $total_cnt = $res2[0][0];
$total_page = max(1, intval(ceil($total_cnt/$page_size)));
$reply_cnt = 0;
$res3 = mysql_query_cache("SELECT COUNT(*) FROM `reply` WHERE `status`<=1");
if(!empty($res3)) $reply_cnt = $res3[0][0];

// 头像色（由用户名散列取色）
function oj_avatar_color($name){
    $palette = array("#2f6ee5","#18a058","#f0a020","#d03050","#8b5cf6","#0ea5e9","#f59e0b","#14b8a6","#ef4444","#6366f1");
    $h = hexdec(substr(md5($name),0,6));
    return $palette[$h % count($palette)];
}
function oj_initial($name){
    return strtoupper(mb_substr($name,0,1,"UTF-8"));
}

$qs = "";
if($cid!=0) $qs.="&cid=".$cid;
if($pid!=0) $qs.="&pid=".$pid;
if($keyword!="") $qs.="&keyword=".urlencode($keyword);
if($sort=="hot") $qs.="&sort=hot";
?>
<div class="oj-discuss-grid">
  <div class="oj-card">

    <div class="oj-toolbar">
      <div class="oj-breadcrumb">
        <?php
          if ($cid!=0) echo "<a href=\"discuss.php?cid=$cid\">Contest $cid</a>";
          else echo "<a href=\"discuss.php\">主版</a>";
          if ($pid!=0){
            if($cid!=0){
              $PAL=pdo_query("select num from contest_problem where contest_id=? and problem_id=?",$cid,$pid)[0][0];
              echo " <span class=\"oj-meta-dot\">/</span> <a href=\"discuss.php?pid=$pid&cid=$cid\">Problem ".$PID[$PAL]."</a>";
            }else{
              echo " <span class=\"oj-meta-dot\">/</span> <a href=\"discuss.php?pid=$pid\">Problem ".$pid."</a>";
            }
          }
        ?>
      </div>
      <div class="oj-toolbar-right">
        <div class="oj-tabs">
          <a class="<?php if($sort=="latest") echo "active"; ?>" href="discuss.php?page=1<?php echo $qs == "" ? "" : preg_replace("/&sort=hot/","",$qs); ?>">最新回复</a>
          <a class="<?php if($sort=="hot") echo "active"; ?>" href="discuss.php?page=1<?php echo $qs; ?>&sort=hot">热门讨论</a>
        </div>
        <form method="get" action="discuss.php" class="oj-search">
          <?php if(isset($_REQUEST['cid'])&&$_REQUEST['cid']!=''){ ?><input type="hidden" name="cid" value="<?php echo intval($_REQUEST['cid']);?>"><?php } ?>
          <?php if(isset($_REQUEST['pid'])&&$_REQUEST['pid']!=''){ ?><input type="hidden" name="pid" value="<?php echo intval($_REQUEST['pid']);?>"><?php } ?>
          <input aria-label="搜索讨论标题、学号或题号" type="text" name="keyword" value="<?php echo htmlspecialchars($keyword,ENT_QUOTES,"UTF-8"); ?>" placeholder="搜索标题 / 学号 / 题号">
          <button class="oj-btn oj-btn-ghost oj-btn-sm" type="submit">搜索</button>
        </form>
      </div>
    </div>

    <?php if ($rows_cnt==0){ ?>
      <div class="oj-empty">
        <div class="icon">&#128172;</div>
        <div class="t">还没有讨论</div>
        <div class="d">遇到问题或有了新思路？发起第一个话题吧。</div>
        <a class="oj-btn oj-btn-primary" href="newpost.php<?php
          if ($pid!=0 && $cid!=null) echo "?pid=".$pid."&cid=".$cid;
          else if ($pid!=0) echo "?pid=".$pid;
          else if ($cid!=0) echo "?cid=".$cid;
        ?>">新建讨论</a>
      </div>
    <?php }else{ ?>
    <div class="oj-topic-list">
      <?php
      $i=0;
      foreach ( $result as $row){
        $acolor = oj_avatar_color($row['author_id']);
        $ainit = oj_initial($row['author_id']);
        $tid = $row['tid'];
        $thread_url = $row['cid'] ? "thread.php?tid=$tid&cid={$row['cid']}" : "thread.php?tid=$tid";
        ?>
        <div class="oj-topic-row">
          <?php if ($isadmin){ ?><input type="checkbox" title="管理操作" style="flex-shrink:0;"><?php } ?>
          <span class="oj-avatar" style="--c:<?php echo $acolor; ?>;"><?php echo $ainit; ?></span>
          <div class="oj-topic-main">
            <div class="oj-topic-title">
              <?php
                if ($row['top_level']!=0){
                  if ($row['top_level']!=1||$row['pid']==($pid==''?0:$pid)) echo "<span class=\"oj-badge oj-badge-top\">置顶</span>";
                }
                else if ($row['status']==1) echo "<span class=\"oj-badge oj-badge-lock\">锁定</span>";
                else if ($row['count']>20) echo "<span class=\"oj-badge oj-badge-hot\">热门</span>";
              ?>
              <a href="<?php echo $thread_url; ?>"><?php echo htmlentities($row['title'],ENT_QUOTES,"UTF-8"); ?></a>
            </div>
            <div class="oj-topic-meta">
              <?php if ($row['pid']!=0){ ?>
                <?php if($row['cid']){ ?><a class="oj-pid" href="discuss.php?pid=<?php echo $row['pid']; ?>&cid=<?php echo $row['cid']; ?>"><?php echo $PID[$row['num']]; ?></a>
                <?php }else{ ?><a class="oj-pid" href="discuss.php?pid=<?php echo $row['pid']; ?>">P<?php echo $row['pid']; ?></a><?php } ?>
                <span class="oj-meta-dot">·</span>
              <?php } ?>
              <span>发布者</span>
              <a href="userinfo.php?user=<?php echo $row['author_id']; ?>"><?php echo $row['author_id']; ?></a>
              <span class="oj-meta-dot">·</span>
              <span><?php echo $row['posttime']; ?></span>
            </div>
          </div>
          <div class="oj-topic-side">
            <span class="oj-reply-count"><b><?php echo max(0,$row['count']-1); ?></b><span>回复</span></span>
            <?php if($row['lastupdate']){ ?><span class="oj-topic-last"><?php echo $row['lastupdate']; ?></span><?php } ?>
          </div>
        </div>
        <?php
        $i++;
      }
      ?>
    </div>
    <?php } ?>

    <?php if($total_page>1){ ?>
    <div class="oj-pagination">
      <a class="<?php if($page==1) echo "disabled"; ?>" href="<?php if($page>1) echo "discuss.php?page=".($page-1).$qs; ?>">上一页</a>
      <?php
        $start=max(1,$page-3); $end=min($total_page,$page+3);
        for($i=$start;$i<=$end;$i++){
          echo "<a class=\"".($i==$page?"active":"")."\" href=\"discuss.php?page=".$i.$qs."\">".$i."</a>";
        }
      ?>
      <a class="<?php if($page==$total_page) echo "disabled"; ?>" href="<?php if($page<$total_page) echo "discuss.php?page=".($page+1).$qs; ?>">下一页</a>
    </div>
    <?php } ?>

  </div>

  <div class="oj-side">
    <div class="oj-card">
      <a class="oj-btn oj-btn-primary oj-btn-block" href="newpost.php<?php
        if ($pid!=0 && $cid!=null) echo "?pid=".$pid."&cid=".$cid;
        else if ($pid!=0) echo "?pid=".$pid;
        else if ($cid!=0) echo "?cid=".$cid;
      ?>">新建讨论</a>
      <div class="oj-side-title" style="margin-top: 18px;">数据概览</div>
      <div class="oj-stats">
        <div><div class="num"><?php echo $total_cnt; ?></div><div class="lbl">主题总数</div></div>
        <div><div class="num"><?php echo $reply_cnt; ?></div><div class="lbl">回复总数</div></div>
      </div>
      <div class="oj-tips" style="margin-top: 16px;">提问请带上题目编号，回复保持礼貌友善。被屏蔽的内容请勿复制传播。</div>
    </div>
  </div>
</div>

<?php require_once("template/$OJ_TEMPLATE/discuss.php")?>
