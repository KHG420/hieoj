<!DOCTYPE html>
<html lang="zh">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="../../favicon.ico">
    <link rel="stylesheet" href="./template/sta_sty/layui/css/layui.css">
    <script src="./template/sta_sty/layui/layui.js"></script>

    <title>
        <?php echo $OJ_NAME?>
    </title>
</head>

<body>
<div class="container">
    <?php include("template/$OJ_TEMPLATE/header.php");?>
</div>
<div class='layui-row layui-col-space10'>
    <div class="layui-col-md3">
    </div>
    <?php
    echo"<div class='layui-col-md6'>";
    if (!$result){
        echo "<button class='layui-btn layui-btn-lg layui-btn-radius layui-btn-normal'>没有标签</button>";
    }else{
//    sort($category);

        $i = 0;
        echo "<div class='layui-collapse'>";
        foreach ($category as $row){
            $j = 0;

            echo "<div class='layui-colla-item' >";
            echo "<h1 class='layui-colla-title' style='background-color: #009688; height: 64px; font-size: 18px; line-height: 64px; color: white; border-radius: 15px'>".$title[$i]."</h1>";
            echo "<div class='layui-colla-content'>";

            foreach ($row as $res) {
                $j ++;
//            if (trim($row[0]) == "") continue;
                $str = htmlentities($title[$i],ENT_QUOTES,'UTF-8');
                $strr = htmlentities($res,ENT_QUOTES,'UTF-8');
                if ($strr == "") $strr = $str;
                if ($res != "")
                    $str = $str."-".htmlentities($res,ENT_QUOTES,'UTF-8');
                echo "<a href='problemset.php?search=$str'><button class='layui-btn layui-btn-lg layui-btn-radius layui-btn-normal' style='width: 140px;height: 45px;margin-bottom: 20px; margin-left: 13px; margin-right: 10px;'>$strr</button></a>";

            }
            $i++;
            echo "</div>";

            echo "</div>";
        }
        echo "</div>";

    }
    echo"</div>";
    ?>
    <div class="layui-col-md3">
    </div>
</div>
<script>
    //注意：折叠面板 依赖 element 模块，否则无法进行功能性操作
    layui.use('element', function(){
        var element = layui.element;

        //…
    });
</script>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
</body>
</html>
