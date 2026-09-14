<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../include/my_func.inc.php';
$checks=0;
foreach (array(
 '<img title="sample" src="/upload/image/test.png" alt="image">',
 '<a href="https://link.zhihu.com/?target=https%3A%2F%2Fvjudge.net%2Fcontest%2F518930">题目 link</a>',
 '<p>title, link, database and description</p>',
 '<a href="https://vjudge.net/contest/513329">练习</a>'
) as $input) {
 if (RemoveXSS($input)!==$input) throw new RuntimeException('Safe news content was corrupted: '.$input);
 $checks++;
}
foreach (array('script','iframe','object','embed','link','meta','title','base') as $tag) {
 foreach (array('<'.$tag.' src="x">','<'.strtoupper($tag).'>','</'.$tag.'>') as $input) {
  if (stripos(RemoveXSS($input),'<'.$tag)!==false || stripos(RemoveXSS($input),'</'.$tag)!==false) throw new RuntimeException('Active tag retained');
  $checks++;
 }
}
foreach (array('<img src=x onerror=alert(1)>','<a href="javascript:alert(1)">x</a>','<a href="java&#x73;cript:alert(1)">x</a>','<img src=x on&#101;rror=alert(1)>','<img style="width:expression(alert(1))">') as $input) {
 $output=RemoveXSS($input);
 if(preg_match('/onerror|javascript|expression/i',$output)) throw new RuntimeException('Active attribute retained');
 $checks++;
}
echo "PASS: $checks news filter regression cases\n";
