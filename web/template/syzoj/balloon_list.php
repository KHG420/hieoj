<?php $show_title=(isset($MSG_BALLOON)?$MSG_BALLOON:"Balloon")." - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<script>
    setTimeout(function(){ window.location.href='balloon.php?cid=<?php echo isset($cid)?intval($cid):0; ?>'; }, 10000);
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
.oj-page { max-width: 1080px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }
.oj-card { background: var(--oj-surface); border: 1px solid var(--oj-border); border-radius: var(--oj-r); box-shadow: var(--oj-shadow); }
.oj-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 18px; border-bottom: 1px solid var(--oj-border); }
.oj-toolbar form { display: flex; align-items: center; gap: 8px; margin: 0; }
.oj-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: var(--oj-r-sm); font-size: 14px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; font-family: inherit; }
.oj-btn-primary { background: var(--oj-p); color: #fff; }
.oj-btn-primary:hover { background: var(--oj-p-h); color: #fff; }
.oj-btn-ghost { background: var(--oj-surface); color: var(--oj-ink-2); border-color: var(--oj-border-2); }
.oj-btn-ghost:hover { border-color: var(--oj-p); color: var(--oj-p); }
.oj-btn-danger { background: var(--oj-surface); color: var(--oj-danger); border-color: var(--oj-border-2); }
.oj-btn-danger:hover { border-color: var(--oj-danger); color: var(--oj-danger); }
.oj-btn-sm { padding: 6px 12px; font-size: 13px; }
.oj-input { box-sizing: border-box; border: 1px solid var(--oj-border-2); border-radius: var(--oj-r-sm); padding: 7px 10px; font-size: 14px; color: var(--oj-ink); font-family: inherit; width: 120px; }
.oj-input:focus { outline: none; border-color: var(--oj-p); box-shadow: 0 0 0 3px var(--oj-p-soft); }
.oj-empty { text-align: center; padding: 46px 20px; }
.oj-empty .t { font-size: 16px; font-weight: 600; color: var(--oj-ink); }
.oj-empty .d { font-size: 14px; color: var(--oj-muted); margin: 6px 0 16px; }
.oj-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.oj-table th { text-align: left; padding: 10px 14px; background: var(--oj-surface-2); color: var(--oj-muted); font-weight: 600; font-size: 13px; border-bottom: 1px solid var(--oj-border); white-space: nowrap; }
.oj-table td { padding: 10px 14px; border-bottom: 1px solid var(--oj-border); color: var(--oj-ink); vertical-align: middle; }
.oj-table tr:last-child td { border-bottom: none; }
.oj-table tbody tr:hover { background: var(--oj-surface-2); }
.oj-table a { color: var(--oj-p); text-decoration: none; }
.oj-table a:hover { color: var(--oj-p-h); text-decoration: underline; }
/* controller-built bootstrap-ish markup inside cells */
.oj-table .btn { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; line-height: 1.6; text-decoration: none; border: none; cursor: pointer; font-family: inherit; }
.oj-table .btn-success { background: rgba(24,160,88,.12); color: var(--oj-success); }
.oj-table .btn-danger { background: rgba(240,160,32,.14); color: #b9780a; }
.oj-table .btn-info { background: var(--oj-p-soft); color: var(--oj-p); }
.oj-table .btn-primary { background: var(--oj-p); color: #fff; }
.oj-table .btn-primary:hover { background: var(--oj-p-h); }
.oj-table form { display: inline; margin: 0; }
</style>
<div class="padding">
<div class="oj-page">
    <div class="oj-card">
        <div class="oj-toolbar">
            <form action="balloon.php" method="get">
                <span style="font-size:14px;color:var(--oj-muted);"><?php echo (isset($MSG_CONTEST_ID)?$MSG_CONTEST_ID:"Contest ID"); ?>:</span>
                <input class="oj-input" type="text" name="cid" value="<?php echo isset($cid)?intval($cid):''; ?>">
                <button class="oj-btn oj-btn-ghost oj-btn-sm" type="submit"><?php echo (isset($MSG_CHECK)?$MSG_CHECK:"Check"); ?></button>
            </form>
            <form action="balloon.php?cid=<?php echo isset($cid)?intval($cid):0; ?>" method="post" onsubmit="return confirm('Delete All Tasks?');">
                <input type="hidden" name="cid" value="<?php echo isset($cid)?intval($cid):0; ?>">
                <input type="hidden" name="clean">
                <button class="oj-btn oj-btn-danger oj-btn-sm" type="submit"><?php echo (isset($MSG_CLEAN)?$MSG_CLEAN:"Clean"); ?></button>
                <?php require_once(dirname(__FILE__)."/../../include/set_post_key.php");?>
            </form>
        </div>
        <?php if(isset($view_balloon) && count($view_balloon)>0){ ?>
        <table class="oj-table">
            <thead>
            <tr>
                <th style="width:80px;">id</th>
                <th><?php echo (isset($MSG_USER_ID)?$MSG_USER_ID:"User ID"); ?></th>
                <th><?php echo (isset($MSG_COLOR)?$MSG_COLOR:"Color"); ?></th>
                <th><?php echo (isset($MSG_STATUS)?$MSG_STATUS:"Status"); ?></th>
                <th style="width:200px;"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($view_balloon as $row){ ?>
                <tr>
                    <td><?php echo htmlentities($row[0],ENT_QUOTES,"UTF-8"); ?></td>
                    <td><?php echo htmlentities($row[1],ENT_QUOTES,"UTF-8"); ?></td>
                    <td><?php echo $row[2]; ?></td>
                    <td><?php echo $row[3]; ?></td>
                    <td><?php echo $row[4]; ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php }else{ ?>
        <div class="oj-empty">
            <div class="t"><?php echo (isset($MSG_BALLOON)?$MSG_BALLOON:"Balloon"); ?></div>
            <div class="d">No pending balloon tasks.</div>
        </div>
        <?php } ?>
    </div>
</div>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
