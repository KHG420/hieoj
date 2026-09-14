<?php $show_title="知识地图 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php"); ?>
<link rel="stylesheet" href="template/syzoj/css/knowledge-graph.css?v=20260913.5">

<div class="kg-page">
<?php if (!$knowledge_graph_ready) { ?>
  <section class="kg-schema">
    <h1>知识地图尚未初始化</h1>
    <p>应用代码已经就绪，但数据库还缺少知识图谱表。管理员需要先执行随部署提供的幂等迁移。</p>
    <code>mariadb jol &lt; db/knowledge-graph.sql</code>
  </section>
<?php } else { ?>
  <main class="kg-shell" id="kg-app">
    <section class="kg-intro" aria-labelledby="kg-title">
      <div>
        <h1 id="kg-title">把下一道题，放回知识路径里</h1>
        <p>选择一个领域，查看知识前置关系、真实关联题目和你的掌握依据。图上的状态来自站内判题记录，不是自我打卡。<a href="adventure.php?tab=route">开启三题远征</a></p>
        <div class="kg-coverage" title="至少命中一个知识节点的公开题目占比"><b><?php echo intval($knowledge_graph_data['governance']['coverage']); ?>%</b><span>题库知识覆盖 · <?php echo intval($knowledge_graph_data['governance']['mapped_problem_count']); ?>/<?php echo intval($knowledge_graph_data['governance']['active_problem_count']); ?> 题</span></div>
      </div>
      <div class="kg-next" id="kg-next">
        <div><div class="kg-next-label">正在计算你的下一步</div><div class="kg-next-title">知识地图加载中</div><div class="kg-next-copy">将优先推荐尝试过但未通过的题目，以及前置知识已经满足的节点。</div></div>
        <button type="button" class="kg-primary" id="kg-continue">继续学习</button>
      </div>
    </section>

    <nav class="kg-domains" id="kg-domains" aria-label="知识领域"></nav>

    <div class="kg-toolbar">
      <label class="kg-search">
        <span class="sr-only">搜索知识点</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        <input id="kg-search" type="search" placeholder="搜索知识点或题目标签" autocomplete="off">
      </label>
      <button type="button" class="kg-control" id="kg-graph-view">关系图</button>
      <button type="button" class="kg-control" id="kg-list-view">列表</button>
      <button type="button" class="kg-control" id="kg-size-view" aria-pressed="false">放大阅读</button>
      <div class="kg-legend" aria-label="掌握状态图例">
        <span><i class="kg-dot"></i>未开始</span><span><i class="kg-dot learning"></i>学习中</span><span><i class="kg-dot mastered"></i>基本掌握</span><span><i class="kg-dot skilled"></i>熟练</span>
      </div>
    </div>

    <section class="kg-workspace">
      <div class="kg-map" id="kg-map">
        <div class="kg-edge-guide" aria-label="知识关系图例">
          <span><i class="prerequisite"></i>前置知识</span>
          <span><i class="strong"></i>强关联</span>
          <span><i class="related"></i>相关</span>
          <small>选择节点可突出它的学习路径</small>
        </div>
        <svg id="kg-svg" role="img" aria-label="当前领域知识关系图"></svg>
        <div class="kg-list" id="kg-list"></div>
      </div>
      <aside class="kg-panel" id="kg-panel" aria-live="polite">
        <div class="kg-panel-empty">选择一个知识点，查看前置关系、掌握依据和关联题目。</div>
      </aside>
    </section>
  </main>
  <script>
  window.KG_DATA = <?php echo json_encode($knowledge_graph_data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
  </script>
  <script src="template/syzoj/js/knowledge-graph.js?v=20260913.6"></script>
<?php } ?>
</div>

<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
