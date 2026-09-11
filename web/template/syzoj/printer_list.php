<?php $show_title=(isset($MSG_PRINTER)?$MSG_PRINTER:"Printer")." - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<script>
    setTimeout(function(){ window.location.href='printer.php'; }, 10000);
</script>
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
.oj-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 18px; border-bottom: 1px solid var(--oj-border); }
.oj-toolbar form { display: flex; align-items: center; gap: 8px; margin: 0; }
.oj-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: var(--oj-r-sm); font-size: 14px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; font-family: inherit; }
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
</style>
<div class="padding">
<div class="oj-page">
    <div class="oj-card">
        <div class="oj-toolbar">
            <span style="font-size:16px;font-weight:600;color:var(--oj-ink);"><?php echo (isset($MSG_PRINTER)?$MSG_PRINTER:"Print"); ?></span>
            <form action="printer.php" method="post" onsubmit="return confirm('Delete All Tasks?');">
                <input type="hidden" name="clean">
                <button class="oj-btn oj-btn-danger oj-btn-sm" type="submit"><?php echo (isset($MSG_CLEAN)?$MSG_CLEAN:"Clean"); ?></button>
                <?php require_once(dirname(__FILE__)."/../../include/set_post_key.php");?>
            </form>
        </div>
        <?php if(isset($view_printer) && count($view_printer)>0){ ?>
        <table class="oj-table">
            <thead>
            <tr>
                <th style="width:80px;">id</th>
                <th><?php echo (isset($MSG_USER_ID)?$MSG_USER_ID:"User ID"); ?></th>
                <th><?php echo (isset($MSG_STATUS)?$MSG_STATUS:"Status"); ?></th>
                <th style="width:140px;"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($view_printer as $row){ ?>
                <tr>
                    <td><?php echo htmlentities($row[0],ENT_QUOTES,"UTF-8"); ?></td>
                    <td><?php echo htmlentities($row[1],ENT_QUOTES,"UTF-8"); ?></td>
                    <td><?php echo htmlentities($row[2],ENT_QUOTES,"UTF-8"); ?></td>
                    <td><?php echo $row[3]; ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php }else{ ?>
        <div class="oj-empty">
            <div class="t"><?php echo (isset($MSG_PRINTER)?$MSG_PRINTER:"Print"); ?></div>
            <div class="d">No print tasks pending.</div>
        </div>
        <?php } ?>
    </div>
</div>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
