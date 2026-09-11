<?php $show_title=(isset($MSG_MAIL)?$MSG_MAIL:"Mail")." - $OJ_NAME"; ?>
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
.oj-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: var(--oj-r-sm); font-size: 14px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; font-family: inherit; }
.oj-btn-primary { background: var(--oj-p); color: #fff; }
.oj-btn-primary:hover { background: var(--oj-p-h); color: #fff; }
.oj-btn-ghost { background: var(--oj-surface); color: var(--oj-ink-2); border-color: var(--oj-border-2); }
.oj-btn-ghost:hover { border-color: var(--oj-p); color: var(--oj-p); }
.oj-btn-danger { background: var(--oj-surface); color: var(--oj-danger); border-color: var(--oj-border-2); }
.oj-btn-danger:hover { border-color: var(--oj-danger); color: var(--oj-danger); }
.oj-btn-sm { padding: 6px 12px; font-size: 13px; }
.oj-empty { text-align: center; padding: 46px 20px; }
.oj-empty .t { font-size: 16px; font-weight: 600; color: var(--oj-ink); }
.oj-empty .d { font-size: 14px; color: var(--oj-muted); margin: 6px 0 16px; }
.oj-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.oj-table th { text-align: left; padding: 10px 14px; background: var(--oj-surface-2); color: var(--oj-muted); font-weight: 600; font-size: 13px; border-bottom: 1px solid var(--oj-border); white-space: nowrap; }
.oj-table td { padding: 10px 14px; border-bottom: 1px solid var(--oj-border); color: var(--oj-ink); vertical-align: middle; word-break: break-all; }
.oj-table tr:last-child td { border-bottom: none; }
.oj-table tbody tr:hover { background: var(--oj-surface-2); }
.oj-table a { color: var(--oj-p); text-decoration: none; }
.oj-table a:hover { color: var(--oj-p-h); text-decoration: underline; }
.oj-label { display: block; font-size: 14px; font-weight: 600; color: var(--oj-ink); margin: 14px 0 6px; }
.oj-input { width: 100%; box-sizing: border-box; border: 1px solid var(--oj-border-2); border-radius: var(--oj-r-sm); padding: 9px 12px; font-size: 14px; color: var(--oj-ink); font-family: inherit; }
.oj-input:focus { outline: none; border-color: var(--oj-p); box-shadow: 0 0 0 3px var(--oj-p-soft); }
.oj-textarea { width: 100%; box-sizing: border-box; border: 1px solid var(--oj-border-2); border-radius: var(--oj-r-sm); padding: 12px; font-size: 14px; line-height: 1.7; color: var(--oj-ink); font-family: var(--oj-mono); resize: vertical; }
.oj-textarea:focus { outline: none; border-color: var(--oj-p); box-shadow: 0 0 0 3px var(--oj-p-soft); }
.oj-form-row { display: flex; gap: 16px; flex-wrap: wrap; }
.oj-form-row .oj-field { flex: 1; min-width: 220px; }
.oj-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 18px; border-bottom: 1px solid var(--oj-border); }
.oj-red { color: var(--oj-danger); font-weight: 600; }
.red { color: var(--oj-danger); font-weight: 600; font-size: 12px; margin-left: 4px; }
.oj-meta { font-size: 13px; color: var(--oj-muted); }
.oj-pre { white-space: pre-wrap; word-break: break-word; font-family: var(--oj-mono); font-size: 14px; line-height: 1.7; color: var(--oj-ink-2); margin: 0; background: var(--oj-surface-2); border: 1px solid var(--oj-border); border-radius: var(--oj-r-sm); padding: 14px 16px; }
</style>
<div class="padding">
<div class="oj-page">
<?php if(isset($view_content) && $view_content!==false){ ?>
    <div class="oj-card">
        <div class="oj-card-head"><?php echo htmlentities(isset($view_title)?$view_title:"",ENT_QUOTES,"UTF-8"); ?></div>
        <div class="oj-card-body">
            <div class="oj-meta" style="margin-bottom:10px;"><?php echo (isset($MSG_MAIL)?$MSG_MAIL:"Mail")." #".htmlentities(isset($vid)?$vid:"",ENT_QUOTES,"UTF-8"); ?></div>
            <pre class="oj-pre"><?php echo htmlentities(str_replace("
","
",isset($view_content)?$view_content:""),ENT_QUOTES,"UTF-8"); ?></pre>
        </div>
    </div>
<?php } ?>
    <div class="oj-card">
        <div class="oj-card-head"><?php echo (isset($MSG_MAIL)?$MSG_MAIL:"Mail"); ?></div>
        <div class="oj-card-body">
            <form method="post" action="mail.php">
                <div class="oj-form-row">
                    <div class="oj-field">
                        <label class="oj-label" for="oj-to"><?php echo (isset($MSG_TO)?$MSG_TO:"To"); ?></label>
                        <input class="oj-input" type="text" id="oj-to" name="to_user" size="10" value="<?php echo htmlentities(isset($to_user)?$to_user:"",ENT_QUOTES,"UTF-8"); ?>">
                    </div>
                    <div class="oj-field">
                        <label class="oj-label" for="oj-title"><?php echo (isset($MSG_TITLE)?$MSG_TITLE:"Title"); ?></label>
                        <input class="oj-input" type="text" id="oj-title" name="title" size="20" value="<?php echo htmlentities(isset($title)?$title:"",ENT_QUOTES,"UTF-8"); ?>">
                    </div>
                </div>
                <label class="oj-label" for="oj-content"><?php echo (isset($MSG_CONTENT)?$MSG_CONTENT:"Content"); ?></label>
                <textarea class="oj-textarea" id="oj-content" name="content" rows="10" cols="80"></textarea>
                <div style="display:flex;justify-content:flex-end;margin-top:14px;">
                    <button class="oj-btn oj-btn-primary" type="submit"><?php echo (isset($MSG_SUBMIT)?$MSG_SUBMIT:"Submit"); ?></button>
                </div>
                <?php require_once(dirname(__FILE__)."/../../include/set_post_key.php");?>
            </form>
        </div>
    </div>
    <div class="oj-card">
        <div class="oj-card-head"><?php echo (isset($MSG_MAIL)?$MSG_MAIL:"Mail"); ?> <?php echo (isset($MSG_LIST)?$MSG_LIST:"List"); ?></div>
        <?php if(isset($view_mail) && count($view_mail)>0){ ?>
        <table class="oj-table">
            <thead>
            <tr>
                <th style="width:90px;">ID</th>
                <th><?php echo (isset($MSG_MAIL)?$MSG_MAIL:"Mail"); ?></th>
                <th style="width:180px;"><?php echo (isset($MSG_DATE)?$MSG_DATE:"Date"); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($view_mail as $row){ ?>
                <tr>
                    <td><?php echo $row[0]; ?></td>
                    <td><?php echo $row[1]; ?></td>
                    <td><?php echo htmlentities($row[2],ENT_QUOTES,"UTF-8"); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php }else{ ?>
        <div class="oj-empty">
            <div class="t"><?php echo (isset($MSG_MAIL)?$MSG_MAIL:"Mail"); ?></div>
            <div class="d">No mails.</div>
        </div>
        <?php } ?>
    </div>
</div>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
