<?php $show_title=(isset($view_user)?htmlentities($view_user,ENT_QUOTES,"UTF-8"):(isset($MSG_PRINTER)?$MSG_PRINTER:"Printer"))." - $OJ_NAME"; ?>
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
.oj-ticket { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
.oj-ticket-row { display: flex; gap: 12px; font-size: 15px; }
.oj-ticket-row .lbl { color: var(--oj-muted); width: 120px; flex-shrink: 0; }
.oj-ticket-row .val { color: var(--oj-ink); font-weight: 600; word-break: break-all; }
.oj-badge { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.oj-badge-pending { background: rgba(240,160,32,.14); color: #b9780a; }
.oj-badge-done { background: rgba(24,160,88,.12); color: var(--oj-success); }
.oj-pre { margin: 0; background: var(--oj-surface-2); border: 1px solid var(--oj-border); border-radius: var(--oj-r-sm); padding: 14px 16px; font-family: var(--oj-mono); font-size: 14px; line-height: 1.7; color: var(--oj-ink-2); white-space: pre-wrap; word-break: break-word; }
.oj-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: var(--oj-r-sm); font-size: 14px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; font-family: inherit; }
.oj-btn-primary { background: var(--oj-p); color: #fff; }
.oj-btn-primary:hover { background: var(--oj-p-h); color: #fff; }
.oj-btn-ghost { background: var(--oj-surface); color: var(--oj-ink-2); border-color: var(--oj-border-2); }
.oj-btn-ghost:hover { border-color: var(--oj-p); color: var(--oj-p); }
.oj-actions { display: flex; gap: 10px; margin-top: 18px; flex-wrap: wrap; }
</style>
<div class="padding">
<div class="oj-page">
    <div class="oj-card">
        <div class="oj-card-head"><?php echo (isset($MSG_PRINTER)?$MSG_PRINTER:"Print"); ?> #<?php echo isset($id)?htmlentities($id,ENT_QUOTES,"UTF-8"):""; ?></div>
        <div class="oj-card-body">
            <div class="oj-ticket">
                <div class="oj-ticket-row">
                    <span class="lbl"><?php echo (isset($MSG_USER_ID)?$MSG_USER_ID:"User ID"); ?></span>
                    <span class="val"><?php echo htmlentities(isset($view_user)?str_replace("
","
",$view_user):"",ENT_QUOTES,"UTF-8"); ?></span>
                </div>
                <div class="oj-ticket-row">
                    <span class="lbl"><?php echo (isset($MSG_SCHOOL)?$MSG_SCHOOL:"School"); ?></span>
                    <span class="val"><?php echo htmlentities(isset($view_school)?str_replace("
","
",$view_school):"",ENT_QUOTES,"UTF-8"); ?></span>
                </div>
                <div class="oj-ticket-row">
                    <span class="lbl"><?php echo (isset($MSG_NICK)?$MSG_NICK:"Nick"); ?></span>
                    <span class="val"><?php echo htmlentities(isset($view_nick)?str_replace("
","
",$view_nick):"",ENT_QUOTES,"UTF-8"); ?></span>
                </div>
                <?php if(isset($view_status)){ ?>
                <div class="oj-ticket-row">
                    <span class="lbl"><?php echo (isset($MSG_STATUS)?$MSG_STATUS:"Status"); ?></span>
                    <span class="val"><span class="oj-badge <?php echo ($view_status==1||$view_status==="1")?"oj-badge-done":"oj-badge-pending"; ?>"><?php echo htmlentities($view_status,ENT_QUOTES,"UTF-8"); ?></span></span>
                </div>
                <?php } ?>
            </div>
            <pre class="oj-pre"><?php echo htmlentities(isset($view_content)?str_replace("
","
",$view_content):"",ENT_QUOTES,"UTF-8"); ?></pre>
            <div class="oj-actions">
                <button class="oj-btn oj-btn-ghost" type="button" onclick="window.print();"><?php echo (isset($MSG_PRINTER)?$MSG_PRINTER:"Print"); ?></button>
                <?php require_once(dirname(__FILE__)."/../../include/set_post_key.php");?>
                <form method="post" action="printer.php" style="display:inline;margin:0">
                  <input type="hidden" name="postkey" value="<?php echo htmlentities($_SESSION[$OJ_NAME.'_'.'postkey'],ENT_QUOTES,'UTF-8')?>">
                  <input type="hidden" name="id" value="<?php echo isset($id)?intval($id):0; ?>">
                  <button type="submit" class="oj-btn oj-btn-primary"><?php echo (isset($MSG_PRINT_DONE)?$MSG_PRINT_DONE:"Done"); ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
