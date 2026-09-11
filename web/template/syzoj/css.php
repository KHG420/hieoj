<?php 
	$dir=basename(getcwd());
	if($dir=="discuss3"||$dir=="admin") $path_fix="../";
	else $path_fix="";
?>
<?php $oj_ver = "2026.3"; // 模板资源版本号：改样式后递增即可让浏览器缓存失效 ?>
<!-- 2026-09-10 性能优化：原 5 个 <link> + 2 个 CSS 内 @import（bootstrap/latin）串行加载，
     现合并为单个 css/oj-bundle.css，请求数 7 -> 1，并消除 @import 的串行往返 -->
<link rel="stylesheet" href="<?php echo $OJ_CDN_URL.$path_fix."template/$OJ_TEMPLATE"?>/css/oj-bundle.css?v=<?php echo $oj_ver?>">
<?php if(!empty($OJ_LOAD_KATEX)){ // 性能优化：数学渲染仅在题目相关页面按需加载 ?>
<link rel="stylesheet" href="<?php echo $path_fix."template/$OJ_TEMPLATE"?>/css/katex.min.css">
<script>
var katex_config = {
	delimiters: 
	[
		{left: "$$", right: "$$", display: true},
  		{left: "$", right: "$", display: false}
	]
};
</script>
<script defer src="<?php echo $path_fix."template/$OJ_TEMPLATE"?>/css/auto-render.min.js" onload="renderMathInElement(document.getElementById('problem-content')||document.body, katex_config)"></script>
<?php } ?>
