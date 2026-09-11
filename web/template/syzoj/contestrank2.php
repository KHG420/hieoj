<?php $show_title="Contest RankList -- ".$title." - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<?php
// 奖牌线（保持原 metal() 逻辑：金 5%+1 / 银 20%+1 / 铜 45%+1）
$cr2_total=0;
for($i=1;$i<=$user_cnt;$i++){
        $cr2_nick=isset($U[$i]->nick)?(string)$U[$i]->nick:'';
        if(isset($U[$i]->solved) && $U[$i]->solved>0 && substr($cr2_nick,0,1)!=='*') $cr2_total++;
}
$cr2_gold=(int)($cr2_total*0.05)+1;
$cr2_silver=(int)($cr2_total*0.20)+1;
$cr2_bronze=(int)($cr2_total*0.45)+1;
$cr2_running=(time()<$end_time);
?>
<style>
.cr2-page{ width:100%; padding:16px 24px 70px; }
.cr2-head{ margin-bottom:14px; display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:10px; }
.cr2-title{ font-size:22px; font-weight:700; margin:0 0 6px; color:#1c2733; }
.cr2-meta{ color:#7a8694; font-size:13px; margin-bottom:6px; }
.cr2-meta b{ color:#4a5560; font-weight:600; }
.cr2-controls{ display:none; position:sticky; top:49px; z-index:60; align-items:center; gap:10px; background:#fff; border:1px solid #e4e6ea; border-radius:8px; padding:10px 14px; margin-bottom:12px; box-shadow:0 2px 8px rgba(15,23,42,.08); }
.cr2-controls .ui.button{ margin:0; }
.cr2-controls input[type=range]{ flex:1; min-width:120px; }
.cr2-time{ font-variant-numeric:tabular-nums; font-size:13px; color:#4a5560; white-space:nowrap; }
.cr2-scroll{ overflow-x:auto; max-height:calc(100vh - 320px); min-height:260px; border:1px solid #e4e6ea; border-radius:8px; background:#fff; }
table#rank{ margin:0 !important; border-collapse:separate; border-spacing:0; }
table#rank thead th{ position:sticky; top:0; z-index:5; background:#f6f8fa; white-space:nowrap; padding:11px 8px; font-size:13px; font-weight:600; color:#47525e; border-bottom:2px solid #dde1e7; }
table#rank thead th a{ color:#2c6fb0; }
table#rank tbody td{ padding:9px 6px; font-size:13px; white-space:nowrap; border-bottom:1px solid #eef1f4; }
table#rank tbody tr:hover td{ background:#f7fafc; }
table#rank.replaying thead{ pointer-events:none; opacity:.6; }
.cr2-user a{ color:#2563ab; font-weight:600; }
.cr2-nick{ color:#333; }
.cr2-solved a{ font-weight:700; color:#0f6e3f; }
.cr2-penalty{ font-variant-numeric:tabular-nums; color:#555; }
.rank-chip{ display:inline-block; min-width:32px; text-align:center; padding:2px 9px; border-radius:11px; font-weight:700; font-size:12px; line-height:18px; }
.chip-gold{ background:#f6c453; color:#5b4300; }
.chip-silver{ background:#e3e7ee; color:#3a4350; }
.chip-bronze{ background:#d98e4e; color:#fff; }
.chip-plain{ background:transparent; color:#6b7683; }
.cr2-cell{ text-align:center; font-variant-numeric:tabular-nums; }
.cr2-ac{ background:#d9f2e0 !important; color:#0b6e4f; }
.cr2-ac .att{ color:#3d8b5f; font-size:11px; }
.cr2-wa{ background:#fde2e2 !important; color:#c0392b; }
.cr2-fb{ position:relative; background:#d6e6ff !important; }
.cr2-fb::after{ content:"首A"; position:absolute; top:1px; left:3px; font-size:10px; line-height:1; color:#1d4ed8; }
.cr2-empty{ padding:70px 0; text-align:center; color:#9aa4af; }
.cr2-chartpanel{ margin-top:16px; background:#fff; border:1px solid #e4e6ea; border-radius:8px; padding:16px; }
.cr2-chartpanel h3{ font-size:15px; margin:0 0 10px; color:#1f2d3d; }
.cr2-chart{ position:relative; height:300px; }
</style>
<div class="cr2-page">
  <div class="cr2-head">
    <div>
      <h1 class="cr2-title"><?php echo $MSG_CONTEST.$MSG_RANKLIST?> -- <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></h1>
      <div class="cr2-meta">
        <b><?php echo $MSG_START_TIME?></b> <?php echo date("Y-m-d H:i:s",$start_time)?>
        &nbsp;·&nbsp; <b>结束</b> <?php echo date("Y-m-d H:i:s",$end_time)?>
        &nbsp;·&nbsp; <b><?php echo $pid_cnt?></b> 题 &nbsp;·&nbsp; <b><?php echo $user_cnt?></b> 人
        &nbsp; <span class="ui tiny <?php echo $cr2_running?'green':'grey'?> label"><?php echo $cr2_running?'Running':'Ended'?></span>
      </div>
      <div>
        <a class="ui small blue button" href="contestrank.xls.php?cid=<?php echo $cid?>"><i class="download icon"></i>Download</a>
<?php if(!empty($solution_json)){ ?>
        <button id="replay-start" class="ui small purple button" type="button"><i class="play icon"></i>Replay</button>
<?php } ?>
      </div>
    </div>
  </div>
  <div class="cr2-controls" id="replay-controls">
    <button id="btn-play" class="ui small green button" type="button"><i class="pause icon"></i>暂停</button>
    <button id="btn-restart" class="ui small button" type="button"><i class="undo icon"></i>重来</button>
    <select id="speed-sel" style="padding:6px 8px;border:1px solid #cfd8e3;border-radius:6px;">
      <option value="30">×0.5</option>
      <option value="60" selected>×1</option>
      <option value="120">×2</option>
      <option value="300">×5</option>
      <option value="600">×10</option>
    </select>
    <input type="range" id="replay-slider" min="0" max="1" value="0" step="1">
    <span class="cr2-time" id="replay-time">00:00:00 / 00:00:00</span>
    <button id="btn-exit" class="ui small basic button" type="button">退出回放</button>
  </div>
<?php if($user_cnt>0){ ?>
  <div class="cr2-scroll">
    <table id="rank" class="ui very basic center aligned table">
      <thead>
        <tr>
          <th style="width:56px;">#</th>
          <th>User</th>
          <th>Nick</th>
          <th>Solved</th>
          <th>Penalty</th>
<?php for($i=0;$i<$pid_cnt;$i++) echo '<th class="cr2-p"><a href="problem.php?cid='.$cid.'&amp;pid='.$i.'" title="Problem '.chr(65+$i).'">'.chr(65+$i).'</a></th>'; ?>
        </tr>
      </thead>
      <tbody>
<?php
$cr2_rank=1;
for($i=1;$i<=$user_cnt;$i++){
        $uuid=isset($U[$i]->user_id)?$U[$i]->user_id:'';
        $nick=isset($U[$i]->nick)?(string)$U[$i]->nick:'';
        $is_star=(substr($nick,0,1)==='*');
        $usolved=isset($U[$i]->solved)?intval($U[$i]->solved):0;
        $r=$cr2_rank++;
        $chip='chip-plain';
        if(!$is_star && $usolved>0){
                if($r==1 || $r<=$cr2_gold) $chip='chip-gold';
                elseif($r<=$cr2_silver) $chip='chip-silver';
                elseif($r<=$cr2_bronze) $chip='chip-bronze';
        }
        echo '<tr'.($is_star?' data-star="1"':'').'>';
        echo '<td>'.($is_star?'*':'<span class="rank-chip '.$chip.'">'.$r.'</span>').'</td>';
        echo '<td class="cr2-user"'.((isset($_GET['user_id'])&&$_GET['user_id']===$uuid)?' style="background:#fff8dc;"':'').'><a href="userinfo.php?user='.urlencode($uuid).'">'.htmlspecialchars($uuid,ENT_QUOTES,'UTF-8').'</a></td>';
        echo '<td class="cr2-nick">'.htmlspecialchars($nick,ENT_QUOTES,'UTF-8').'</td>';
        echo '<td class="cr2-solved"><a href="status.php?user_id='.urlencode($uuid).'&amp;cid='.$cid.'">'.$usolved.'</a></td>';
        echo '<td class="cr2-penalty">'.sec2str(isset($U[$i]->time)?$U[$i]->time:0).'</td>';
        for($j=0;$j<$pid_cnt;$j++){
                $wa=isset($U[$i]->p_wa_num[$j])?intval($U[$i]->p_wa_num[$j]):0;
                $acsec=isset($U[$i]->p_ac_sec[$j])?intval($U[$i]->p_ac_sec[$j]):0;
                $isfb=(isset($first_blood[$j])&&$first_blood[$j]===$uuid);
                if($acsec>0){
                        $cls='cr2-cell cr2-ac'.($isfb?' cr2-fb':'');
                        echo '<td class="'.$cls.'"'.($isfb?' title="First Blood"':'').'>'.sec2str($acsec);
                        if($wa>0) echo '<span class="att">(-'.$wa.')</span>';
                        echo '</td>';
                }else if($wa>0){
                        echo '<td class="cr2-cell cr2-wa">-'.$wa.'</td>';
                }else{
                        echo '<td class="cr2-cell" style="color:#c6cdd4;">·</td>';
                }
        }
        echo '</tr>';
}
?>
      </tbody>
    </table>
  </div>
<?php }else{ ?>
  <div class="cr2-scroll"><div class="cr2-empty">暂无选手提交</div></div>
<?php } ?>
  <div class="cr2-chartpanel">
    <h3>排位趋势图（Top 8，纵轴为名次，谁超谁一目了然）</h3>
    <div class="cr2-chart"><canvas id="rank-trend"></canvas></div>
  </div>
</div>
<script type="text/javascript" src="include/jquery.tablesorter.js"></script>
<script type="text/javascript" src="template/<?php echo $OJ_TEMPLATE?>/css/Chart.min.js"></script>
<script type="text/javascript">
var CID=<?php echo intval($cid)?>;
var PP=<?php echo intval($pid_cnt)?>;
var SELF=<?php echo isset($_GET['user_id'])?json_encode($_GET['user_id']):'null'?>;
var solutions=<?php echo empty($solution_json)?'[]':str_replace('</','<\/',$solution_json); ?>;
function escapeHtml(s){
  return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;");
}
function sec2str(sec){
  sec=Math.max(0,parseInt(sec)||0);
  var h=Math.floor(sec/3600),m=Math.floor(sec%3600/60),s=sec%60;
  function p(x){ return (x<10?"0":"")+x; }
  return p(h)+":"+p(m)+":"+p(s);
}
function str2sec(str){
  var a=String(str).split(":");
  return (parseInt(a[0])||0)*3600+(parseInt(a[1])||0)*60+(parseInt(a[2])||0);
}
var MAXT = solutions.length ? (parseInt(solutions[solutions.length-1]["in_date"])||0) : 0;

$(function(){
  $.tablesorter.addParser({
    id:'punish',
    is:function(s){ return false; },
    format:function(s){
      var v=s.toLowerCase().replace(/:/g,'').replace(/\(-/,'.').replace(/\)/,'');
      v=parseFloat('0'+v);
      return v>1?v:v+Number.MAX_VALUE-1;
    },
    type:'numeric'
  });
  var hdrs={4:{sorter:'punish'}};
  for(var i=0;i<PP;i++) hdrs[5+i]={sorter:'punish'};
  $("#rank").tablesorter({ headers:hdrs });
});

// ==================== 榜单回放引擎 ====================
var board={}, replayIdx=0, simTime=0, playing=false, lastTick=0, speed=60;
function makeRow(s){
  var uid=s["user_id"]?String(s["user_id"]):"";
  var nick=s["nick"]?String(s["nick"]):"";
  var tr=document.createElement("tr");
  if(nick.charAt(0)==="*") tr.setAttribute("data-star","1");
  var html="<td></td>"
    +"<td class='cr2-user'><a href='userinfo.php?user="+encodeURIComponent(uid)+"'>"+escapeHtml(uid)+"</a></td>"
    +"<td class='cr2-nick'>"+escapeHtml(nick)+"</td>"
    +"<td class='cr2-solved'>0</td>"
    +"<td class='cr2-penalty'>00:00:00</td>";
  for(var i=0;i<PP;i++) html+="<td class='cr2-cell' style='color:#c6cdd4;'>·</td>";
  tr.innerHTML=html;
  board[uid]={el:tr,solved:0,penalty:0,star:nick.charAt(0)==="*"};
  $("#rank tbody").append(tr);
  return board[uid];
}
function renderRanks(){
  var rows=$("#rank tbody tr");
  var total=0;
  rows.each(function(){ if((parseInt(this.cells[3].textContent)||0)>0) total++; });
  var g=Math.floor(total*0.05)+1,s2=Math.floor(total*0.20)+1,b=Math.floor(total*0.45)+1;
  var r=0;
  rows.each(function(){
    r++;
    var td=this.cells[0];
    if(this.getAttribute("data-star")==="1"){ td.innerHTML="*"; return; }
    var ac=parseInt(this.cells[3].textContent)||0;
    var cls="chip-plain";
    if(ac>0){
      if(r===1||r<=g) cls="chip-gold";
      else if(r<=s2) cls="chip-silver";
      else if(r<=b) cls="chip-bronze";
    }
    td.innerHTML="<span class='rank-chip "+cls+"'>"+r+"</span>";
  });
}
function resortRows(){
  var tb=$("#rank tbody")[0];
  if(!tb) return;
  var rows=Array.prototype.slice.call(tb.rows);
  rows.sort(function(a,b){
    var sa=parseInt(a.cells[3].textContent)||0,sb=parseInt(b.cells[3].textContent)||0;
    if(sa!==sb) return sb-sa;
    return str2sec(a.cells[4].textContent)-str2sec(b.cells[4].textContent);
  });
  rows.forEach(function(r){ tb.appendChild(r); });
  renderRanks();
}
function applySolution(s){
  var uid=s["user_id"]?String(s["user_id"]):"";
  var row=board[uid]||makeRow(s);
  var col=5+parseInt(s["num"]);
  if(isNaN(col)||col<5||col>=5+PP) return;
  var td=row.el.cells[col];
  if(!td) return;
  if(parseInt(s["result"])===4){
    if(td.getAttribute("data-done")) return;
    td.setAttribute("data-done","1");
    var t=Math.max(0,parseInt(s["in_date"])||0);
    var wa=parseInt(td.getAttribute("data-wa")||"0");
    td.innerHTML=sec2str(t)+(wa>0?"<span class='att'>(-"+wa+")</span>":"");
    td.className="cr2-cell cr2-ac";
    row.solved++;
    row.el.cells[3].innerHTML="<a href='status.php?user_id="+encodeURIComponent(uid)+"&cid="+CID+"'>"+row.solved+"</a>";
    row.penalty+=t+wa*1200;
    row.el.cells[4].innerHTML=sec2str(row.penalty);
    resortRows();
  }else{
    if(td.getAttribute("data-done")) return;
    var w=parseInt(td.getAttribute("data-wa")||"0")+1;
    td.setAttribute("data-wa",String(w));
    td.className="cr2-cell cr2-wa";
    td.innerHTML="-"+w;
  }
}
function applyAllUpTo(t){
  while(replayIdx<solutions.length){
    var ts=parseInt(solutions[replayIdx]["in_date"])||0;
    if(ts>t) break;
    applySolution(solutions[replayIdx]);
    replayIdx++;
  }
}
function rebuildTo(t){
  replayIdx=0; board={};
  $("#rank tbody").empty();
  $("#rank").addClass("replaying");
  simTime=Math.max(0,Math.min(MAXT,t));
  applyAllUpTo(simTime);
  updateHud();
}
function updateHud(){
  $("#replay-slider").val(simTime);
  $("#replay-time").text(sec2str(simTime)+" / "+sec2str(MAXT));
  var pct=MAXT>0?Math.round(simTime*100/MAXT):0;
  if(playing){
    $("#btn-play").removeClass("green").addClass("orange");
    $("#btn-play").html("<i class='pause icon'></i>暂停");
  }else{
    $("#btn-play").removeClass("orange").addClass("green");
    $("#btn-play").html("<i class='play icon'></i>"+(simTime>=MAXT&&replayIdx>=solutions.length?"重播":"继续"));
  }
  if(simTime>=MAXT&&replayIdx>=solutions.length){
    $("#btn-play").removeClass("green orange").addClass("grey");
    $("#btn-play").html("<i class='flag icon'></i>已完成");
  }
}
function tick(){
  if(!playing) return;
  var now=Date.now();
  var dt=(now-lastTick)/1000; lastTick=now;
  simTime=Math.min(MAXT, simTime+dt*speed);
  applyAllUpTo(simTime);
  updateHud();
  if(simTime>=MAXT&&replayIdx>=solutions.length){ playing=false; updateHud(); return; }
  setTimeout(tick,50);
}
function startPlay(fromZero){
  if(fromZero){ rebuildTo(0); }
  else if(replayIdx>=solutions.length){ rebuildTo(0); }
  playing=true;
  lastTick=Date.now();
  updateHud();
  tick();
}
function pausePlay(){ playing=false; updateHud(); }

// ==================== 控件 ====================
$(function(){
  if(!solutions||!solutions.length) return;
  $("#replay-slider").attr("max",MAXT);
  $("#replay-start").on("click",function(){
    $("#replay-controls").css("display","flex");
    startPlay(true);
  });
  $("#btn-play").on("click",function(){
    if(playing){ pausePlay(); }
    else if(simTime>=MAXT&&replayIdx>=solutions.length){ startPlay(true); }
    else startPlay(false);
  });
  $("#btn-restart").on("click",function(){ startPlay(true); });
  $("#speed-sel").on("change",function(){ speed=parseFloat(this.value)||60; });
  $("#replay-slider").on("input",function(){
    pausePlay();
    simTime=parseInt(this.value)||0;
    $("#replay-time").text(sec2str(simTime)+" / "+sec2str(MAXT));
  });
  $("#replay-slider").on("change",function(){
    rebuildTo(parseInt(this.value)||0);
  });
  $("#btn-exit").on("click",function(){ location.reload(); });
});

// ==================== 排位趋势图 ====================
function computeTrend(){
  if(!solutions||!solutions.length) return null;
  var st={}, events=[];
  for(var i=0;i<solutions.length;i++){
    var s=solutions[i];
    var uid=s["user_id"]?String(s["user_id"]):"";
    if(!uid) continue;
    var num=parseInt(s["num"]); var t=parseInt(s["in_date"])||0;
    if(!st[uid]){
      var nk=s["nick"]?String(s["nick"]):"";
      st[uid]={solved:0,penalty:0,wa:{},nick:nk,star:nk.charAt(0)==="*"};
    }
    var u=st[uid];
    if(parseInt(s["result"])===4){
      var w=u.wa[num]||0;
      u.solved++; u.penalty+=t+w*1200;
      events.push({t:t,uid:uid,solved:u.solved,penalty:u.penalty});
    }else{
      u.wa[num]=(u.wa[num]||0)+1;
    }
  }
  var all=[];
  for(var k in st){ if(!st[k].star) all.push(k); }
  if(!all.length) return null;
  var ranked=all.slice().sort(function(a,b){
    return st[b].solved-st[a].solved || st[a].penalty-st[b].penalty;
  });
  var tracked=ranked.slice(0,8);
  if(SELF && st[SELF] && !st[SELF].star && tracked.indexOf(SELF)<0) tracked.push(SELF);
  var cur={}; all.forEach(function(u){ cur[u]={solved:0,penalty:0}; });
  var series={}; tracked.forEach(function(u){ series[u]=[]; });
  function rankOf(u){
    var c=cur[u], r=1;
    for(var j=0;j<all.length;j++){
      var o=cur[all[j]];
      if(o.solved>c.solved || (o.solved===c.solved && o.penalty<c.penalty)) r++;
    }
    return r;
  }
  for(var i=0;i<events.length;i++){
    var ev=events[i];
    cur[ev.uid]={solved:ev.solved,penalty:ev.penalty};
    for(var j=0;j<tracked.length;j++){
      series[tracked[j]].push({x:ev.t,y:rankOf(tracked[j])});
    }
  }
  var palette=['#2f6ee5','#d03050','#18a058','#f0a020','#8b5cf6','#0ea5e9','#f59e0b','#14b8a6','#6366f1','#ef4444'];
  var datasets=tracked.map(function(u,idx){
    var nm=(st[u].nick&&st[u].nick.charAt(0)!=='*')?st[u].nick:u;
    return {label:nm,data:series[u],borderColor:palette[idx%palette.length],backgroundColor:palette[idx%palette.length],borderWidth:2,pointRadius:1.5,fill:false,tension:0.1};
  });
  return datasets;
}
$(function(){
  var d=computeTrend();
  if(!d||!d.length) return;
  var rtx=document.getElementById('rank-trend');
  if(!rtx||typeof Chart==='undefined') return;
  new Chart(rtx,{
    type:'line',
    data:{datasets:d},
    options:{
      responsive:true,maintainAspectRatio:false,
      legend:{position:'right',labels:{usePointStyle:true,boxWidth:8,fontSize:11}},
      tooltips:{mode:'index',intersect:false,callbacks:{title:function(items){ if(items&&items.length) return "时间 "+sec2str(items[0].xLabel); return ""; },label:function(item){ return item.datasetLabel+"：第 "+item.yLabel+" 名"; }}},
      scales:{
        yAxes:[{ticks:{reverse:true,min:1,precision:0,stepSize:1},gridLines:{color:'#e6ebf2'},scaleLabel:{display:true,labelString:'名次'}}],
        xAxes:[{type:'linear',ticks:{callback:function(v){ return sec2str(v); },maxTicksLimit:12},gridLines:{color:'#e6ebf2'},scaleLabel:{display:true,labelString:'比赛时间'}}]
      }
    }
  });
});
</script>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
