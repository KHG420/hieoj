<?php
// Pure validation tests: no database configuration, network, or external framework.
chdir(dirname(__DIR__));
require dirname(__DIR__,2).'/docker/operations/publish-system-editorials.php';

function import_check($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
}
function import_json($value) {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function import_reject($callable, $message) {
    try {
        $callable();
    } catch (DomainException $e) {
        return;
    }
    throw new RuntimeException('应拒绝：'.$message);
}
function import_content($batch, $content) {
    $batch['articles'][0]['content'] = $content;
    $batch['articles'][0]['article_sha256'] = hash('sha256', $content);
    return $batch;
}

$code = "#include <stdio.h>\nint main(void) { puts(\"example\"); return 0; }\n";
$content = str_repeat('算法说明', 30)."\n\n```c\n".$code."```\n";
$valid = array('batch_id'=>'validation-test-001', 'articles'=>array(array(
    'problem_id'=>1000, 'title'=>'系统题解测试', 'content'=>$content,
    'problem_fingerprint'=>str_repeat('a',64), 'article_sha256'=>hash('sha256',$content),
    'code_sha256'=>hash('sha256',$code), 'language'=>'c',
    'validation'=>array('status'=>'passed','case_count'=>1,'code_sha256'=>hash('sha256',$code))
)));
$parsed = system_editorial_validate_input(import_json($valid));
import_check($parsed->articles[0]->problem_id === 1000, '合法对象保留题号');
import_check($parsed->articles[0]->content === $content, '正文原始字节不变');
import_check(!function_exists('pdo_query') && !isset($GLOBALS['dbh']), '纯校验没有加载配置或建立连接');

// Golden value independently calculated with Python's UTF-8 JSON encoder.
$problem = array('problem_id'=>'123','title'=>"题名/Ω\u{2028}\u{2029}",
    'description'=>"<p>中文😀</p>\r\n",'input'=>null,'output'=>'0',
    'sample_input'=>"1\n",'sample_output'=>"2\n",'hint'=>'a/b',
    'source'=>null,'time_limit'=>'2','memory_limit'=>'128','spj'=>'0');
$fingerprint = system_editorial_problem_fingerprint($problem);
import_check($fingerprint === '2c0e9d65d1bbff2733610d23eab9a9d72b4dcce64266151e3d3a3332666bb205',
    '跨语言指纹保留Unicode、斜杠和原换行，并规范空值与数值类型');
$problem['accepted'] = 10;
$problem['submit'] = 20;
import_check(system_editorial_problem_fingerprint($problem) === $fingerprint, '变化统计不使已验证来源失效');
$problem['description'] .= '更新';
import_check(system_editorial_problem_fingerprint($problem) !== $fingerprint, '题面改动使验证来源失效');
$crlfCode = str_replace("\n", "\r\n", $code);
import_check(system_editorial_final_code("```c\r\n".$crlfCode."```\r\n", 'c') === $crlfCode,
    '提取保留参考程序的原始换行字节');
import_reject(function () use ($content) { system_editorial_final_code($content.'正文', 'c'); },
    '围栏之后不能隐藏其他正文或未验证代码');

$boundary = import_json($valid);
$boundary .= str_repeat(' ', SYSTEM_EDITORIAL_MAX_INPUT-strlen($boundary));
import_check(system_editorial_validate_input($boundary)->batch_id === $valid['batch_id'], '恰好3MiB合法输入');
import_reject(function () use ($boundary) { system_editorial_validate_input($boundary.' '); }, '超过3MiB');
$stream = fopen('php://memory', 'w+');
try {
    fwrite($stream, $boundary.' ');
    rewind($stream);
    import_reject(function () use ($stream) { system_editorial_read_input($stream); }, '读取时限制输入上限');
} finally {
    fclose($stream);
}
unset($boundary);

$bad = $valid;
$bad['articles'][0]['article_sha256'] = str_repeat('0',64);
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '正文坏哈希');
$bad = $valid;
$bad['articles'][0]['code_sha256'] = str_repeat('0',64);
$bad['articles'][0]['validation']['code_sha256'] = str_repeat('0',64);
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '代码坏哈希');
$bad = $valid;
unset($bad['articles'][0]['validation']);
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '验证缺失');
$bad = $valid;
$bad['articles'][0]['validation']['case_count'] = 0;
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '没有真实测试用例');
$bad = $valid;
$bad['articles'][0]['validation']['code_sha256'] = str_repeat('0',64);
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '验证记录与代码哈希不一致');
$bad = $valid;
$bad['articles'][] = $bad['articles'][0];
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '重复题号');
$bad = import_content($valid, str_repeat('解',119)."\n\n```c\n".$code."```\n");
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '解释不足120汉字');
$bad = import_content($valid, "```text\n".str_repeat('解',200)."\n```\n\n```c\n".$code."```\n");
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '代码块内汉字不能充当解释');
$bad = import_content($valid, str_repeat('解',120)."\n\n```c\n".$code);
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '参考程序围栏不完整');
$bad = $valid;
$bad['articles'][0]['language'] = 'python';
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '未知语言');
$bad = $valid;
$bad['articles'][0]['language'] = 'cpp17';
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '围栏与语言不一致');
$cpp = str_replace('```c', '```cpp', $content);
$good = import_content($valid, $cpp);
$good['articles'][0]['language'] = 'cpp17';
import_check(system_editorial_validate_input(import_json($good))->articles[0]->language === 'cpp17', '允许cpp17及cpp围栏');
$bad = $valid;
$bad['articles'] = array();
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '空批次');
$bad = $valid;
$bad['articles'] = array();
for ($i=0; $i<26; ++$i) {
    $article = $valid['articles'][0];
    $article['problem_id'] += $i;
    $bad['articles'][] = $article;
}
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '批次超过25题');
$bad = import_content($valid, str_repeat('解',50001));
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '正文超过50000字');
$bad = $valid;
$bad['articles'][0]['title'] = str_repeat('题',121);
import_reject(function () use ($bad) { system_editorial_validate_input(import_json($bad)); }, '标题超过120字');
import_reject(function () { system_editorial_validate_input("{\"batch_id\":\"\xff\"}"); }, '无效UTF8');
import_reject(function () { system_editorial_validate_input('[]'); }, '顶层必须是对象');
echo "system_editorial_import_test: passed\n";
