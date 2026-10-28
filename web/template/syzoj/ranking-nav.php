<?php $rankingContext = $navQuery ?? ($ojAdventureQuery ?? ''); ?>
<nav class="oj-module-tabs" aria-label="排名导航">
  <a href="ranklist.php<?php echo $rankingContext ? '?'.substr($rankingContext, 5) : ''; ?>"<?php if ($url === 'ranklist.php') echo ' aria-current="page"'; ?>>刷题排名</a>
  <a href="coins.php?tab=ranking<?php echo $rankingContext; ?>"<?php if ($url === 'coins.php' && $tab === 'ranking') echo ' aria-current="page"'; ?>>金币榜单</a>
  <a href="coins.php?tab=history<?php echo $rankingContext; ?>"<?php if ($url === 'coins.php' && $tab === 'history') echo ' aria-current="page"'; ?>>我的金币</a>
</nav>
