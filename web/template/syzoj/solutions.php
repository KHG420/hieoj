<?php include("template/$OJ_TEMPLATE/header.php"); ?>
<link rel="stylesheet" href="template/syzoj/css/editorial.css?v=1">
<div class="ed-page">
  <div class="ed-heading"><div><h1>题解</h1><p>分享通过之后的思路，让每一次解题都有新的收获。</p></div><a class="ui basic button" href="coins.php<?php echo $user ? '?tab=history' : ''; ?>"><?php echo $user ? '我的金币 · '.intval($balance) : '了解金币奖励'; ?></a></div>
  <nav class="ed-tabs" aria-label="题解导航">
    <a href="solutions.php" <?php if($tab==='published') echo 'aria-current="page"'; ?>>公开题解</a>
    <a href="solutions.php?tab=mine" <?php if($tab==='mine') echo 'aria-current="page"'; ?>>我的题解</a>
    <a href="coins.php">金币榜单</a>
    <?php if($admin){ ?><a href="solutions.php?tab=review" <?php if($tab==='review') echo 'aria-current="page"'; ?>>管理员审核</a><?php } ?>
  </nav>
  <?php if(!$ready){ ?><div class="ed-notice">题解模块暂未开放，请稍后再来。</div><?php } ?>
  <?php if($error){ ?><div class="ed-notice ed-error" role="alert"><?php echo editorial_escape($error); ?><?php if(!$user){ ?> <a href="loginpage.php">前往登录</a><?php } ?></div><?php } ?>
  <?php if($ready && $article){ ?>
    <article class="ed-panel">
      <a href="solutions.php?problem_id=<?php echo intval($article['problem_id']); ?>">P<?php echo intval($article['problem_id']).' · '.editorial_escape($article['problem_title']); ?> 的题解</a>
      <h2 class="ed-article-title"><?php echo editorial_escape($article['title']); ?></h2>
      <p class="ed-meta">作者 <?php echo editorial_escape($article['user_id']); ?> · <?php echo editorial_escape($article['created_at']); ?> · <?php echo $statusNames[$article['status']]; ?></p>
      <?php if($canRead){ ?>
        <?php if($article['status']==='pending'){ ?><p class="ed-notice">题解已提交，正在等待管理员审核。审核通过后公开，并奖励 10 金币。</p><?php } ?>
        <?php if(($admin || $article['user_id']===$user) && $article['review_note']!==''){ ?><p class="ed-notice">审核说明：<?php echo editorial_escape($article['review_note']); ?></p><?php } ?>
        <div class="ed-body"><?php echo editorial_escape($article['content']); ?></div>
        <?php if($admin && $tab==='review' && $article['status']==='pending'){ ?>
          <form method="post" class="ed-form ed-review">
            <?php require './include/set_post_key.php'; ?><input type="hidden" name="action" value="review">
            <h3>审核这篇题解</h3>
            <label for="review-note">审核说明（驳回时必填）</label><textarea id="review-note" name="note" rows="3" maxlength="500"><?php echo editorial_escape(is_string($_POST['note'] ?? null) ? $_POST['note'] : ''); ?></textarea>
            <div class="ed-actions"><button class="ui primary button" type="submit" name="decision" value="approved">审核通过 · 奖励作者 10 金币</button><button class="ui basic button" type="submit" name="decision" value="rejected">驳回题解</button></div>
          </form>
        <?php } ?>
      <?php } else { ?>
        <div class="ed-paywall"><h3>解锁本题全部题解</h3><p>支付 <strong>5 金币</strong>，永久解锁本题全部已审核题解，包括后续新增题解。通过本题后可免费查看。</p>
        <?php if($user){ ?><p>当前余额 <?php echo intval($balance); ?> 金币<?php if($balance<5) echo '，还差 '.(5-$balance).' 金币。'; ?></p>
          <form method="post"><?php require './include/set_post_key.php'; ?><input type="hidden" name="action" value="unlock"><button class="ui primary button" type="submit" <?php if($balance<5) echo 'disabled'; ?>>支付 5 金币解锁本题</button></form>
          <?php if($balance<5){ ?><p><a href="problemset.php">去练习赚金币</a> · 首次通过一道题获得 2 金币。</p><?php } ?>
        <?php } else { ?><a class="ui primary button" href="loginpage.php">登录后解锁</a><?php } ?>
        </div>
      <?php } ?>
    </article>
  <?php } elseif($ready && (!$error || $rows || $problem)){ ?>
    <?php if($problem){ ?><div class="ed-section-heading"><h2>P<?php echo intval($problemId).' · '.editorial_escape($problem['title']); ?></h2><a href="problem.php?id=<?php echo intval($problemId); ?>">返回题目</a></div><?php } ?>
    <?php if(!$problem && $tab==='published'){ ?><form method="get" class="ed-search"><label for="problem-search">按题号查找或提交题解</label><input id="problem-search" name="problem_id" type="number" min="1" required placeholder="例如 1000"><button class="ui basic button" type="submit">查看该题题解</button></form><?php } ?>
    <section class="ed-panel" aria-label="题解列表">
      <?php if(!$rows){ ?><div class="ed-empty"><h2><?php echo $tab==='review' ? '暂时没有待审核题解' : ($tab==='mine' ? '你还没有提交题解' : '暂时没有公开题解'); ?></h2><p><?php echo $tab==='review' ? '新提交的题解会出现在这里。' : '通过题目后，在题目页面进入题解，分享你的解题思路。'; ?></p></div><?php } ?>
      <?php foreach($rows as $item){ ?><div class="ed-row"><div><a class="ed-row-title" href="solutions.php?id=<?php echo intval($item['id']).($tab==='review' ? '&amp;tab=review' : ''); ?>"><?php echo editorial_escape($item['title']); ?></a><p class="ed-meta">P<?php echo intval($item['problem_id']).' · '.editorial_escape($item['problem_title']); ?></p><p class="ed-meta"><?php echo editorial_escape($item['user_id']).' · '.editorial_escape($item['created_at']); ?></p></div><span class="ed-status"><?php echo $statusNames[$item['status']]; ?></span></div><?php } ?>
    </section>
    <?php if($page>1 || $hasNext){ ?><nav class="ed-pagination" aria-label="题解分页"><?php if($page>1){ ?><a class="ui basic button" href="solutions.php?tab=<?php echo $tab; ?>&amp;problem_id=<?php echo $problemId; ?>&amp;page=<?php echo $page-1; ?>">上一页</a><?php } ?><span>第 <?php echo $page; ?> 页</span><?php if($hasNext){ ?><a class="ui basic button" href="solutions.php?tab=<?php echo $tab; ?>&amp;problem_id=<?php echo $problemId; ?>&amp;page=<?php echo $page+1; ?>">下一页</a><?php } ?></nav><?php } ?>
    <?php if($problem && $tab==='published'){ ?><section class="ed-panel"><h2>提交我的题解</h2>
      <?php if($passed){ ?><p>审核通过后获得 10 金币。请写清思路、算法步骤、复杂度和关键代码。</p>
        <form method="post" class="ed-form"><?php require './include/set_post_key.php'; ?><input type="hidden" name="action" value="submit">
          <label for="editorial-title">题解标题</label><input id="editorial-title" name="title" maxlength="120" required value="<?php echo editorial_escape($titleInput); ?>" placeholder="例如：用前缀和减少重复计算">
          <label for="editorial-content">题解正文</label><p class="ed-meta" id="content-help">使用纯文本编写，保留换行和代码缩进，最多 50000 字。</p><textarea id="editorial-content" name="content" rows="16" maxlength="50000" aria-describedby="content-help" required><?php echo editorial_escape($contentInput); ?></textarea>
          <div><button class="ui primary button" type="submit">提交题解，等待审核</button></div>
        </form>
      <?php } else { ?><p>通过这道题目后即可提交题解。<?php if(!$user){ ?><a href="loginpage.php">请先登录</a><?php } else { ?><a href="problem.php?id=<?php echo $problemId; ?>">去完成题目</a><?php } ?></p><?php } ?>
    </section><?php } ?>
  <?php } ?>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
