<?php $show_title="Contest RankList -- ".$title." - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>
.submit_time {
    font-size: 0.8em;
    margin-top: 5px;
    color: #000;
}
</style>
    <script>
        $("#main_container").removeClass("ui");
        $("#main_container").removeClass("main");
        $("#main_container").removeClass("container");
    </script>

    <div class="layui-container">
        <div class="layui-row">
            <div class="layui-col-md9">

    <div style="margin-bottom:40px; ">
    <center>
      <h1 style="text-align: center;">Contest RankList -- <?php echo $title?></h1>
      <a href="contestrank.xls.php?cid=<?php echo $cid?>">Download</a>
    </center>
</div>
    <div style="transform: scaleY(-1);" id="rank-top">
<div class="padding" style="width: auto; overflow-y:auto;">
    <div style="transform: scaleY(-1);">
    <?php if($user_cnt>0){ ?>
    <table class="ui very basic center aligned table" sylye="margin:30px" id="rank">
        <thead>
            <tr>
                <th>#</th>
                <?php if ($team_state == 0) {?>
                    <th>用户名</th>
                    <th>专业班级</th>
                    <th>姓名</th>
                <?php } else if ($team_state == 1) { ?>
                    <th>队伍名称</th>
                    <th>队员</th>
                <?php } ?>
                <th>通过数量</th>
<!--                <th>重复数</th>-->
<!--                --><?php //if($IPprivate == "3") {?>
<!--                <th>联网数</th>-->
<!--                --><?php //} ?>
                <th>罚时</th>
                <?php
                  for ($i=0;$i<$pid_cnt;$i++)
                  echo "<th><a href=problem.php?cid=$cid&pid=$i>".chr(65+$i)."</a></th>";
                ?>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
              $rank=1;
              for ($i=0;$i<$user_cnt;$i++){
                $uuid=$U[$i]->user_id;
                $school=$U[$i]->school;
                $nick=$U[$i]->nick;
                $usolved=$U[$i]->solved;
                $s_num = $U[$i]->sim_num;
                $l_num = $U[$i]->link_num;
                echo "<tr>";

                echo "<td>";
                  if($nick[0]!="*"){
                    if($rank==1)
                      echo "<div class=\"ui yellow ribbon label\">";
                    else if($rank==2)
                      echo "<div class=\"ui ribbon label\">";
                    else if($rank==3)
                      echo "<div class=\"ui brown ribbon label\" style=\"background-color: #C47222 !important;\">";
                    else
                      echo "<div>";
                    echo $rank++;
                    echo "</div>";
                  }
                  else
                    echo "*";
                echo "</td>";

                if(isset($_GET['user_id'])&&$uuid==$_GET['user_id'])
                  echo "<td bgcolor=#ffff77>";
                else
                  echo "<td>";

                  if($team_state == 0)
                      echo "<a name=\"$uuid\" href=userinfo.php?user=$uuid>$uuid</a>";
                  else if($team_state == 1) {
                      echo "<span style='position: relative;'>".(isset($teams[$i]['team_name'])?$teams[$i]['team_name']:'');
                      if ($IPprivate == "3" && $l_num > 0) {
                          echo "<span style='position: absolute; top:-5px; right: -5px; color: red'>*</span>";
                      }
                      echo "</span>";
                  }
                  echo "</td>";

                echo "<td>";
                  if($team_state == 0)
                      echo $school;
                  else if($team_state == 1)
                      echo "<a href='userinfo.php?user=".(isset($teams[$i]['user_id1'])?$teams[$i]['user_id1']:'')."'>".(isset($teams[$i]['nick1'])?$teams[$i]['nick1']:'')."</a>".
                          "&nbsp&nbsp&nbsp&nbsp"."<a href='userinfo.php?user=".(isset($teams[$i]['user_id2'])?$teams[$i]['user_id2']:'')."'>".(isset($teams[$i]['nick2'])?$teams[$i]['nick2']:'')."</a>".
                          "&nbsp&nbsp&nbsp&nbsp"."<a href='userinfo.php?user=".(isset($teams[$i]['user_id3'])?$teams[$i]['user_id3']:'')."'>".(isset($teams[$i]['nick3'])?$teams[$i]['nick3']:'')."</a>";
                echo "</td>";

            if($team_state == 0) {
                echo "<td>";
                echo "<a style='position: relative;' href=userinfo.php?user=$uuid>" . htmlentities($U[$i]->nick, ENT_QUOTES, "UTF-8");
                if ($IPprivate == "3" && $l_num > 0) {
                    echo "<span style='position: absolute; top:-5px; right: -5px; color: red'>*</span>";
                }
                echo "</a>";
                echo "</td>";
            }
                  
                echo "<td>";
                  echo "<span class=\"score\">";
                  echo "<a href=status.php?user_id=$uuid&cid=$cid>$usolved</a>";
                  echo "</span>";
                echo "</td>";
//重复数
//                  echo "<td>";
//                  echo "<span class=\"score\">";
//                  echo "<a href=status.php?user_id=$uuid&cid=$cid>$s_num</a>";
//                  echo "</span>";
//                  echo "</td>";
//联网数
//                  if($IPprivate == "3") {
//                      echo "<td>";
//                      echo $l_num;
//                      echo "</td>";
//                  }

                echo "<td>";
                  echo sec2str($U[$i]->time);
                echo "</td>";
                
                for ($j=0;$j<$pid_cnt;$j++){
                  if(isset($U[$i])){
                    if (isset($U[$i]->p_ac_sec[$j])&&$U[$i]->p_ac_sec[$j]>0){
		   	if($uuid==$first_blood[$j]){
                      		echo "<td style=\"background: rgb(".(150+12*$U[$i]->p_wa_num[$j]).", 255, ".(150+8*$U[$i]->p_wa_num[$j])."); position:relative;\">";
				echo "<div style=\"position:absolute;width:30%;margin-top: 5%;margin-right: 5%;height:30%;right:0px;top:0px;\">※1st</div>";
			}
			else{
                      echo "<td style=\"background: rgb(".(150+12*$U[$i]->p_wa_num[$j]).", 255, ".(150+8*$U[$i]->p_wa_num[$j])."); \">";
			}
                      if (isset($U[$i]->p_wa_num[$j])&&$U[$i]->p_wa_num[$j]>0)
		      {
                        echo "<span class=\"score score_10\">";
                        echo "+".$U[$i]->p_wa_num[$j]."";
                        echo "</span>";
                      }else{
                        echo "<span class=\"score score_10\">";
                        echo "+";
                        echo "</span>";
		      }
                      echo "<div class=\"submit_time\">";
                        echo sec2str($U[$i]->p_ac_sec[$j]);
                      echo "</div>";
                    }
                    else if (isset($U[$i]->p_wa_num[$j])&&$U[$i]->p_wa_num[$j]>0){
                      echo "<td style=\"background: rgb(255, ".(240-9*$U[$i]->p_wa_num[$j]).", ".(240-9*$U[$i]->p_wa_num[$j])."); \">";
                      echo "<span class=\"score score_0\">";
                        echo "-".$U[$i]->p_wa_num[$j]."";
                      echo "</span>";
                    }
                    else{
                      echo "<td>";
                    }

                  }
                  else{
                    echo "<td>";
                  }

                  echo "</td>";
                }
                echo "<td>";
                
                echo "</td>";
                
                echo "</tr>";
              }
            ?>

        </tbody>
    </table>
        <script type="text/javascript">
            setInterval(function() {
                $("#rank").load(location.href+" #rank>*","");
            }, 60000);
            //每隔60s自动刷新
            // (location.href+" #dq>*","" )后一个id选择器一定要加个空格，后面*和"",也要加上，不然页面就乱。

        </script>
    <?php }else{ ?>
    <div style="background-color: #fff; height: 18px; margin-top: -18px; "></div>
    <div class="ui placeholder segment" style="margin-top: 0px; ">
        <div class="ui icon header">
            <i class="ui file icon" style="margin-bottom: 20px; "></i>
            暂无选手提交
        </div>
    </div>
        <script type="text/javascript">
            setInterval(function () {
                $("#rank-top").load(location.href +" #rank-top>*", "");
            }, 60000);
        </script>
    <?php } ?>
    </div>
</div>
</div>

            </div>
        </div>
    </div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>