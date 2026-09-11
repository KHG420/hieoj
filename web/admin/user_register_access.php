<?php require_once("admin-header.php");
require_once('../include/db_info.inc.php');

$xy = [
    "01" => "电气与信息工程学院", "02" => "机械工程学院", "03" => "信息科学与工程学院", "04" => "外国语学院", "05" => "经济学院",
    "06" => "材料与化工学院", "07" => "管理学院", "08" => "纺织服装学院", "09" => "建筑工程学院", "10" => "计算科学与电子学院",
    "12" => "设计艺术学院", "13" => "应用技术学院", "17" => "国际教育学院", "45" => "卓越工程师学院",
];

$sql = "SELECT user_id,nick,school,xueYuan,email,register_num FROM `users` WHERE `register_num`=0";
$result=pdo_query($sql);

$a_str = '<div>
        <includetail>
            <div align="center">
                <div class="open_email" style="margin-left: 8px; margin-top: 8px; margin-bottom: 8px; margin-right: 8px;">
                    <div>
                        <br>
                        <span class="genEmailContent">
                        <div id="cTMail-Wrap"
                             style="word-break: break-all;box-sizing:border-box;text-align:center;min-width:320px; max-width:660px; border:1px solid #f6f6f6; background-color:#f7f8fa; margin:auto; padding:20px 0 30px; font-family:\'helvetica neue\',PingFangSC-Light,arial,\'hiragino sans gb\',\'microsoft yahei ui\',\'microsoft yahei\',simsun,sans-serif">
                            <div class="main-content" style="">
                                <table style="width:100%;font-weight:300;margin-bottom:10px;border-collapse:collapse">
                                    <tbody>
                                    <tr style="font-weight:300">
                                        <td style="width:3%;max-width:30px;"></td>
                                        <td style="max-width:600px;">
                                            <div id="cTMail-logo" style="height:50px; ;">
                                                <a href="https://hnieacm.com">
                                                    <img border="0" src=""
                                                        style="height:50px; ">
                                                         </a><h2 style="display: inline; line-height: 50px;">算法设计在线评测系统</h2>
                                            </div>
                                            <p style="height:2px;background-color: #00a4ff;border: 0;font-size:0;padding:0;width:100%;margin-top:20px;"></p>

                                            <div id="cTMail-inner" style="background-color:#fff; padding:23px 0 20px;box-shadow: 0px 1px 1px 0px rgba(122, 55, 55, 0.2);text-align:left;">
                                                <table style="width:100%;font-weight:300;margin-bottom:10px;border-collapse:collapse;text-align:left;">
                                                    <tbody>
                                                    <tr style="font-weight:300">
                                                        <td style="width:3.2%;max-width:30px;"></td>
                                                        <td style="max-width:480px;text-align:left;">
                                                            <h1 id="cTMail-title" style="font-size: 20px; line-height: 36px; margin: 0px 0px 22px;">
                                                                【HNIEOJ】成功注册
                                                            </h1>

                                                            <p id="cTMail-userName" style="font-size:14px;color:#333; line-height:24px; margin:0;">
                                                                你注册的账号已通过审核，可以正常登陆使用，恭喜你开启一段 coding 之旅 !!!
                                                            </p>

                                                            <p class="cTMail-content" style="line-height: 24px; margin: 6px 0px 0px; overflow-wrap: break-word; word-break: break-all;">
                                                                <span style="color: rgb(51, 51, 51); font-size: 14px;">
                                                                    外网可使用新域名访问：https://www.hnieacm.com
                                                                </span>
                                                            </p>

                                                            <p class="cTMail-content" style="line-height: 24px; margin: 6px 0px 0px; overflow-wrap: break-word; word-break: break-all;">
                                                                <span style="color: rgb(51, 51, 51); font-size: 14px;">
                                                                    欢迎加入湖南工程学院算法兴趣交流群：308803591。
                                                                </span>
                                                            </p>

                                                            <dl style="font-size: 14px; color: rgb(51, 51, 51); line-height: 18px;">
                                                                <dd style="margin: 0px 0px 6px; padding: 0px; font-size: 12px; line-height: 22px;">
                                                                    <p id="cTMail-sender" style="font-size: 14px; line-height: 26px; word-wrap: break-word; word-break: break-all; margin-top: 32px;">
                                                                        此致
                                                                        <br>
                                                                        <strong>湖南工程学院ACM程序设计竞赛实验室</strong>
                                                                    </p>
                                                                </dd>
                                                            </dl>
                                                        </td>
                                                        <td style="width:3.2%;max-width:30px;"></td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div id="cTMail-copy" style="text-align:center; font-size:12px; line-height:18px; color:#999">
                                                <table style="width:100%;font-weight:300;margin-bottom:10px;border-collapse:collapse">
                                                    <tbody>
                                                    <tr style="font-weight:300">
                                                        <td style="width:3.2%;max-width:30px;"></td>
                                                        <td style="max-width:540px;">

                                                            <p style="text-align:center; margin:20px auto 14px auto;font-size:12px;color:#999;">
                                                                此为系统邮件，请勿回复。
                                                            </p>
                                                            
                                                        </td>
                                                        <td style="width:3.2%;max-width:30px;"></td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                        <td style="width:3%;max-width:30px;"></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </span>
                    </div>
                </div>
            </div>
        </includetail>
    </div>';

function send_post($url, $post_data) {

    $postdata = http_build_query($post_data);
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-type:application/x-www-form-urlencoded',
            'content' => $postdata,
            'timeout' => 15 * 60 // 超时时间（单位:s）
        )
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    return $result;
}

$n = 0;
$b = 0;
$k = 0;
foreach ($result as $row) {
    $user_id = $row['user_id'];
    $nick = $row['nick'];
    $school = $row['school'];
    $xueYuan = $row['xueYuan'];
    $email = $row['email'];

    if ($_GET['num'] == "1") {
        $sql = "SELECT nick, school, COUNT(*) as s FROM `audit_user` WHERE user_id=?";
        $res = pdo_query($sql, $user_id);
        $res = $res[0];
        if ($res['s'] == 0 || $res['s'] == "0") {
            $k ++;
            continue;
        }
    } else if ($_GET['num'] == "2") {
        $post_data = array(
            'user_id' => $user_id,
        );
        $url = "http://".$micServiceIP."/mail/getstuinfo/";
        $req = send_post($url, $post_data);
//        echo $req;
        $res = json_decode($req, true);
        echo "user_id:".$user_id." school:".$res['school']." nick: ".$res['nick']."<br>";
        if (strcmp($res['result'], "true")  != 0) {
            $k ++;
            echo $user_id." 查询失败。"."<br>";
            continue;
        }
    }

    $str = "";
    if (strcmp($res['nick'], $nick) == 0 && strcmp($res['school'], $school) == 0) {
        $sql = "UPDATE `users` SET `defunct`='N',`register_num`=1,`accesstime`=NOW() WHERE `user_id`=?";
        pdo_query($sql,$user_id);
        $sql = "UPDATE `audit_user` SET `status`=1 WHERE `user_id`=?";
        pdo_query($sql,$user_id);
//        $str = "[HNIEOJ] 你注册的账号已通过审核，可以正常登陆使用，恭喜你开启一段 coding 之旅 !!!（本邮件为程序自动发送，不用回复，外网可使用新域名访问：https://hnieacm.com）";
        $str = $a_str;
        $n ++;
        $post_data = array(
            'code' => 'acmore',
            'email' => $email,
            'content' => $str,
        );
        $url = "http://".$micServiceIP."/mail/sendEmail/";
        send_post($url, $post_data);
    } else {
        $k ++;
    }
//    else {
//        $sql = "DELETE FROM users WHERE user_id=?";
//        pdo_query($sql,$user_id);
//        $w_str = "[HNIEOJ] 你的注册申请未通过申请，"."可能的原因：姓名、班级或学号不正确。请检查后重新注册（本邮件为程序自动发送，不用回复）。<br>"
//            ."您的注册信息如下：<br>"
//            ."<table>"
//            ."<tr><td>姓名</td><td>".$nick."</td></tr>"
//            ."<tr><td>班级</td><td>".$school."</td></tr>"
//            ."<tr><td>学号</td><td>".$user_id."</td></tr>"
//            ."</table>";
//        $str = $w_str;
//        $b ++;
//    }
}

echo "已通过人数：".$n."<br>";
echo "需手动确认：".$k."<br>";
?>