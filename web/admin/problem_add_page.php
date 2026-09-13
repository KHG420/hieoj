<?php 
  require_once("../include/db_info.inc.php");
  require_once("admin-header.php");
  require_once("../include/set_get_key.php"); // 新增：统一引入getkey，保持和list页面一致
  
  if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'contest_creator']) || isset($_SESSION[$OJ_NAME.'_'.'problem_editor']))) {
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
  }
  
  if(isset($OJ_LANG)){
    require_once("../lang/$OJ_LANG.php");
  }
?>

<title><?php echo $MSG_PROBLEM."-".$MSG_ADD; ?></title>

<!-- 引入和problem_list.php一致的样式文件 -->
<link rel="stylesheet" href="https://cdn.bootcss.com/bootstrap/4.0.0-beta/css/bootstrap.min.css">
<link href="https://cdn.bootcdn.net/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css" rel="stylesheet">
<link href="https://cdn.bootcss.com/font-awesome/5.13.0/css/all.css" rel="stylesheet">
<style>
    .nav > li > a:hover{
        background-color:#01AAED;
    }
    /* 表单样式优化，适配AdminLTE */
    .form-container {
        background: #fff;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .form-group label {
        font-weight: 600;
        margin-bottom: 5px;
    }
    .kindeditor {
        width: 100% !important;
    }
    /* 按钮容器样式：强制一行排列，垂直居中，间距均匀 */
    .btn-group-container {
        display: flex;
        flex-wrap: nowrap; /* 禁止换行 */
        align-items: center; /* 垂直居中 */
        gap: 8px; /* 按钮间距（替代mr-2，更稳定） */
        width: 100%;
    }
    /* 按钮最小宽度，避免文字变长导致变形 */
    .btn-ai {
        min-width: 100px;
    }
</style>

<!-- 模仿problem_list.php的body类 -->
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <!-- 引入导航栏（和problem_list.php一致） -->
    <?php include("navbar.php");?>
    
    <!-- 内容容器 -->
    <div class="content-wrapper">
        <!-- 内容头部 -->
        <div class="content-header">
            <div class="row">
                <div class="col-sm-1">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </div>
                <div class="col-sm-11">
                    <h1 class="m-0"><?php echo $MSG_PROBLEM." ".$MSG_ADD; ?></h1>
                </div>
            </div>
        </div>

        <!-- 主要内容区域 -->
        <section class="content">
            <div class="container-fluid">
                <!-- 题目添加表单容器 -->
                <div class="form-container">
                    <form method=POST action=problem_add.php>
                        <?php require_once("../include/set_post_key.php"); ?>
                        <input type=hidden name=problem_id value="New Problem">
                        
                        <!-- 标题行：核心修改区域 -->
                        <div class="form-group row align-items-center"> <!-- 整行垂直居中 -->
                            <label class="col-sm-2 col-form-label"><?php echo $MSG_TITLE; ?></label>
                            <div class="col-sm-4">
                                <input class="form-control" type=text name='title' id='title' placeholder="<?php echo $MSG_TITLE; ?>">
                            </div>
                            <!-- 扩大按钮列宽度：从col-sm-2改为col-sm-6，确保有足够空间 -->
                            <div class="col-sm-6">
                                <!-- 按钮容器：使用flex布局强制一行排列 -->
                                <div class="btn-group-container">
                                    <input class="btn btn-success" type=submit value='<?php echo '保存'; ?>' name=submit>
                                    <!-- 添加最小宽度类，避免AI按钮文字变长换行 -->
                                    <input class='btn btn-primary btn-ai' id='ai_bt' type=button value='AI一下' onclick='ai_gen()'>
                                    <input class='btn btn-danger' type=reset value='<?php echo $MSG_RESET; ?>'>
                                </div>
                            </div>
                        </div>

                        <!-- 时间/内存限制 -->
                        <div class="form-group row">
                            <label class="col-sm-2 col-form-label"><?php echo $MSG_LIMIT; ?></label>
                            <div class="col-sm-5">
                                <?php echo $MSG_Time_Limit?>
                                <input class="form-control w-25 d-inline-block" type=number min="0.001" max="300" step="0.001" name=time_limit value=1> sec
                            </div>
                            <div class="col-sm-5">
                                <?php echo $MSG_Memory_Limit?>
                                <input class="form-control w-25 d-inline-block" type=number min="1" max="2048" step="1" name=memory_limit value=128> MiB
                            </div>
                        </div>

                        <!-- 题目描述 -->
                        <div class="form-group">
                            <label><?php echo $MSG_Description."(<64kB)"; ?></label>
                            <textarea class="kindeditor" rows=13 name=description cols=80><span class='md auto_select'>&nbsp;
&nbsp;</span></textarea>
                        </div>

                        <!-- 输入格式 -->
                        <div class="form-group">
                            <label><?php echo $MSG_Input."(<64kB)"; ?></label>
                            <textarea class="kindeditor" rows=13 name=input cols=80><span class='md'>
</span></textarea>
                        </div>

                        <!-- 输出格式 -->
                        <div class="form-group">
                            <label><?php echo $MSG_Output."(<64kB)"; ?></label>
                            <textarea class="kindeditor" rows=13 name=output cols=80><span class='md'>
</span></textarea>
                        </div>

                        <!-- 样例输入 -->
                        <div class="form-group">
                            <label><?php echo $MSG_Sample_Input."(<64kB)"; ?></label>
                            <textarea class="form-control" rows=6 name=sample_input></textarea>
                        </div>

                        <!-- 样例输出 -->
                        <div class="form-group">
                            <label><?php echo $MSG_Sample_Output."(<64kB)"; ?></label>
                            <textarea class="form-control" rows=6 name=sample_output></textarea>
                        </div>

                        <!-- 测试输入 -->
                        <div class="form-group">
                            <label><?php echo $MSG_Test_Input." (".$MSG_HELP_MORE_TESTDATA_LATER.")"; ?></label>
                            <textarea class="form-control" rows=6 name=test_input></textarea>
                        </div>

                        <!-- 测试输出 -->
                        <div class="form-group">
                            <label><?php echo $MSG_Test_Output." (".$MSG_HELP_MORE_TESTDATA_LATER.")"; ?></label>
                            <textarea class="form-control" rows=6 name=test_output></textarea>
                        </div>

                        <!-- 提示 -->
                        <div class="form-group">
                            <label><?php echo $MSG_HINT."(<64kB)"; ?></label>
                            <textarea class="kindeditor" rows=13 name=hint cols=80><span class='md'>
</span></textarea>
                        </div>

                        <!-- SPJ设置 -->
                        <div class="form-group">
                            <label><?php echo $MSG_SPJ; ?></label>
                            <div class="form-check">
                                <input class="form-check-input" type=radio name=spj value='0' checked>
                                <label class="form-check-label"><?php echo $MSG_NJ?> 更多测试数据，在题目添加后补充。</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type=radio name=spj value='1'>
                                <label class="form-check-label"><?php echo $MSG_SPJ?> (<?php echo $MSG_HELP_SPJ; ?>)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type=radio name=spj value='2'>
                                <label class="form-check-label"><?php echo $MSG_RTJ?>(用于选择判断填空题，用法见<a target='_blank' href='http://hustoj.com'>hustoj.com</a>)</label>
                            </div>
                        </div>

                        <!-- 题目来源 -->
                        <div class="form-group">
                            <label><?php echo $MSG_SOURCE; ?></label>
                            <?php $source=pdo_query("select source from problem order by problem_id desc limit 1"); 
                                  $source=!empty($source)&&isset($source[0])?$source[0][0]:"";
                            ?>
                            <textarea name=source class="form-control" rows=1><?php echo htmlentities($source,ENT_QUOTES,'UTF-8') ?></textarea>
                        </div>

                        <!-- 关联比赛 -->
                        <div class="form-group">
                            <label><?php echo $MSG_CONTEST; ?></label>
                            <select name=contest_id class="form-control w-50">
                                <?php
                                $sql="SELECT `contest_id`,`title` FROM `contest` WHERE `start_time`>NOW() order by `contest_id`";
                                $result=pdo_query($sql);
                                echo "<option value=''>none</option>";
                                if (count($result)>0) {
                                  foreach ($result as $row) {
                                    echo "<option value='{$row['contest_id']}'>{$row['contest_id']} {$row['title']}</option>";
                                  }
                                }
                                ?>
                            </select>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- 引入必要的JS文件 -->
<script src='../template/bs3/jquery.min.js' ></script>
<script src="<?php echo $OJ_CDN_URL."/template/bs3/"?>marked.min.js"></script>
<?php include_once("kindeditor.php") ; // 调整位置，确保JS加载顺序正确 ?>

<script>
// 侧边栏激活脚本（和problem_list.php保持一致）
document.getElementById('menu3').classList.remove("menu");
document.getElementById('menu3').classList.add("menu-open");
// 根据实际菜单ID调整，这里假设题目管理的菜单ID是a15（a14是problem_list）
if(document.getElementById('a15')){
    document.getElementById('a15').classList.remove("bg-primary");
    document.getElementById('a15').classList.add("bg-secondary");
}

// AI生成题目函数（保留原有功能）
function ai_gen(filename) {
    // 按钮状态控制
    let oldval = $('#ai_bt').val();
    $('#ai_bt').val('AI思考中...请稍候...');
    $('#ai_bt').prop('disabled', true);
    let title = $('#title').val();

    $.ajax({
        url: '../<?php echo $OJ_AI_API_URL?>',
        type: 'GET',
        data: { title: title },
        success: function(data) {
            console.log("AI返回原始数据：", data);
            let parsedData = null;

            // 标题为空时直接填充原始数据
            if(title === ""){
                $('#title').val(data);
                console.log("标题为空，已直接填充原始数据到标题框");
            } else {
                // 优先尝试解析预设格式
                if (data.indexOf('###TITLE###') !== -1) {
                    console.log("检测到分隔符格式");
                    let sections = data.split('###');
                    parsedData = {};
                    for (let i = 0; i < sections.length; i++) {
                        let section = sections[i].trim();
                        if (section.startsWith('TITLE###')) {
                            parsedData.title = section.replace('TITLE###', '').trim();
                        } else if (section.startsWith('DESCRIPTION###')) {
                            parsedData.description = section.replace('DESCRIPTION###', '').trim();
                        } else if (section.startsWith('INPUT###')) {
                            parsedData.input = section.replace('INPUT###', '').trim();
                        } else if (section.startsWith('OUTPUT###')) {
                            parsedData.output = section.replace('OUTPUT###', '').trim();
                        } else if (section.startsWith('SAMPLE_INPUT###')) {
                            parsedData.sample_input = section.replace('SAMPLE_INPUT###', '').trim();
                        } else if (section.startsWith('SAMPLE_OUTPUT###')) {
                            parsedData.sample_output = section.replace('SAMPLE_OUTPUT###', '').trim();
                        } else if (section.startsWith('HINT###')) {
                            parsedData.hint = section.replace('HINT###', '').trim();
                        }
                    }
                } else {
                    // 尝试JSON解析
                    try {
                        console.log("尝试解析JSON格式");
                        parsedData = JSON.parse(data);
                        console.log("JSON解析成功");
                    } catch (e) {
                        console.log("JSON解析失败，尝试从纯文本提取题目信息");
                        parsedData = extractProblemDataFromText(data);
                    }
                }

                // 填充表单数据
                if(parsedData && typeof parsedData === 'object') {
                    console.log("=== 解析后的数据 ===");
                    console.log(parsedData);
                    
                    if(parsedData.title) {
                        $('#title').val(parsedData.title);
                        console.log("填充title:", parsedData.title);
                    }
                    
                    // 处理description
                    if(parsedData.description) {
                        let descContent = "<span class='md'>" + parsedData.description + "</span>";
                        $("textarea[name='description']").val(descContent);
                        try {
                            if(typeof KindEditor !== 'undefined') {
                                let editor = KindEditor.instances[0];
                                if(editor) {
                                    editor.html(descContent);
                                }
                            }
                        } catch(e) {
                            console.log("KindEditor设置失败,已使用textarea方式:", e);
                        }
                    }
                    
                    // 处理input
                    if(parsedData.input) {
                        let inputContent = "<span class='md'>" + parsedData.input + "</span>";
                        $("textarea[name='input']").val(inputContent);
                        try {
                            if(typeof KindEditor !== 'undefined') {
                                let editor = KindEditor.instances[1];
                                if(editor) {
                                    editor.html(inputContent);
                                }
                            }
                        } catch(e) {
                            console.log("KindEditor input设置失败:", e);
                        }
                    }
                    
                    // 处理output
                    if(parsedData.output) {
                        let outputContent = "<span class='md'>" + parsedData.output + "</span>";
                        $("textarea[name='output']").val(outputContent);
                        try {
                            if(typeof KindEditor !== 'undefined') {
                                let editor = KindEditor.instances[2];
                                if(editor) {
                                    editor.html(outputContent);
                                }
                            }
                        } catch(e) {
                            console.log("KindEditor output设置失败:", e);
                        }
                    }
                    
                    if(parsedData.sample_input) {
                        $("textarea[name='sample_input']").val(parsedData.sample_input);
                    }
                    if(parsedData.sample_output) {
                        $("textarea[name='sample_output']").val(parsedData.sample_output);
                    }
                    
                    // 处理hint
                    if(parsedData.hint) {
                        let hintContent = "<span class='md'>" + parsedData.hint + "</span>";
                        $("textarea[name='hint']").val(hintContent);
                        try {
                            if(typeof KindEditor !== 'undefined') {
                                let editor = KindEditor.instances[3];
                                if(editor) {
                                    editor.html(hintContent);
                                }
                            }
                        } catch(e) {
                            console.log("KindEditor hint设置失败:", e);
                        }
                    }
                }
            }
            
            // 恢复按钮状态
            $('#ai_bt').prop('disabled', false);
            $('#ai_bt').val('AI一下');
        },
        error: function(xhr, status, error) {
            console.error("AJAX请求失败：", status, error);
            $('#ai_bt').val('获取数据失败');
            $('#ai_bt').prop('disabled', false);
        }
    });

//    // 纯文本题目内容提取函数
//    function extractProblemDataFromText(text) {
//        let result = {};
//        let lines = text.split('\n').map(line => line.trim()).filter(line => line);
//
//        // 提取标题
//        let titleLine = lines.find(line => line.startsWith('# '));
//        if (titleLine) {
//            result.title = titleLine.replace(/^#\s+/, '').trim();
//        }
//
//        // 提取描述
//        let descStart = lines.findIndex(line => line.startsWith('## 题目背景'));
//        let descEnd = lines.findIndex(line => line.startsWith('## 输入格式'));
//        if (descStart !== -1 && descEnd !== -1) {
//            result.description = lines.slice(descStart, descEnd).join('\n').replace(/^##\s+/gm, '').trim();
//        }
//
//        // 提取输入格式
//        let inputStart = lines.findIndex(line => line.startsWith('## 输入格式'));
//        let inputEnd = lines.findIndex(line => line.startsWith('## 输出格式'));
//        if (inputStart !== -1 && inputEnd !== -1) {
//            result.input = lines.slice(inputStart, inputEnd).join('\n').replace(/^##\s+/gm, '').trim();
//        }
//
//        // 提取输出格式
//        let outputStart = lines.findIndex(line => line.startsWith('## 输出格式'));
//        let outputEnd = lines.findIndex(line => line.startsWith('## 样例'));
//        if (outputStart !== -1 && outputEnd !== -1) {
//            result.output = lines.slice(outputStart, outputEnd).join('\n').replace(/^##\s+/gm, '').trim();
//        }
//
//        // 提取样例输入/输出
//        let sampleStart = lines.findIndex(line => line.startsWith('## 样例'));
//        let sampleEnd = lines.findIndex(line => line.startsWith('## 数据范围'));
//        if (sampleStart !== -1) {
//            let sampleLines = lines.slice(sampleStart, sampleEnd !== -1 ? sampleEnd : lines.length);
//            let sampleInputMatch = sampleLines.join('\n').match(/输入：\s*```(?:text)?\s*(.*?)\s*```/s);
//            if (sampleInputMatch) {
//                result.sample_input = sampleInputMatch[1].trim();
//            }
//            let sampleOutputMatch = sampleLines.join('\n').match(/输出：\s*```(?:text)?\s*(.*?)\s*```/s);
//            if (sampleOutputMatch) {
//                result.sample_output = sampleOutputMatch[1].trim();
//            }
//        }
//
//        // 提取提示
//        let hintStart = lines.findIndex(line => line.startsWith('## 提示'));
//        if (hintStart !== -1) {
//            result.hint = lines.slice(hintStart).join('\n').replace(/^##\s+/gm, '').trim();
//        }
//
//        return result;
    //    }
    //
    // 特殊符号替换辅助函数：处理LaTeX符号转普通符号
function replaceSpecialSymbols(text) {
    if (!text) return text; // 空值直接返回
    // 定义替换规则（按优先级排序，先替换命令再去$）
    const replaceRules = [
        { regex: /\\le/g, replacement: '≤' },    // \le → ≤
        { regex: /\\ge/g, replacement: '≥' },    // \ge → ≥（扩展支持，可选）
        { regex: /\\times/g, replacement: '×' }, // \times → ×（扩展支持，可选）
        { regex: /\$/g, replacement: '' },       // 去掉所有$符号（如$n$→n）
    ];

    // 依次应用替换规则
    let processedText = text;
    replaceRules.forEach(rule => {
        processedText = processedText.replace(rule.regex, rule.replacement);
    });
    return processedText;
}

// 纯文本题目内容提取函数（已集成符号替换）
function extractProblemDataFromText(text) {
    let result = {};
    let lines = text.split('\n').map(line => line.trim()).filter(line => line);

    // 提取标题
    let titleLine = lines.find(line => line.startsWith('# '));
    if (titleLine) {
        result.title = replaceSpecialSymbols(titleLine.replace(/^#\s+/, '').trim());
    }

    // 提取描述（题目背景+题目描述）
    let descStart = lines.findIndex(line => line.startsWith('## 题目背景'));
    let descEnd = lines.findIndex(line => line.startsWith('## 输入格式'));
    if (descStart !== -1 && descEnd !== -1) {
        const descRaw = lines.slice(descStart, descEnd).join('\n').replace(/^##\s+/gm, '').trim();
        result.description = replaceSpecialSymbols(descRaw); // 替换特殊符号
    }

    // 提取输入格式
    let inputStart = lines.findIndex(line => line.startsWith('## 输入格式'));
    let inputEnd = lines.findIndex(line => line.startsWith('## 输出格式'));
    if (inputStart !== -1 && inputEnd !== -1) {
        const inputRaw = lines.slice(inputStart, inputEnd).join('\n').replace(/^##\s+/gm, '').trim();
        result.input = replaceSpecialSymbols(inputRaw); // 替换特殊符号
    }

    // 提取输出格式
    let outputStart = lines.findIndex(line => line.startsWith('## 输出格式'));
    let outputEnd = lines.findIndex(line => line.startsWith('## 样例'));
    if (outputStart !== -1 && outputEnd !== -1) {
        const outputRaw = lines.slice(outputStart, outputEnd).join('\n').replace(/^##\s+/gm, '').trim();
        result.output = replaceSpecialSymbols(outputRaw); // 替换特殊符号
    }

    // 提取样例输入/输出
    let sampleStart = lines.findIndex(line => line.startsWith('## 样例'));
    let sampleEnd = lines.findIndex(line => line.startsWith('## 数据范围'));
    if (sampleStart !== -1) {
        let sampleLines = lines.slice(sampleStart, sampleEnd !== -1 ? sampleEnd : lines.length);
        let sampleInputMatch = sampleLines.join('\n').match(/输入：\s*```(?:text)?\s*(.*?)\s*```/s);
        if (sampleInputMatch) {
            result.sample_input = replaceSpecialSymbols(sampleInputMatch[1].trim());
        }
        let sampleOutputMatch = sampleLines.join('\n').match(/输出：\s*```(?:text)?\s*(.*?)\s*```/s);
        if (sampleOutputMatch) {
            result.sample_output = replaceSpecialSymbols(sampleOutputMatch[1].trim());
        }
    }

    // 提取数据范围（新增：原代码漏了这部分，补充后也做符号替换）
    let rangeStart = lines.findIndex(line => line.startsWith('## 数据范围'));
    let rangeEnd = lines.findIndex(line => line.startsWith('## 提示'));
    if (rangeStart !== -1) {
        const rangeRaw = lines.slice(rangeStart, rangeEnd !== -1 ? rangeEnd : lines.length)
            .join('\n').replace(/^##\s+/gm, '').trim();
        result.dataRange = replaceSpecialSymbols(rangeRaw); // 替换特殊符号
    }

    // 提取提示
    let hintStart = lines.findIndex(line => line.startsWith('## 提示'));
    if (hintStart !== -1) {
        const hintRaw = lines.slice(hintStart).join('\n').replace(/^##\s+/gm, '').trim();
        result.hint = replaceSpecialSymbols(hintRaw); // 替换特殊符号
    }

    return result;
}
}
</script>
</body>
</html>
