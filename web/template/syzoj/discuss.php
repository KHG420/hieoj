<?php $show_title="讨论版 - $OJ_NAME"; ?>
<?php

   $view_discuss=ob_get_contents();
    ob_end_clean();
   require_once(dirname(__FILE__)."/../../lang/$OJ_LANG.php");
?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<?php include("include/bbcode.php");?>
<style>
/* ============================================================
   讨论版设计系统（Product Register · Restrained）
   单一无衬线字体 · 克制配色 · 完整交互状态 · 动效仅传达状态
   ============================================================ */
:root {
  --oj-p: #2f6ee5;
  --oj-p-h: #1e56c6;
  --oj-p-soft: rgba(47,110,229,.10);
  --oj-bg: #f4f6fa;
  --oj-surface: #ffffff;
  --oj-surface-2: #f8fafc;
  --oj-ink: #1f2d3d;
  --oj-ink-2: #3a4759;
  --oj-muted: #5f6d85;
  --oj-faint: #8a93a6;
  --oj-border: #e6ebf2;
  --oj-border-2: #d7dee8;
  --oj-success: #18a058;
  --oj-warning: #f0a020;
  --oj-danger: #d03050;
  --oj-r-sm: 8px;
  --oj-r: 12px;
  --oj-shadow: 0 1px 2px rgba(15,23,42,.04), 0 6px 20px rgba(15,23,42,.06);
  --oj-font: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", "Noto Sans CJK SC", sans-serif;
  --oj-mono: ui-monospace, "Fira Mono", Consolas, "Courier New", monospace;
}

.oj-discuss-page { max-width: 1160px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }

/* 页头 */
.oj-discuss-hero { background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%); border: 1px solid var(--oj-border); border-radius: var(--oj-r); padding: 22px 26px; box-shadow: var(--oj-shadow); }
.oj-discuss-hero h1 { margin: 0; font-size: 24px; font-weight: 700; color: var(--oj-ink); letter-spacing: -.2px; }
.oj-discuss-hero .sub { margin-top: 4px; font-size: 14px; color: var(--oj-muted); }

/* 主体网格 */
.oj-discuss-grid { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 18px; align-items: start; }
@media (max-width: 980px) { .oj-discuss-grid { grid-template-columns: 1fr; } }

.oj-card { background: var(--oj-surface); border: 1px solid var(--oj-border); border-radius: var(--oj-r); box-shadow: var(--oj-shadow); }

/* 工具条：面包屑 + 搜索 + 排序 */
.oj-toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--oj-border); }
.oj-breadcrumb { font-size: 13px; color: var(--oj-muted); }
.oj-breadcrumb a { color: var(--oj-p); }
.oj-toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.oj-search { display: flex; gap: 8px; }
.oj-search input[type=text] { border: 1px solid var(--oj-border-2); border-radius: var(--oj-r-sm); padding: 7px 12px; font-size: 14px; width: 200px; color: var(--oj-ink); background: var(--oj-surface); transition: border-color .15s ease, box-shadow .15s ease; }
.oj-search input[type=text]::placeholder { color: var(--oj-faint); }
.oj-search input[type=text]:focus { outline: none; border-color: var(--oj-p); box-shadow: 0 0 0 3px var(--oj-p-soft); }
.oj-tabs { display: inline-flex; background: var(--oj-surface-2); border: 1px solid var(--oj-border); border-radius: var(--oj-r-sm); padding: 3px; gap: 2px; }
.oj-tabs a { padding: 5px 12px; border-radius: 6px; font-size: 13px; color: var(--oj-muted); text-decoration: none; transition: background .15s ease, color .15s ease; }
.oj-tabs a:hover { color: var(--oj-ink-2); }
.oj-tabs a.active { background: var(--oj-surface); color: var(--oj-ink); font-weight: 600; box-shadow: 0 1px 3px rgba(15,23,42,.08); }

/* 按钮（统一词汇） */
.oj-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: var(--oj-r-sm); font-size: 14px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; transition: background .15s ease, border-color .15s ease, box-shadow .15s ease, color .15s ease; }
.oj-btn:focus-visible { outline: 2px solid var(--oj-p); outline-offset: 2px; }
.oj-btn-primary { background: var(--oj-p); color: #fff; }
.oj-btn-primary:hover { background: var(--oj-p-h); color: #fff; }
.oj-btn-primary:active { background: #174aa5; }
.oj-btn-ghost { background: var(--oj-surface); color: var(--oj-ink-2); border-color: var(--oj-border-2); }
.oj-btn-ghost:hover { border-color: var(--oj-p); color: var(--oj-p); }
.oj-btn-ghost:active { background: var(--oj-surface-2); }
.oj-btn-block { width: 100%; }
.oj-btn-sm { padding: 6px 12px; font-size: 13px; }
.oj-btn:disabled { opacity: .55; cursor: not-allowed; }

/* 主题列表行 */
.oj-topic-list { padding: 6px 0; }
.oj-topic-row { display: flex; align-items: center; gap: 14px; padding: 14px 18px; border-bottom: 1px solid var(--oj-border); transition: background .15s ease; }
.oj-topic-row:last-child { border-bottom: none; }
.oj-topic-row:hover { background: var(--oj-surface-2); }
.oj-topic-main { flex: 1; min-width: 0; }
.oj-topic-title { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: var(--oj-ink); }
.oj-topic-title a { color: inherit; text-decoration: none; }
.oj-topic-title a:hover { color: var(--oj-p); }
.oj-topic-title a:focus-visible { outline: 2px solid var(--oj-p); outline-offset: 2px; border-radius: 4px; }
.oj-topic-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 5px; font-size: 13px; color: var(--oj-muted); }
.oj-topic-meta a { color: var(--oj-muted); }
.oj-topic-meta a:hover { color: var(--oj-p); }
.oj-pid { font-family: var(--oj-mono); color: var(--oj-p); background: var(--oj-p-soft); border-radius: 6px; padding: 1px 8px; font-size: 12px; font-weight: 600; }
.oj-meta-dot { color: #cfd6e0; }
.oj-topic-side { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.oj-reply-count { display: inline-flex; align-items: baseline; gap: 4px; color: var(--oj-ink-2); }
.oj-reply-count b { font-size: 16px; font-weight: 700; color: var(--oj-ink); }
.oj-reply-count span { font-size: 12px; color: var(--oj-faint); }
.oj-topic-last { font-size: 12px; color: var(--oj-faint); }

/* 徽章（置顶/热门/锁定） */
.oj-badge { display: inline-flex; align-items: center; border-radius: 6px; padding: 1px 8px; font-size: 12px; font-weight: 600; line-height: 1.5; }
.oj-badge-top { background: #fdecef; color: var(--oj-danger); }
.oj-badge-hot { background: #fff4e0; color: #b9780a; }
.oj-badge-lock { background: var(--oj-surface-2); color: var(--oj-muted); border: 1px solid var(--oj-border); }

/* 分页 */
.oj-pagination { display: flex; justify-content: center; gap: 6px; padding: 14px 18px; flex-wrap: wrap; }
.oj-pagination a { border: 1px solid var(--oj-border); border-radius: var(--oj-r-sm); padding: 6px 12px; color: var(--oj-ink-2); font-size: 13px; text-decoration: none; transition: border-color .15s ease, color .15s ease, background .15s ease; }
.oj-pagination a:hover { border-color: var(--oj-p); color: var(--oj-p); }
.oj-pagination a.active { background: var(--oj-p); border-color: var(--oj-p); color: #fff; font-weight: 600; }
.oj-pagination a.disabled { color: var(--oj-faint); pointer-events: none; }

/* 侧栏 */
.oj-side { display: flex; flex-direction: column; gap: 18px; }
.oj-side .oj-card { padding: 18px; }
.oj-side-title { font-size: 14px; font-weight: 600; color: var(--oj-ink); margin-bottom: 12px; }
.oj-stats { display: flex; gap: 20px; }
.oj-stats .num { font-size: 24px; font-weight: 700; color: var(--oj-ink); line-height: 1.1; }
.oj-stats .lbl { font-size: 12px; color: var(--oj-faint); margin-top: 2px; }
.oj-tips { font-size: 13px; color: var(--oj-muted); background: var(--oj-surface-2); border-radius: var(--oj-r-sm); padding: 12px; line-height: 1.7; }

/* 空态 */
.oj-empty { text-align: center; padding: 46px 20px; }
.oj-empty .icon { font-size: 34px; margin-bottom: 10px; opacity: .8; }
.oj-empty .t { font-size: 16px; font-weight: 600; color: var(--oj-ink); }
.oj-empty .d { font-size: 14px; color: var(--oj-muted); margin: 6px 0 16px; }

/* 帖子页 */
.oj-thread-head { padding: 20px 22px; }
.oj-thread-head h1 { margin: 0; font-size: 21px; font-weight: 700; color: var(--oj-ink); line-height: 1.4; word-break: break-word; }
.oj-thread-meta { display: flex; align-items: center; gap: 8px; margin-top: 10px; font-size: 13px; color: var(--oj-muted); flex-wrap: wrap; }
.oj-thread-actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 18px; border-bottom: 1px solid var(--oj-border); flex-wrap: wrap; }
.oj-avatar { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; color: #fff; font-size: 13px; font-weight: 700; background: var(--c, var(--oj-p)); flex-shrink: 0; user-select: none; }
.oj-avatar-lg { width: 34px; height: 34px; font-size: 15px; }

.oj-replies { padding: 8px 0; }
.oj-reply { display: flex; gap: 12px; padding: 16px 18px; border-bottom: 1px solid var(--oj-border); }
.oj-reply:hover { background: var(--oj-surface-2); }
.oj-reply-body { flex: 1; min-width: 0; }
.oj-reply-head { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
.oj-reply-author { font-size: 14px; font-weight: 600; color: var(--oj-ink); }
.oj-reply-author a { color: inherit; }
.oj-reply-author a:hover { color: var(--oj-p); }
.oj-reply-time { font-size: 12px; color: var(--oj-faint); }
.oj-reply-no { margin-left: auto; font-family: var(--oj-mono); font-size: 12px; color: var(--oj-faint); }
.oj-reply-actions { display: inline-flex; gap: 12px; margin-left: 12px; }
.oj-reply-actions a { font-size: 13px; color: var(--oj-muted); text-decoration: none; }
.oj-reply-actions a:hover { color: var(--oj-p); }
.oj-reply-content { margin-top: 8px; font-size: 14px; color: var(--oj-ink-2); line-height: 1.75; word-break: break-word; }

/* 回复表单 */
.oj-composer { padding: 18px; border-top: 1px solid var(--oj-border); }
.oj-composer label { display: block; font-size: 14px; font-weight: 600; color: var(--oj-ink); margin-bottom: 8px; }
.oj-composer textarea { width: 100%; box-sizing: border-box; border: 1px solid var(--oj-border-2); border-radius: var(--oj-r-sm); padding: 12px; font-size: 14px; line-height: 1.7; color: var(--oj-ink); font-family: inherit; min-height: 120px; resize: vertical; transition: border-color .15s ease, box-shadow .15s ease; }
.oj-composer textarea:focus { outline: none; border-color: var(--oj-p); box-shadow: 0 0 0 3px var(--oj-p-soft); }
.oj-composer .foot { display: flex; justify-content: flex-end; margin-top: 10px; }

/* 发帖表单 */
.oj-form { padding: 22px; }
.oj-form h3 { margin: 0 0 4px; font-size: 20px; font-weight: 700; color: var(--oj-ink); }
.oj-form .sub { font-size: 13px; color: var(--oj-muted); margin-bottom: 18px; }
.oj-form label { display: block; font-size: 14px; font-weight: 600; color: var(--oj-ink); margin: 16px 0 6px; }
.oj-form label .hint { font-weight: 400; color: var(--oj-faint); font-size: 12px; margin-left: 6px; }
.oj-form input[type=text], .oj-form textarea { width: 100%; box-sizing: border-box; border: 1px solid var(--oj-border-2); border-radius: var(--oj-r-sm); padding: 10px 12px; font-size: 14px; color: var(--oj-ink); font-family: inherit; transition: border-color .15s ease, box-shadow .15s ease; }
.oj-form input[type=text]:focus, .oj-form textarea:focus { outline: none; border-color: var(--oj-p); box-shadow: 0 0 0 3px var(--oj-p-soft); }
.oj-form textarea { min-height: 240px; resize: vertical; line-height: 1.7; }
.oj-form .foot { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
</style>

<div class="oj-discuss-page">
  <div class="oj-discuss-hero">
    <h1>讨论版</h1>
    <div class="sub">分享思路，答疑解惑，一起把题做明白。</div>
  </div>
  <?php echo $view_discuss?>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>