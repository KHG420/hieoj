<?php $show_title=(isset($MSG_PRINTER)?$MSG_PRINTER:"Printer")." - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>
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
.oj-page { max-width: 980px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }
.oj-card { background: var(--oj-surface); border: 1px solid var(--oj-border); border-radius: var(--oj-r); box-shadow: var(--oj-shadow); }
.oj-card-head { padding: 16px 20px; border-bottom: 1px solid var(--oj-border); font-size: 16px; font-weight: 600; color: var(--oj-ink); }
.oj-card-body { padding: 20px; }
.oj-label { display: block; font-size: 14px; font-weight: 600; color: var(--oj-ink); margin-bottom: 8px; }
.oj-textarea { width: 100%; box-sizing: border-box; border: 1px solid var(--oj-border-2); border-radius: var(--oj-r-sm); padding: 12px; font-size: 14px; line-height: 1.7; color: var(--oj-ink); font-family: var(--oj-mono); resize: vertical; min-height: 320px; }
.oj-textarea:focus { outline: none; border-color: var(--oj-p); box-shadow: 0 0 0 3px var(--oj-p-soft); }
.oj-hint { font-size: 13px; color: var(--oj-muted); margin-top: 10px; }
.oj-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: var(--oj-r-sm); font-size: 14px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; font-family: inherit; }
.oj-btn-primary { background: var(--oj-p); color: #fff; }
.oj-btn-primary:hover { background: var(--oj-p-h); color: #fff; }
.oj-foot { display: flex; justify-content: flex-end; margin-top: 14px; }
</style>
<div class="padding">
<div class="oj-page">
    <div class="oj-card">
        <div class="oj-card-head"><?php echo (isset($MSG_PRINTER)?$MSG_PRINTER:"Print"); ?></div>
        <div class="oj-card-body">
            <form id="frmSolution" action="printer.php" method="post">
                <label class="oj-label" for="source"><?php echo (isset($MSG_PRINTER)?$MSG_PRINTER:"Print"); ?></label>
                <textarea class="oj-textarea" id="source" name="content" rows="20" cols="180"></textarea>
                <div class="oj-hint"><?php echo (isset($MSG_PRINT_WAITING)?$MSG_PRINT_WAITING:"Please wait for delivery and do not submit duplicate print tasks."); ?></div>
                <div class="oj-foot">
                    <button class="oj-btn oj-btn-primary" type="submit"><?php echo (isset($MSG_PRINTER)?$MSG_PRINTER:"Print"); ?></button>
                </div>
                <?php require_once(dirname(__FILE__)."/../../include/set_post_key.php");?>
            </form>
        </div>
    </div>
</div>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
