<?php include("template/$OJ_TEMPLATE/header.php"); ?>
<link rel="stylesheet" href="template/syzoj/css/editorial.css?v=2">
<div class="ed-page">
  <div class="ed-heading"><div><h1>金币榜单</h1><p>首次通过一道题 +2 金币，题解审核通过 +10 金币，解锁一道题的全部题解 −5 金币，通过该题免费查看。</p></div><?php if($user){ ?><a class="ui basic button" href="coins.php?tab=history">我的金币 · <?php echo intval($balance); ?></a><?php } ?></div>
  <nav class="ed-tabs" aria-label="金币导航"><a href="coins.php" <?php if($tab==='ranking') echo 'aria-current="page"'; ?>>金币榜单</a><a href="coins.php?tab=history" <?php if($tab==='history') echo 'aria-current="page"'; ?>>我的金币明细</a><a href="problemset.php">去题库练习</a></nav>
  <?php if(!$ready){ ?><div class="ed-notice">金币模块暂未开放，请稍后再来。</div><?php } elseif($error){ ?><div class="ed-notice ed-error" role="alert"><?php echo editorial_escape($error); ?><?php if(!$user){ ?> <a href="loginpage.php">前往登录</a><?php } ?></div><?php } else { ?>
    <section class="ed-panel">
      <?php if($tab==='ranking'){ ?><h2>按当前余额排名</h2><p class="ed-meta">消费后余额和排名会变化。余额相同时按用户名排序。</p><?php } else { ?><h2>我的金币明细</h2><p class="ed-meta">仅展示功能上线后的收支，历史通过题目不补发金币。同一题首次通过、同一篇题解审核通过均只奖励一次。</p><?php } ?>
      <?php if(!$rows){ ?><p class="ed-empty"><?php echo $tab==='ranking' ? '暂时没有可展示的用户。' : '还没有金币收支。去完成一道新题，获得第一笔奖励。'; ?></p><?php } else { ?>
      <div class="ed-table-wrap"><table class="ed-table"><thead><tr><?php if($tab==='ranking'){ ?><th scope="col">排名</th><th scope="col">用户</th><th scope="col">金币余额</th><?php } else { ?><th scope="col">时间</th><th scope="col">事项</th><th scope="col">变动</th><?php } ?></tr></thead><tbody>
      <?php foreach($rows as $index=>$item){ ?><tr><?php if($tab==='ranking'){ ?><td><?php echo ($page-1)*30+$index+1; ?></td><td><a href="userinfo.php?user=<?php echo rawurlencode($item['user_id']); ?>"><?php echo editorial_escape($item['user_id']); ?></a><?php if($item['nick']!==''){ ?><span class="ed-nick"><?php echo editorial_escape($item['nick']); ?></span><?php } ?></td><td><?php echo intval($item['balance']); ?></td><?php } else { ?><td><?php echo editorial_escape($item['created_at']); ?></td><td><?php echo array('first_ac'=>'首次通过题目','editorial_reward'=>'题解审核奖励','problem_unlock'=>'解锁本题全部题解')[$item['kind']]; ?> · <?php echo $item['kind']==='editorial_reward' ? '#' : 'P'; ?><?php echo intval($item['reference_id']); ?></td><td class="<?php echo $item['amount']>0 ? 'ed-credit' : ''; ?>"><?php echo ($item['amount']>0 ? '+' : '').intval($item['amount']); ?></td><?php } ?></tr><?php } ?>
      </tbody></table></div><?php } ?>
    </section>
    <?php if($page>1 || $hasNext){ ?><nav class="ed-pagination" aria-label="金币分页"><?php if($page>1){ ?><a class="ui basic button" href="coins.php?tab=<?php echo $tab; ?>&amp;page=<?php echo $page-1; ?>">上一页</a><?php } ?><span>第 <?php echo $page; ?> 页</span><?php if($hasNext){ ?><a class="ui basic button" href="coins.php?tab=<?php echo $tab; ?>&amp;page=<?php echo $page+1; ?>">下一页</a><?php } ?></nav><?php } ?>
  <?php } ?>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
