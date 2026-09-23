<?php $show_title = '今日冒险 - '.$OJ_NAME; $OJ_ADVENTURE_VIEWPORT = true; include "template/$OJ_TEMPLATE/header.php"; ?>
<link rel="stylesheet" href="template/syzoj/css/adventure.css?v=20260914.2">
<div class="adv-page">
  <header class="adv-heading"><div><h1>今日冒险</h1><p>重逢一道旧题，走一段新路。今天的进步，从这里开始。</p></div><a href="knowledge_graph.php">探索知识地图</a></header>
  <nav class="adv-tabs" aria-label="冒险玩法">
    <?php foreach ($tabs as $key=>$label) { ?><a href="adventure.php?tab=<?php echo $key; ?>" <?php if ($tab === $key) echo 'aria-current="page"'; ?>><?php echo $label; ?></a><?php } ?>
  </nav>
  <?php if ($error) { ?><p class="adv-feedback adv-error" role="alert"><?php echo adv_escape($error); ?></p><?php } ?>
  <?php if (!$user && !in_array($tab, array('hunt', 'campus'), true)) { ?>
    <section class="adv-panel adv-welcome"><h2>你的冒险，从登录开始</h2><p>登录后，我们会从你的真实提交记录里，找出值得再战的旧题、可复练的比赛和属于你的成长故事。</p><a class="ui primary button" href="loginpage.php">登录账号</a><a class="ui basic button" href="adventure.php?tab=hunt">先试试反例猎人</a></section>
  <?php } elseif ($tab === 'enemy') { ?>
    <section class="adv-panel adv-enemy">
    <?php if ($enemy) { $r = $enemy['record']; $won = !empty($r['accepted']); ?>
      <h2><?php echo $won ? '旧敌已破。' : '还有一题，在等你回来。'; ?></h2>
      <a class="adv-feature-title" href="problem.php?id=<?php echo intval($enemy['id']); ?>"><?php echo intval($enemy['id']).' · '.adv_escape($enemy['title']); ?></a>
      <?php if ($won) { ?><p>从第一次尝试到通过，历时 <?php echo max(0, intval((strtotime($r['first_ac'])-strtotime($r['first_attempt']))/86400)); ?> 天。当时没解出来的题，现在会了。</p><p class="adv-success">今日再战已完成。明天再来遇见下一道旧题。</p><button type="button" class="ui basic button" data-save-card="victory">保存翻盘战报</button>
      <?php } else { ?><p>上次未通过是在 <?php echo adv_escape(substr($r['last_failure'], 0, 10)); ?>。那之后，你又首次通过了 <?php echo $growth; ?> 道公开题。</p><p>过去的尝试没有白费，再给这道题一个新的答案。</p><a class="ui primary button" href="problem.php?id=<?php echo intval($enemy['id']); ?>" target="_blank" rel="noopener">再战一次 <span class="sr-only">（在新标签页打开）</span></a><a class="ui basic button" href="adventure.php">刷新判题进度</a><?php } ?>
    <?php } else { ?><h2>没有旧敌，也是一种好消息。</h2><p>暂时没有昨天及以前尝试过、仍未通过的公开题。去远征里遇见下一道挑战吧。</p><a class="ui primary button" href="adventure.php?tab=route">开启算法远征</a><?php } ?>
    </section>
    <?php if ($victories) { ?><section class="adv-history"><h2>那些后来被你解开的题</h2><ul class="adv-problems"><?php foreach ($victories as $p) { ?><li><a href="problem.php?id=<?php echo intval($p['id']); ?>"><?php echo adv_escape($p['title']); ?></a><span><?php echo adv_escape(substr($p['record']['first_ac'], 0, 10)); ?> · 已通过</span></li><?php } ?></ul></section><?php } ?>
  <?php } elseif ($tab === 'route') { ?>
    <section class="adv-panel"><h2>三道题，走完一段新路</h2><p>沿着知识点及它的前置知识选题。巩固模式优先复练已通过题，挑战模式选择尚未通过题。</p>
    <?php if (!$graph['nodes']) { ?><p class="adv-empty">知识地图暂时没有可用节点。先到<a href="problemset.php">题库</a>自由练习。</p><?php } else { ?>
      <form method="post" class="adv-form" action="adventure.php?tab=route"><?php require './include/set_post_key.php'; ?><input type="hidden" name="action" value="route">
      <label>目的地<select name="node" required<?php if ($route && !$routeNeedsRebuild) echo ' disabled'; ?>><?php foreach ($graph['nodes'] as $node) { $available = false; foreach ($node['progress']['problems'] as $p) if (isset($public[$p['id']])) { $available = true; break; } if (!$available) continue; ?><option value="<?php echo adv_escape($node['slug']); ?>" <?php if (($route['node'] ?? '') === $node['slug']) echo 'selected'; ?>><?php echo adv_escape($node['domain'].' · '.$node['name']); ?></option><?php } ?></select></label>
      <label>这次想怎么练<select name="mode"<?php if ($route && !$routeNeedsRebuild) echo ' disabled'; ?>><option value="challenge" <?php if (($route['mode'] ?? 'challenge') === 'challenge') echo 'selected'; ?>>挑战自己</option><option value="review" <?php if (($route['mode'] ?? '') === 'review') echo 'selected'; ?>>巩固一下</option></select></label>
      <button class="ui primary button" type="submit"><?php echo $routeNeedsRebuild ? '重新生成今日路线' : ($routeReady ? '查看今日路线' : '开启三题远征'); ?></button></form>
      <p class="adv-note">每日路线当天固定保存：生成今天的路线后，目的地与练习模式不能再改，重复提交只会回到同一组三题和同一开始时间，只有本轮开始后的练习 AC 才点亮路标。路线中的题目不再公开或已不存在时，按钮会变成“重新生成今日路线”，系统按上面选择的知识点换一组合法题目，并把开始时间与提交编号边界移到替换时刻。三道题都在当天通过可领取 10 金币，每位用户每天最多奖励一次，按北京时间 00:00 换日；替换路线不会产生第二次奖励。</p>
    <?php } ?>
    </section>
    <?php if ($routeRestoreError) { ?><p class="adv-feedback adv-error" role="alert"><?php echo adv_escape($routeRestoreError); ?></p>
    <?php } elseif ($routeInvalid) { ?><p class="adv-feedback">路线中有题目已不再公开或已不存在，<?php echo $routeNeedsRebuild ? '请点击上方“重新生成今日路线”，系统会换一组合法题目并从当前时刻重新计时。' : '当前知识地图不可用，请稍后刷新页面再试。'; ?></p><?php } elseif ($routeReady) { $done = count(array_intersect(array_column($route['problems'], 'id'), $routeDone)); ?>
    <section class="adv-panel"><div class="adv-section-head"><h2><?php echo $done === 3 ? '远征完成，这段路属于你。' : '你的本次路线'; ?></h2><span><?php echo $done; ?> / 3 站已通过</span></div>
    <ol class="adv-route"><?php foreach ($route['problems'] as $i=>$p) { $accepted = in_array($p['id'], $routeDone); $shown = isset($public[$p['id']]); ?><li class="<?php echo $accepted ? 'is-done' : ''; ?>"><span class="adv-stop"><?php echo $i+1; ?></span><div><span class="adv-note"><?php echo adv_escape($p['node_name']); ?></span><h3><?php if ($shown) { ?><a href="problem.php?id=<?php echo intval($p['id']); ?>" target="_blank" rel="noopener"><?php echo adv_escape($public[$p['id']]['title']); ?><span class="sr-only">（在新标签页打开）</span></a><?php } else { ?><span class="adv-note">题目 <?php echo intval($p['id']); ?> 已不可用</span><?php } ?></h3><span><?php echo $accepted ? '已通过 · 路标点亮' : '等待你的答案'; ?></span></div></li><?php } ?></ol>
    <?php if ($rewardPaid) { ?><p class="adv-success" role="status">今日冒险奖励已到账：+10 金币。明天完成新的三道题可以再次领取。</p>
    <?php } elseif ($rewardClaimed) { ?><p class="adv-note">今日冒险奖励已领取。每位用户每天最多奖励一次，明天再来。</p>
    <?php } elseif ($routeComplete) { ?><p class="adv-note">今日 10 金币尚未发放：需要三道题都在今天通过，刷新本页即可领取。</p>
    <?php } if ($rewardError) { ?><p class="adv-feedback adv-error" role="alert">金币奖励暂时无法发放，请稍后刷新重试。</p><?php } ?>
    <?php if ($done === 3) { ?><button type="button" class="ui primary button" data-save-card="route">保存通关卡</button><?php } else { ?><a class="ui basic button" href="adventure.php?tab=route">刷新判题进度</a><?php } ?></section><?php } ?>
  <?php } elseif ($tab === 'shadow') { ?>
    <section class="adv-panel"><h2>和过去的自己，打一场</h2><p>重练一场参加过的公开比赛，让当时的提交时间线陪你跑完全程。</p>
    <?php if (!$contests) { ?><p class="adv-empty">暂时没有可复练的比赛。这里会展示你参加过、已经结束且所有题目均可公开练习的比赛。</p><a href="contest.php">查看比赛与作业</a><?php } else { ?>
    <form method="post" action="adventure.php?tab=shadow" class="adv-form"><?php require './include/set_post_key.php'; ?><input type="hidden" name="action" value="shadow"><label>选择历史比赛<select name="contest"><?php foreach ($contests as $id=>$c) { ?><option value="<?php echo $id; ?>" <?php if (($shadow['contest']['contest_id'] ?? 0) == $id) echo 'selected'; ?>><?php echo adv_escape($c['title']); ?></option><?php } ?></select></label><button class="ui primary button" type="submit"><?php echo $shadow ? '重新开始计时' : '开始影子挑战'; ?></button></form>
    <p class="adv-note">这是复练成绩。通过下方题目链接正常提交，不计入原比赛排名。计时保存在当前登录会话中，重新开始会重置本轮。</p><?php } ?></section>
    <?php if (isset($state['shadow']) && !$shadow) { ?><p class="adv-feedback">上一场挑战已不可用，请重新选择公开比赛。</p><?php } ?>
    <?php if ($shadow) { ?><section class="adv-panel"><div class="adv-section-head"><h2><?php echo adv_escape($shadow['contest']['title']); ?></h2><span id="adv-clock" data-elapsed="<?php echo $shadow['elapsed']; ?>" data-duration="<?php echo $shadow['duration']; ?>"><?php echo intdiv($shadow['elapsed'],60); ?> 分钟</span></div><p><?php echo $shadow['finished'] ? '本轮计时结束，以下为本轮复练战报。' : '以下对比同一时刻的成绩。刷新页面可更新判题进度。'; ?></p>
    <table class="adv-table"><caption class="sr-only">影子挑战成绩对比</caption><thead><tr><th>本轮表现</th><th>过去的你</th><th>现在的你</th></tr></thead><tbody><tr><th>通过题数</th><td><?php echo count($shadow['past']['accepted']); ?></td><td><?php echo count($shadow['current']['accepted']); ?></td></tr><tr><th>首题用时</th><?php foreach (array('past','current') as $side) { ?><td><?php echo $shadow[$side]['first'] === null ? '尚未通过' : intdiv($shadow[$side]['first'],60).' 分 '.($shadow[$side]['first']%60).' 秒'; ?></td><?php } ?></tr><tr><th>错误提交</th><td><?php echo $shadow['past']['wrong']; ?></td><td><?php echo $shadow['current']['wrong']; ?></td></tr></tbody></table>
    <ul class="adv-problems"><?php foreach ($shadow['contest']['ids'] as $id) { ?><li><a href="problem.php?id=<?php echo $id; ?>" target="_blank" rel="noopener"><?php echo adv_escape($public[$id]['title']); ?><span class="sr-only">（在新标签页打开）</span></a><span><?php echo isset($shadow['current']['accepted'][$id]) ? '本轮已通过' : '本轮未通过'; ?> · <?php echo isset($shadow['timeline']['accepted'][$id]) ? '影子在第 '.intdiv($shadow['timeline']['accepted'][$id], 60).' 分钟通过' : '影子当时未通过'; ?></span></li><?php } ?></ul><a class="ui basic button" href="adventure.php?tab=shadow">刷新判题进度</a></section><?php } ?>
  <?php } elseif ($tab === 'hunt') { $hunts = adv_hunts(); $huntSlug = isset($_GET['hunt']) && is_string($_GET['hunt']) && isset($hunts[$_GET['hunt']]) ? $_GET['hunt'] : ($state['hunt_feedback']['slug'] ?? array_keys($hunts)[intval(date('W')) % count($hunts)]); if (!isset($hunts[$huntSlug])) $huntSlug = 'maximum'; $hunt = $hunts[$huntSlug]; $feedback = isset($state['hunt_feedback']) && $state['hunt_feedback']['slug'] === $huntSlug ? $state['hunt_feedback'] : null; ?>
    <section class="adv-panel"><div class="adv-section-head"><h2>一起出题，一起找破绽</h2><a class="ui primary button" href="hunt.php?mode=new">我要出题</a></div><p>所有登录用户都可以发布反例题，构造输入验证错误，也可以在题目评论区交流思路。</p><a href="hunt.php">浏览社区题目 →</a></section>
    <section class="adv-panel"><h2>代码看起来没问题。真的？</h2><p>构造一组合法输入，让下面的错误程序露出破绽。每周轮换默认挑战，也可以自由选择。</p><nav class="adv-hunts" aria-label="反例挑战题目"><?php foreach ($hunts as $slug=>$h) { ?><a href="adventure.php?tab=hunt&amp;hunt=<?php echo $slug; ?>" <?php if ($slug === $huntSlug) echo 'aria-current="page"'; ?>><?php echo adv_escape($h['title']); ?><?php if (!empty($state['hunts'][$slug])) echo ' · 已攻破'; ?></a><?php } ?></nav>
    <div class="adv-hunt-layout"><div><h3><?php echo adv_escape($hunt['title']); ?></h3><p id="hunt-rule"><?php echo adv_escape($hunt['rule']); ?></p><pre aria-label="待找反例的 C++ 代码"><code><?php echo adv_escape($hunt['code']); ?></code></pre></div><div>
    <?php if ($user) { ?><form method="post" action="adventure.php?tab=hunt" class="adv-hunt-form"><?php require './include/set_post_key.php'; ?><input type="hidden" name="action" value="hunt"><input type="hidden" name="hunt" value="<?php echo $huntSlug; ?>"><label for="hunt-input">你的反例输入</label><textarea id="hunt-input" name="input" rows="5" maxlength="256" required aria-describedby="hunt-rule"><?php echo $feedback ? adv_escape($feedback['input']) : ''; ?></textarea><button class="ui primary button" type="submit">验证反例</button></form>
    <?php if ($feedback) { $f = $feedback['result']; ?><div class="adv-feedback <?php echo !empty($f['hit']) ? 'adv-success' : ''; ?>" role="status"><?php if (isset($f['error'])) echo adv_escape($f['error']); else { ?><strong><?php echo $f['hit'] ? '命中！这就是它的破绽。' : '还没有击中，再试一次。'; ?></strong><p>程序输出 <?php echo $f['actual']; ?>，正确输出 <?php echo $f['expected']; ?>。</p><p><?php echo adv_escape($f['reason']); ?></p><?php } ?></div><?php } ?>
    <p class="adv-note">验证在服务器完成；本会话内会记住已攻破的挑战。</p><?php } else { ?><p>想好反例了吗？登录后提交，立即查看程序输出与正确结果。</p><a class="ui primary button" href="loginpage.php">登录并验证</a><?php } ?></div></div><a href="hunt.php?builtin=<?php echo adv_escape($huntSlug); ?>#comments">进入这道题的评论区 →</a></section>
  <?php } elseif ($tab === 'campus') { ?>
    <section class="adv-panel"><h2>这周，一起把路走通</h2><p><?php echo adv_escape(substr($week[0], 0, 10)); ?> 至 <?php echo date('Y-m-d', strtotime($week[1])-1); ?> · 本周五题接力</p>
    <?php if (!$campus) { ?><p class="adv-empty">公开题库还没有可用题目，等待第一道题加入这次接力。</p><?php } else { ?>
    <div class="adv-community"><strong><?php echo $contributions; ?> / 100</strong><span>次首次通过 · <?php echo $contributors; ?> 位同学参与</span><progress value="<?php echo min(100,$contributions); ?>" max="100" aria-label="本周共同挑战进度"></progress></div>
    <p><?php echo $contributions >= 100 ? '本周目标达成。每一次新的突破，仍然值得记录。' : '全站共同完成 100 次首次通过，就能完成本周接力。'; ?><?php if ($user) echo ' 你已贡献 '.$mine.' 次。'; ?></p>
    <ol class="adv-problems"><?php foreach ($campus as $p) { ?><li><a href="problem.php?id=<?php echo intval($p['id']); ?>"><?php echo adv_escape($p['title']); ?></a><span><?php echo $p['contributions']; ?> 次贡献<?php if ($user && !empty($results[$p['id']]['accepted'])) echo ' · 你已通过'; ?></span></li><?php } ?></ol>
    <p class="adv-note">每位同学每题只记一次，需在本周练习中首次 AC；往周已通过、比赛提交和重复提交不会增加贡献。题目每周轮换，关闭或进入比赛的题目会退出本周题单。</p><?php } ?></section>
  <?php } elseif ($tab === 'memoir') { ?>
    <section class="adv-panel"><div class="adv-section-head"><h2>你的解题回忆录</h2><form method="get" class="adv-form"><input type="hidden" name="tab" value="memoir"><label>回看月份<input type="month" name="month" min="2000-01" max="<?php echo date('Y-m'); ?>" value="<?php echo $month; ?>" required></label><button type="submit" class="ui basic button">查看回忆</button></form></div>
    <?php if (!$memoir['attempts']) { ?><p class="adv-empty">这个月还没有公开题目的提交记录。换个月份看看，或者从今天开始写下第一笔。</p><a href="problemset.php">去做一道题</a><?php } else { ?>
    <article class="adv-memoir" id="adv-memoir"><h3><?php echo adv_escape($month); ?>，一步一步走到这里。</h3><p>这个月，你在 <?php echo $memoir['days']; ?> 个日子里留下了尝试，通过了 <?php echo $memoir['solved']; ?> 道公开题。</p><?php if ($memoir['first']) { ?><p>这个月最先通过的是 <a href="problem.php?id=<?php echo intval($memoir['first']['id']); ?>"><?php echo adv_escape($memoir['first']['title']); ?></a>。</p><p>你用 <?php echo $memoir['languages']; ?> 种编程语言完成了解题。</p><?php } ?><p><?php echo $memoir['comebacks']; ?> 道曾经没通过的题，在这个月被你解开。</p><?php if ($memoir['hard_won']) { ?><p>值得记住的一次突破：<a href="problem.php?id=<?php echo intval($memoir['hard_won']['id']); ?>"><?php echo adv_escape($memoir['hard_won']['title']); ?></a>。那些尝试，终于有了答案。</p><?php } ?><p class="adv-memoir-end">每个解开的结，都算数。</p></article><button class="ui primary button" type="button" data-save-card="memoir">保存本月回忆卡</button><p class="adv-note">只统计当前公开题目。回忆仅对本人展示，保存的卡片不会自动发布。</p><?php } ?></section>
  <?php } ?>
  <p id="adv-save-status" role="status"></p>
</div>
<?php
$cards = array();
if ($enemy && !empty($enemy['record']['accepted'])) $cards['victory'] = array('title'=>'旧敌已破', 'lines'=>array($enemy['title'], '首次尝试：'.substr($enemy['record']['first_attempt'],0,10), '最终通过：'.substr($enemy['record']['first_ac'],0,10), '当时没解出来的题，现在会了。'));
if ($tab === 'route' && $route && !$routeInvalid && isset($done) && $done === 3) $cards['route'] = array('title'=>'三题远征 · 已通关', 'lines'=>array_merge(array_column($route['problems'],'title'), array('每一站，都有你的答案。')));
if ($tab === 'memoir' && $memoir && $memoir['attempts']) $cards['memoir'] = array('title'=>$month.' 解题回忆录', 'lines'=>array('在 '.$memoir['days'].' 个日子里留下尝试', '通过 '.$memoir['solved'].' 道题 · 使用 '.$memoir['languages'].' 种语言', '解开 '.$memoir['comebacks'].' 道曾经没通过的题', '每个解开的结，都算数。'));
?>
<script id="adv-card-data" type="application/json"><?php echo json_encode($cards, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script>
<script defer src="template/syzoj/js/adventure.js?v=20260914.1"></script>
<?php include "template/$OJ_TEMPLATE/footer.php"; ?>
