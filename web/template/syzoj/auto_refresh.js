// syzoj 模板状态页自动刷新（修正列索引：结果=5，内存=6，时间=7，提交时间=10，judger=11）
var i = 0;
var interval = 800;

var editorial_prompt_solution = 0;
function show_editorial_prompt(solution_id) {
    var problems = typeof editorial_prompt_problems === 'object' ? editorial_prompt_problems : {};
    var problem = problems[solution_id];
    var prompt = document.getElementById('editorial-reward-prompt');
    if (!problem || !prompt || Number(solution_id) <= editorial_prompt_solution) return;
    document.getElementById('editorial-reward-problem').textContent = 'P' + problem;
    document.getElementById('editorial-reward-link').href = 'solutions.php?problem_id=' + problem + '&write=1';
    prompt.hidden = false;
    editorial_prompt_solution = Number(solution_id);
}

function auto_refresh() {
	interval = 800;
	var tb = window.document.getElementById('result-tab');
	if (!tb || !tb.rows) return;
	var rows = tb.rows;
	for (var i=rows.length-1; i>0; i--) {
		if (!rows[i].cells[5]) continue;
		var result = $(rows[i].cells[5]).find("span[result]").attr("result");
		if (result == null) continue;
		rows[i].cells[5].className = "td_result";
		var sid = rows[i].cells[0].innerHTML;
        if (Number(result) === 4) show_editorial_prompt(sid);
		if (result<4) {
			window.setTimeout("fresh_result("+sid+")",interval);
		}
	}
}

function findRow(solution_id) {
	var tb = window.document.getElementById('result-tab');
	if (!tb) return null;
	var rows = tb.rows;
	for (var i=1; i<rows.length; i++) {
		var cell = rows[i].cells[0];
		if (cell && cell.innerHTML==solution_id)
			return rows[i];
	}
	return null;
}

function fresh_result(solution_id) {
	var xmlhttp;
	if (window.XMLHttpRequest) {// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	}
	else {// code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}

	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState==4 && xmlhttp.status==200) {
			var row = findRow(solution_id);
			if (!row) return;
			var r = xmlhttp.responseText;
			var ra = r.split(",");
			ra[0] = parseInt(ra[0]);
			var loader = "<img width=18 src=image/loader.gif>";
			if (row.cells[6]) row.cells[6].innerHTML = ra[1];
			if (row.cells[7]) row.cells[7].innerHTML = ra[2];
			if (ra[3]!="none" && row.cells[11])
				row.cells[11].innerHTML = ra[3];

			if (ra[0]<4) {
				if (-1==row.cells[5].innerHTML.indexOf("loader")) {
			 		row.cells[5].innerHTML += loader;
				}
				interval *= 1.5;
				window.setTimeout("fresh_result("+solution_id+")",interval);
			}
			else {
				switch (ra[0]) {
					case 4:
                        show_editorial_prompt(solution_id);
						row.cells[5].innerHTML = "<a href=reinfo.php?sid="+solution_id+" class='"+judge_color[ra[0]]+"'>"+judge_result[ra[0]]+"</a>";
						break;
					case 5:
					case 6:
					case 7:
					case 8:
					case 9:
					case 10:
						row.cells[5].innerHTML = "<a href=reinfo.php?sid="+solution_id+" class='"+judge_color[ra[0]]+"'>"+judge_result[ra[0]]+" AC:"+ra[4].trim()+"%</a>";
						break;
					case 11:
						row.cells[5].innerHTML = "<a href=ceinfo.php?sid="+solution_id+" class='"+judge_color[ra[0]]+"'>"+judge_result[ra[0]]+"</a>";
						break;
					default:
						row.cells[5].innerHTML = "<span class='"+judge_color[ra[0]]+"'>"+judge_result[ra[0]]+"</span>";
				}
				auto_refresh();
			}
		}
	}
	xmlhttp.open("GET","status-ajax.php?solution_id="+solution_id,true);
	xmlhttp.send();
}

var hj_ss = "<select class='http_judge form-control' length='2' name='result'>";

for (var i=0; i<10; i++) {
  hj_ss += "	<option value='"+i+"'>"+judge_result[i]+" </option>";
}

hj_ss += "</select>";
hj_ss += "<input name='manual' type='hidden'>";
hj_ss += "<input class='http_judge form-control' size=5 title='输入判定原因与提示' name='explain' type='text'>";
hj_ss += "<input type='button' class='http_judge btn' name='manual' value='确定' onclick='http_judge(this)' >";

$(".http_judge_form").append(hj_ss);

auto_refresh();

$(".td_result").mouseover(function () {
  $(this).find("form").show(600);
  var sid = $(this).find("span[class=original]").attr("sid");
  $(this).find("span[class=original]").load("status-ajax.php?q=user_id&solution_id="+sid);
});

$(".http_judge_form").hide();

function http_judge(btn) {
  var sid = $(btn).parent()[0].children[0].value;
  $.post("admin/problem_judge.php",$(btn).parent().serialize(),function(data,textStatus) {
    if(textStatus=="success")window.setTimeout("fresh_result("+sid+")",1000);
	})
  return false;
}
