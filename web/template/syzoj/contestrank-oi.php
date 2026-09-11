<?php $show_title="OI ".$MSG_STANDING." - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>
.oj-rank-oi { max-width: 1200px; margin: 0 auto; }
.oj-card { background: #fff; border: 1px solid #e6ebf2; border-radius: 10px; box-shadow: 0 4px 14px rgba(15,23,42,.06); padding: 20px; margin-bottom: 20px; }
.oj-table-wrap { overflow-x: auto; }
.oj-table-wrap table { width: 100%; border-collapse: collapse; font-size: 13px; }
.oj-table-wrap th, .oj-table-wrap td { padding: 8px 10px; border: 1px solid #e9ecef; text-align: center; white-space: nowrap; }
.oj-table-wrap thead th { background: #f8fafc; }
.oj-btn { display: inline-block; padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; background: #2f6ee5; color: #fff; margin: 0 4px; }
.oj-btn:hover { background: #1e56c6; color: #fff; }
</style>
<div class="padding oj-rank-oi">
  <div class="oj-card">
        <?php
        $rank=1;
        ?>
        <center><h3>OI Mode RankList -- <?php echo $title?></h3>
            <a class="oj-btn" href="contestrank.xls.php?cid=<?php echo $cid?>" >Download</a>
            <?php
            if($OJ_MEMCACHE){
                ?>
                <a class="oj-btn" href="contestrank2.php?cid=<?php echo $cid?>" >Replay</a>

                <?php
            }
            ?>
            <a class="oj-btn" href="contestrank.php?cid=<?php echo $cid?>">ACM<?php echo $MSG_STANDING?></a>
        </center>
        <div style="overflow: auto">
            <table id=rank>
                <thead>
                <tr class=toprow align=center>
                    <td style="text-align:center" class="{sorter:'false'}" width=5%>名次
                    <th style="text-align:center" width=10%>学号</th>
                    <th style="text-align:center" width=10%>专业班级</th>
                    <th style="text-align:center" width=10%>姓名</th>
                    <th style="text-align:center" width=5%>解决数</th>
                    <th style="text-align:center" width=5%>耗时</th>
                    <th style="text-align:center">分数</th>
                    <?php
                    for ($i=0;$i<$pid_cnt;$i++)
                        echo "<td><a href=problem.php?cid=$cid&pid=$i>$PID[$i]</a></td>";
                    echo "</tr></thead>\n<tbody>";
                    for ($i=0;$i<$user_cnt;$i++){
                        if ($i&1) echo "<tr class=oddrow align=center>\n";
                        else echo "<tr class=evenrow align=center>\n";
                        echo "<td>";
                        $uuid=$U[$i]->user_id;
                        $nick=$U[$i]->nick;
                        if($nick[0]!="*")
                            echo $rank++;
                        else
                            echo "*";
                        $usolved=$U[$i]->solved;
                        if(isset($_GET['user_id'])&&$uuid==$_GET['user_id']) echo "<td bgcolor=#ffff77>";
                        else echo"<td>";
                        echo "<a name=\"$uuid\" href=userinfo.php?user=$uuid>$uuid</a>";
                        echo "<td>".($U[$i]->school);
                        echo "<td><a href=userinfo.php?user=$uuid>".htmlentities($U[$i]->nick,ENT_QUOTES,"UTF-8")."</a>";
                        echo "<td><a href=status.php?user_id=$uuid&cid=$cid>$usolved</a>";
                        echo "<td>".sec2str($U[$i]->time);
                        echo "<td>".($U[$i]->total);
                        for ($j=0;$j<$pid_cnt;$j++){
                            $bg_color="eeeeee";
                            if (isset($U[$i]->p_ac_sec[$j])&&$U[$i]->p_ac_sec[$j]>0){
                                $aa=0x33+intval($U[$i]->p_wa_num[$j])*32;
                                $aa=$aa>0xaa?0xaa:$aa;
                                $aa=dechex($aa);
                                $bg_color="$aa"."ff"."$aa";
                                //$bg_color="aaffaa";
                                if($uuid==$first_blood[$j]){
                                    $bg_color="aaaaff";
                                }
                            }else if(isset($U[$i]->p_wa_num[$j])&&$U[$i]->p_wa_num[$j]>0) {
                                $aa=0xaa-intval($U[$i]->p_wa_num[$j])*10;
                                $aa=$aa>16?$aa:16;
                                $aa=dechex($aa);
                                $bg_color="ff$aa$aa";
                            }
                            echo "<td class=well style='background-color:#$bg_color'>";
                            if(isset($U[$i])){
                                if (isset($U[$i]->p_ac_sec[$j])&&$U[$i]->p_ac_sec[$j]>0)
                                    echo 100;
                                //echo sec2str($U[$i]->p_ac_sec[$j]);
                                else if (isset($U[$i]->p_wa_num[$j])&&$U[$i]->p_wa_num[$j]>0)
                                    echo "(+".(intval($U[$i]->p_pass_rate[$j])*100).")";
                            }
                        }
                        echo "</tr>\n";
                    }
                    echo "</tbody></table>";
                    ?>
                    <script type="text/javascript">
                        setInterval(function() {
                            $("#rank").load(location.href+" #rank>*","");
                        }, 30000);
                        //每隔30s自动刷新
                        // (location.href+" #dq>*","" )后一个id选择器一定要加个空格，后面*和"",也要加上，不然页面就乱。

                    </script>
        </div>
    </div>
  </div>
</div>
<?php include(dirname(__FILE__)."/footer.php");?>
