<?php $show_title="提交 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>
/* 提交页（左右布局：左侧编辑器，右侧题目信息+操作） */
.oj-sub { max-width: 1120px; margin: 0 auto; }
.oj-card { background: #fff; border: none; border-radius: 10px; box-shadow: 0 4px 14px rgba(15,23,42,.06); }
.oj-sub-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 20px; align-items: start; }

.oj-sub-main .oj-card { padding: 24px; }
.oj-sub-head { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; border-bottom: 1px solid #e6ebf2; padding-bottom: 14px; }
.oj-sub-title { font-size: 20px; font-weight: 600; color: #1f2d3d; }
.oj-sub-problem { font-size: 14px; color: #5f6d85; }
.oj-sub-row { margin-bottom: 16px; }
.oj-sub-label { display: block; font-size: 14px; font-weight: 600; color: #1f2d3d; margin-bottom: 6px; }
#language { border: 1px solid #d7dee8; border-radius: 6px; padding: 8px 12px; font-size: 14px; color: #1f2d3d; background: #fff; }
.oj-editor { border: 1px solid #d7dee8; border-radius: 8px; overflow: hidden; background: #fff; }
#source { width: 100%; min-height: 440px; border: 0 !important; margin: 0; box-sizing: border-box; font-size: 14px; line-height: 1.6; }
textarea#source { padding: 12px; font-family: Consolas, Monaco, "Courier New", monospace; resize: vertical; }
.oj-sample { border: 1px solid #dcdfe6; border-radius: 6px; overflow: hidden; margin-top: 14px; }
.oj-sample-head { background: #f5f7fa; padding: 6px 12px; border-bottom: 1px solid #dcdfe6; font-size: 13px; font-weight: 600; color: #606266; }
.oj-sample pre { margin: 0; padding: 10px 12px; font-family: Consolas, Monaco, "Courier New", monospace; font-size: 13px; line-height: 1.5; color: #1f2d3d; white-space: pre-wrap; word-break: break-all; background: #fff; }
.oj-test-area { margin-top: 14px; }
.oj-test-area textarea { width: 100%; border: 1px solid #d7dee8; border-radius: 6px; padding: 8px 10px; font-family: Consolas, Monaco, monospace; font-size: 13px; box-sizing: border-box; }

.oj-sub-side .oj-card { padding: 16px; position: sticky; top: 76px; }
.oj-sub-side-title { font-size: 16px; font-weight: 600; color: #1f2d3d; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #e6ebf2; }
.oj-info-row { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-bottom: 10px; font-size: 14px; line-height: 1.6; }
.oj-info-row .l { color: #5f6d85; flex-shrink: 0; }
.oj-info-row .v { color: #1f2d3d; text-align: right; font-weight: 500; word-break: break-all; }
.oj-info-row .v a { color: #2f6ee5; }
.oj-sub-btns { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
.oj-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; }
.oj-btn-primary { background: #2f6ee5; color: #fff; box-shadow: 0 4px 14px rgba(47,110,229,.3); }
.oj-btn-primary:hover { background: #1e56c6; color: #fff; }
.oj-btn-ghost { background: #fff; color: #3a4759; border: 1px solid #d7dee8; }
.oj-btn-ghost:hover { border-color: #2f6ee5; color: #2f6ee5; }
.oj-btn-green { background: #18a058; color: #fff; }
.oj-btn-green:hover { color: #fff; opacity: .9; }

@media (max-width: 900px) {
  .oj-sub-grid { grid-template-columns: 1fr; }
  .oj-sub-side .oj-card { position: static; }
}
</style>

<script src="<?php echo $OJ_CDN_URL?>include/checksource.js"></script>
<div class="oj-sub">
  <div class="oj-sub-grid">

    <!-- 左侧：提交表单 -->
    <div class="oj-sub-main">
      <div class="oj-card">
        <div class="oj-sub-head">
          <div class="oj-sub-title">提交代码</div>
          <div class="oj-sub-problem">
            <?php
              if(isset($view_pmeta['title'])&&$view_pmeta['title']!=""){ echo $view_pmeta['title']; }
              else if(isset($id)){ echo "题目 ".$id; }
              else { echo "题目 ".chr($pid+ord('A'))." · 比赛 ".$cid; }
            ?>
          </div>
        </div>

        <form id="frmSolution" action="submit.php" method="post" onsubmit='do_submit()'>
          <?php require_once('./include/set_post_key.php');?>
          <?php if (isset($id)){?>
            <input id="problem_id" type="hidden" value="<?php echo $id?>" name="id">
          <?php }else{ ?>
            <input id="cid" type="hidden" value="<?php echo $cid?>" name="cid">
            <input id="pid" type="hidden" value="<?php echo $pid?>" name="pid">
          <?php }?>

          <div class="oj-sub-row" id="language_span">
            <label class="oj-sub-label" for="language">语言</label>
            <select id="language" name="language" onChange="reloadtemplate($(this).val());">
              <?php
                $lang_count=count($language_ext);
                if(isset($_GET['langmask']))
                  $langmask=$_GET['langmask'];
                else
                  $langmask=$OJ_LANGMASK;
                $lang=(~((int)$langmask))&((1<<($lang_count))-1);
                if(isset($_COOKIE['lastlang'])) $lastlang=intval($_COOKIE['lastlang']);
                else $lastlang=0;
                for($i=0;$i<$lang_count;$i++){
                  if($lang&(1<<$i))
                    echo "<option value=$i ".( $lastlang==$i?"selected":"").">".$language_name[$i]."</option>";
                }
              ?>
            </select>
            <?php if($OJ_VCODE){?>
              <?php echo $MSG_VCODE?>: <input name="vcode" size=4 type="text">
              <img id="vcode" alt="click to change" src="vcode.php" onclick="this.src='vcode.php?'+Math.random()">
            <?php }?>
          </div>

          <label class="oj-sub-label">源代码</label>
          <div class="oj-editor">
            <?php if($OJ_ACE_EDITOR){ ?>
              <pre cols=180 rows=20 id="source"><?php echo htmlentities($view_src,ENT_QUOTES,"UTF-8")?></pre>
              <input type="hidden" id="hide_source" name="source" value=""/>
            <?php }else{ ?>
              <textarea cols=180 rows=20 id="source" name="source"><?php echo htmlentities($view_src,ENT_QUOTES,"UTF-8")?></textarea>
            <?php }?>
          </div>

          <?php if(strlen($view_sample_input)||strlen($view_sample_output)){ ?>
          <div class="oj-sample">
            <div class="oj-sample-head">输入样例</div>
            <pre><?php echo htmlentities($view_sample_input,ENT_QUOTES,"UTF-8")?></pre>
          </div>
          <div class="oj-sample">
            <div class="oj-sample-head">输出样例</div>
            <pre><?php echo htmlentities($view_sample_output,ENT_QUOTES,"UTF-8")?></pre>
          </div>
          <?php } ?>

          <?php if (isset($OJ_TEST_RUN)&&$OJ_TEST_RUN){?>
          <div class="oj-test-area">
            <label class="oj-sub-label"><?php echo $MSG_Input?></label>
            <textarea cols=40 rows=5 id="input_text" name="input_text"><?php echo $view_sample_input?></textarea>
            <label class="oj-sub-label" style="margin-top: 10px;"><?php echo $MSG_Output?></label>
            <textarea cols=10 rows=5 id="out" name="out" disabled="true">SHOULD BE:
<?php echo $view_sample_output?></textarea>
          </div>
          <?php } ?>

          <?php if (isset($OJ_ENCODE_SUBMIT)&&$OJ_ENCODE_SUBMIT){?>
          <div style="margin-top: 16px;">
            <button type="button" class="oj-btn oj-btn-green" title="WAF gives you reset ? try this." onclick="encoded_submit();">Encoded <?php echo $MSG_SUBMIT?></button>
            <input type="hidden" id="encoded_submit_mark" name="reverse2" value="reverse"/>
          </div>
          <?php }?>
        </form>
      </div>
    </div>

    <!-- 右侧：题目信息 + 操作 -->
    <div class="oj-sub-side">
      <div class="oj-card">
        <div class="oj-sub-side-title">题目信息</div>
        <div class="oj-info-row"><span class="l">题号</span><span class="v"><a href="<?php if(isset($id)) echo "problem.php?id=$id"; else echo "problem.php?cid=$cid&pid=$pid"; ?>"><?php if(isset($id)) echo $id; else echo chr($pid+ord('A')); ?></a></span></div>
        <div class="oj-info-row"><span class="l">标题</span><span class="v"><?php echo isset($view_pmeta['title'])?htmlentities($view_pmeta['title'],ENT_QUOTES,"UTF-8"):""; ?></span></div>
        <div class="oj-info-row"><span class="l">上传者</span><span class="v"><span id="creator"></span></span></div>
        <div class="oj-info-row"><span class="l">时间限制</span><span class="v"><?php echo isset($view_pmeta['time_limit'])?$view_pmeta['time_limit']:0; ?> S</span></div>
        <div class="oj-info-row"><span class="l">内存限制</span><span class="v"><?php echo isset($view_pmeta['memory_limit'])?$view_pmeta['memory_limit']:0; ?> MB</span></div>
        <div class="oj-info-row"><span class="l">提交 / 通过</span><span class="v"><?php echo isset($view_pmeta['submit'])?$view_pmeta['submit']:0; ?> / <?php echo isset($view_pmeta['accepted'])?$view_pmeta['accepted']:0; ?></span></div>
        <?php if(isset($view_pmeta['source'])&&$view_pmeta['source']){ ?>
        <div class="oj-info-row"><span class="l">来源</span><span class="v"><?php echo htmlentities($view_pmeta['source'],ENT_QUOTES,"UTF-8"); ?></span></div>
        <?php } ?>

        <div class="oj-sub-btns">
          <a class="oj-btn oj-btn-ghost" href="<?php if(isset($id)) echo "problem.php?id=$id"; else echo "problem.php?cid=$cid&pid=$pid"; ?>">返回题目</a>
          <button id="Submit" type="button" class="oj-btn oj-btn-primary" onclick="$('#frmSolution').submit();">提交代码</button>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
$(document).ready(function(){
  $("#creator").load("problem-ajax.php?pid=<?php echo isset($problem_id)?intval($problem_id):0; ?>");
});
</script>
<script>
var sid=0;
var i=0;
var using_blockly=false;
var judge_result=[<?php
foreach($judge_result as $result){
echo "'$result',";
}
?>''];
function print_result(solution_id)
{
sid=solution_id;
$("#out").load("status-ajax.php?tr=1&solution_id="+solution_id);
}
function fresh_result(solution_id)
{
	var tb=window.document.getElementById('result');
	if(solution_id==undefined){
		tb.innerHTML="Vcode Error!";		
		if($("#vcode")!=null) $("#vcode").click();
		return ;
	}
	sid=solution_id;
	var xmlhttp;
	if (window.XMLHttpRequest)
	{// code for IE7+, Firefox, Chrome, Opera, Safari
	xmlhttp=new XMLHttpRequest();
	}
	else
	{// code for IE6, IE5
	xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange=function()
	{
	if (xmlhttp.readyState==4 && xmlhttp.status==200)
	{
	var r=xmlhttp.responseText;
	var ra=r.split(",");
	var loader="<img width=18 src=image/loader.gif>";
	var tag="span";
	if(ra[0]<4) tag="span disabled=true";
	else tag="a";
	{
		if(ra[0]==11)
		
		tb.innerHTML="<"+tag+" href='ceinfo.php?sid="+solution_id+"' class='badge badge-info' target=_blank>"+judge_result[ra[0]]+"</"+tag+">";
		else
		tb.innerHTML="<"+tag+" href='reinfo.php?sid="+solution_id+"' class='badge badge-info' target=_blank>"+judge_result[ra[0]]+"</"+tag+">";
	}
	if(ra[0]<4)tb.innerHTML+=loader;
	tb.innerHTML+="Memory:"+ra[1]+"kb&nbsp;&nbsp;";
	tb.innerHTML+="Time:"+ra[2]+"ms";
	if(ra[0]<4)
	window.setTimeout("fresh_result("+solution_id+")",2000);
	else{
		window.setTimeout("print_result("+solution_id+")",2000);
		count=1;
	}
	}
	}
	xmlhttp.open("GET","status-ajax.php?solution_id="+solution_id,true);
	xmlhttp.send();
}
function getSID(){
var ofrm1 = document.getElementById("testRun").document;
var ret="0";
if (ofrm1==undefined)
{
ofrm1 = document.getElementById("testRun").contentWindow.document;
var ff = ofrm1;
ret=ff.innerHTML;
}
else
{
var ie = document.frames["frame1"].document;
ret=ie.innerText;
}
return ret+"";
}
var count=0;
	 
function encoded_submit(){

      var mark="<?php echo isset($id)?'problem_id':'cid';?>";
        var problem_id=document.getElementById(mark);

	if(typeof(editor) != "undefined")
		$("#hide_source").val(editor.getValue());
        if(mark=='problem_id')
                problem_id.value='<?php if(isset($id)) echo $id?>';
        else
                problem_id.value='<?php if(isset($cid))echo $cid?>';

        document.getElementById("frmSolution").target="_self";
        document.getElementById("encoded_submit_mark").name="encoded_submit";
        var source=$("#source").val();
	if(typeof(editor) != "undefined") {
		source=editor.getValue();
        	$("#hide_source").val(encode64(utf16to8(source)));
	}else{
        	$("#source").val(encode64(utf16to8(source)));
	}
        document.getElementById("frmSolution").submit();
}

function do_submit(){
	if(using_blockly) 
		 translate();
	if(typeof(editor) != "undefined"){ 
		$("#hide_source").val(editor.getValue());
	}
	var mark="<?php echo isset($id)?'problem_id':'cid';?>";
	var problem_id=document.getElementById(mark);
	if(mark=='problem_id')
	problem_id.value='<?php if (isset($id))echo $id?>';
	else
	problem_id.value='<?php if (isset($cid))echo $cid?>';
	document.getElementById("frmSolution").target="_self";
	document.getElementById("frmSolution").submit();
}
var handler_interval;
function do_test_run(){
	if( handler_interval) window.clearInterval( handler_interval);
	var loader="<img width=18 src=image/loader.gif>";
	var tb=window.document.getElementById('result');
        var source=$("#source").val();
	if(typeof(editor) != "undefined") {
		source=editor.getValue();
        	$("#hide_source").val(source);
	}
	if(source.length<10) return alert("too short!");
	if(tb!=null)tb.innerHTML=loader;

	var mark="<?php echo isset($id)?'problem_id':'cid';?>";
	var problem_id=document.getElementById(mark);
	problem_id.value=-problem_id.value;
	document.getElementById("frmSolution").target="testRun";
	$.post("submit.php?ajax",$("#frmSolution").serialize(),function(data){fresh_result(data);});
  	$("#Submit").prop('disabled', true);
  	$("#TestRub").prop('disabled', true);
	problem_id.value=-problem_id.value;
	count=20;
	handler_interval= window.setTimeout("resume();",1000);
}
function resume(){
	count--;
	var s=$("#Submit")[0];
	var t=$("#TestRub")[0];
	if(count<0){
		s.disabled=false;
		if(t!=null)t.disabled=false;
		s.value="<?php echo $MSG_SUBMIT?>";
		if(t!=null)t.value="<?php echo $MSG_TR?>";
		if( handler_interval) window.clearInterval( handler_interval);
		if($("#vcode")!=null) $("#vcode").click();
	}else{
		s.value="<?php echo $MSG_SUBMIT?>("+count+")";
		if(t!=null)t.value="<?php echo $MSG_TR?>("+count+")";
		window.setTimeout("resume();",1000);
	}
}
function switchLang(lang){
   var langnames=new Array("c_cpp","c_cpp","pascal","java","ruby","sh","python","php","perl","csharp","objectivec","vbscript","scheme","c_cpp","c_cpp","lua","javascript","golang");
   editor.getSession().setMode("ace/mode/"+langnames[lang]);

}
function reloadtemplate(lang){
   console.log("lang="+lang);
   document.cookie="lastlang="+lang+"; path=/; SameSite=Lax";
   var url=window.location.href;
   var i=url.indexOf("sid=");
   if(i!=-1) url=url.substring(0,i-1);
   switchLang(lang);
}
function openBlockly(){
   $("#frame_source").hide();
   $("#TestRun").hide();
   $("#language")[0].scrollIntoView();
   $("#language").val(6).hide();
   $("#language_span").hide();
   $("#EditAreaArroundInfos_source").hide();
   $('#blockly').html('<iframe name=\'frmBlockly\' width=90% height=580 src=\'blockly/demos/code/index.html\'></iframe>'); 
  $("#blockly_loader").hide();
  $("#transrun").show();
  $("#Submit").prop('disabled', true);
  using_blockly=true;
  
}
function translate(){
  var blockly=$(window.frames['frmBlockly'].document);
  var tb=blockly.find('td[id=tab_python]');
  var python=blockly.find('pre[id=content_python]');
  tb.click();
  blockly.find('td[id=tab_blocks]').click();
  if(typeof(editor) != "undefined") editor.setValue(python.text());
  else $("#source").val(python.text());
  $("#language").val(6);
 
}
function loadFromBlockly(){
 translate();
 do_test_run();
  $("#frame_source").hide();
}
</script>
<script language="Javascript" type="text/javascript" src="<?php echo $OJ_CDN_URL?>include/base64.js"></script>
<?php if($OJ_ACE_EDITOR){ ?>
<script src="<?php echo $OJ_CDN_URL?>ace/ace.js"></script>
<script src="<?php echo $OJ_CDN_URL?>ace/ext-language_tools.js"></script>
<script>
    ace.require("ace/ext/language_tools");
    var editor = ace.edit("source");
    editor.setTheme("ace/theme/chrome");
    switchLang($("#language").val());
    editor.setOptions({
	    enableBasicAutocompletion: true,
	    enableSnippets: true,
	    enableLiveAutocompletion: true
    });
   reloadtemplate($("#language").val()); 
     
</script>
<?php }?>

<?php include("template/$OJ_TEMPLATE/footer.php");?>