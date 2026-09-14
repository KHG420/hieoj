<section class="ed-panel ed-composer" aria-labelledby="compose-heading">
  <div class="ed-compose-heading"><div><h2 id="compose-heading">编写题解</h2><p class="ed-meta">讲清楚为什么这样做，让下一位读者也能独立解出来。</p></div><span class="ed-meta"><?php echo $passed ? '已通过本题' : '登录并通过本题后可提交'; ?></span></div>
  <form method="post" class="ed-form" id="editorial-form" data-draft-key="<?php echo editorial_escape($draftKey); ?>">
    <?php require './include/set_post_key.php'; ?><input type="hidden" name="action" value="submit">
    <input type="hidden" name="content_format" value="<?php echo $formatInput === 'plain' ? 'plain' : 'markdown'; ?>">
    <label for="editorial-title">题解标题</label>
    <input id="editorial-title" name="title" maxlength="120" required value="<?php echo editorial_escape($titleInput); ?>" placeholder="例如：用前缀和，把区间查询变成一次减法" aria-describedby="title-count">
    <div class="ed-editor-meta"><span id="title-count">最多 120 字</span><span id="draft-status" role="status" aria-live="polite">草稿仅保存在当前浏览器，不跨设备同步。</span></div>
    <div id="draft-restore" class="ed-notice" hidden><p id="draft-message">发现本题的本机草稿。</p><div class="ed-actions"><button type="button" id="restore-draft" class="ui basic button">恢复草稿</button><button type="button" id="keep-current" class="ui basic button">保留当前内容</button></div></div>
    <label for="editorial-content">题解正文</label>
    <p class="ed-meta" id="content-help"><?php echo $formatInput === 'plain' ? '当前保留原纯文本格式，换行和代码缩进会原样显示。' : '支持 Markdown 和 LaTeX。可用工具栏插入格式，在预览中检查阅读效果。'; ?></p>
    <div class="ed-editor-tools" hidden>
      <?php if($formatInput !== 'plain'){ ?>
      <div class="ed-format-tools" aria-label="正文格式工具">
        <button type="button" data-insert="heading">标题</button><button type="button" data-insert="bold"><strong>加粗</strong></button><button type="button" data-insert="list">列表</button><button type="button" data-insert="link">链接</button>
        <label class="ed-visually-hidden" for="code-language">代码语言</label><select id="code-language"><option value="cpp">C++</option><option value="c">C</option><option value="python">Python</option><option value="java">Java</option><option value="javascript">JavaScript</option><option value="text">纯文本</option></select>
        <button type="button" data-insert="code">代码块</button><button type="button" data-insert="math">公式</button><button type="button" id="insert-template">插入题解模板</button>
      </div><?php } ?>
      <div class="ed-view-tools" aria-label="编辑器视图"><button type="button" data-mode="edit" aria-pressed="false">编辑</button><button type="button" data-mode="split" aria-pressed="true">对照</button><button type="button" data-mode="preview" aria-pressed="false">预览</button><button type="button" id="editor-focus" aria-pressed="false">专注写作</button></div>
    </div>
    <div class="ed-editor-workspace" data-mode="edit">
      <div class="ed-editor-input"><textarea id="editorial-content" name="content" rows="18" maxlength="50000" aria-describedby="content-help content-count" required spellcheck="false" placeholder="先解释关键思路，再展开算法步骤、复杂度和代码。也可以使用上方的题解模板。" ><?php echo editorial_escape($contentInput); ?></textarea></div>
      <section class="ed-editor-preview" aria-label="题解阅读效果" hidden><div class="ed-preview-label">阅读效果</div><div id="editorial-preview" class="ed-markdown"></div></section>
    </div>
    <div class="ed-editor-meta"><span id="content-count">最多 50000 字</span><button type="button" id="download-draft" hidden>下载草稿</button></div>
    <details class="ed-writing-help"><summary>写作帮助与快捷键</summary><p>建议写清解题思路、算法步骤、时间与空间复杂度，并附上可读的代码。标题用 <code>##</code>，加粗用 <code>**文字**</code>，代码放在三反引号之间。行内公式用 <code>$O(n)$</code>，独立公式用 <code>$$…$$</code>。图片语法显示为链接，不自动加载外部图片。</p><p>Ctrl / ⌘ + B 加粗；Ctrl / ⌘ + S 保存本机草稿。下载草稿可另存一份；清理浏览器数据会移除本机草稿。</p></details>
    <div class="ed-submit-row"><div><strong>准备好后再提交审核</strong><p class="ed-meta">审核通过后公开，并获得 10 金币。保存草稿不会提交审核。</p></div><button class="ui primary button" type="submit" <?php if(!$passed) echo 'disabled'; ?>>提交审核</button></div>
    <?php if(!$user){ ?><p><a href="loginpage.php">重新登录</a>后再提交，正文已保留，可先下载草稿。</p><?php } ?>
  </form>
</section>
