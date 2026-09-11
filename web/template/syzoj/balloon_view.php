<?php $show_title=(isset($view_user)?htmlentities($view_user,ENT_QUOTES,"UTF-8"):(isset($MSG_BALLOON)?$MSG_BALLOON:"Balloon"))." - $OJ_NAME"; ?>
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
.oj-page { max-width: 880px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }
.oj-card { background: var(--oj-surface); border: 1px solid var(--oj-border); border-radius: var(--oj-r); box-shadow: var(--oj-shadow); }
.oj-card-head { padding: 16px 20px; border-bottom: 1px solid var(--oj-border); font-size: 16px; font-weight: 600; color: var(--oj-ink); }
.oj-card-body { padding: 20px; }
.oj-ticket { display: flex; flex-direction: column; gap: 10px; }
.oj-ticket-row { display: flex; gap: 12px; font-size: 15px; }
.oj-ticket-row .lbl { color: var(--oj-muted); width: 120px; flex-shrink: 0; }
.oj-ticket-row .val { color: var(--oj-ink); font-weight: 600; word-break: break-all; }
.oj-color-dot { display: inline-block; width: 14px; height: 14px; border-radius: 50%; margin-right: 8px; vertical-align: -2px; border: 1px solid var(--oj-border-2); }
.oj-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: var(--oj-r-sm); font-size: 14px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; font-family: inherit; }
.oj-btn-primary { background: var(--oj-p); color: #fff; }
.oj-btn-primary:hover { background: var(--oj-p-h); color: #fff; }
.oj-btn-ghost { background: var(--oj-surface); color: var(--oj-ink-2); border-color: var(--oj-border-2); }
.oj-btn-ghost:hover { border-color: var(--oj-p); color: var(--oj-p); }
.oj-actions { display: flex; gap: 10px; margin-top: 18px; flex-wrap: wrap; }
.oj-map { margin-top: 0; }
.oj-map table { border-collapse: collapse; width: 100%; font-size: 14px; }
.oj-map td, .oj-map th { border: 1px solid var(--oj-border); padding: 8px 12px; text-align: center; color: var(--oj-ink); }
.oj-map th { background: var(--oj-surface-2); color: var(--oj-muted); font-weight: 600; }
.oj-map .oj-map-hint { font-size: 13px; color: var(--oj-muted); background: var(--oj-surface-2); border-radius: var(--oj-r-sm); padding: 10px 14px; }
</style>
<div class="padding">
<div class="oj-page">
    <div class="oj-card">
        <div class="oj-card-head"><?php echo (isset($MSG_BALLOON)?$MSG_BALLOON:"Balloon"); ?> Ticket</div>
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
                <div class="oj-ticket-row">
                    <span class="lbl"><?php echo (isset($MSG_PROBLEM)?$MSG_PROBLEM:"Problem"); ?></span>
                    <span class="val"><?php echo isset($view_pid)?htmlentities((isset($PID[$view_pid])?$PID[$view_pid]:$view_pid),ENT_QUOTES,"UTF-8"):""; ?></span>
                </div>
                <div class="oj-ticket-row">
                    <span class="lbl">Balloon Color</span>
                    <span class="val">
                        <span class="oj-color-dot" style="background-color:<?php echo isset($ball_color[$view_pid])?htmlentities($ball_color[$view_pid],ENT_QUOTES,"UTF-8"):"transparent"; ?>;"></span>
                        <?php echo isset($ball_name[$view_pid])?htmlentities($ball_name[$view_pid],ENT_QUOTES,"UTF-8"):""; ?>
                    </span>
                </div>
            </div>
            <div class="oj-actions">
                <button class="oj-btn oj-btn-ghost" type="button" onclick="window.print();"><?php echo (isset($MSG_PRINTER)?$MSG_PRINTER:"Print"); ?></button>
                <?php require_once(dirname(__FILE__)."/../../include/set_post_key.php");?>
                <form method="post" action="balloon.php?cid=<?php echo isset($cid)?intval($cid):0; ?>" style="display:inline;margin:0">
                  <input type="hidden" name="postkey" value="<?php echo htmlentities($_SESSION[$OJ_NAME.'_'.'postkey'],ENT_QUOTES,'UTF-8')?>">
                  <input type="hidden" name="id" value="<?php echo isset($id)?intval($id):0; ?>">
                  <button type="submit" class="oj-btn oj-btn-primary"><?php echo (isset($MSG_PRINT_DONE)?$MSG_PRINT_DONE:"Done"); ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="oj-card">
        <div class="oj-card-head">Seat Map</div>
        <div class="oj-card-body oj-map">
            <?php echo isset($view_map)?$view_map:""; ?>
        </div>
    </div>
</div>
</div>
<script>
(function(){
    var user = <?php echo json_encode(isset($view_user)?$view_user:"",JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
    var color = <?php echo json_encode(isset($ball_color[$view_pid])?$ball_color[$view_pid]:"#2f6ee5",JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
    if(window.jQuery && user){
        window.jQuery(".oj-map td").filter(function(){ return window.jQuery(this).text().indexOf(user) !== -1; }).css("background-color", color);
    }
})();
</script>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
