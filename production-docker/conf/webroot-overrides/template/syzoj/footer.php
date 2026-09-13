</div>
</div>
<script src="<?php echo $OJ_CDN_URL.$path_fix."template/$OJ_TEMPLATE"?>/css/semantic.min.js" defer></script>
<footer>
    <style>
    .footer {
        line-height: 1.4285em;
        font-family: "Lato", "Noto Sans CJK SC", "Source Han Sans SC", "PingFang SC", "Hiragino Sans GB", "Microsoft Yahei", "WenQuanYi Micro Hei", "Droid Sans Fallback", "sans-serif";
        box-sizing: inherit;
        padding: 0 !important;
        border: none !important;
        color: #888;
        font-size: 1rem;
        margin: 35px 0 14px !important;
        position: relative;
        width: 100%;
        bottom: 0;
        background: none transparent;
        border-radius: 0;
        box-shadow: none;
    }

    #qqun:hover:after {
        content: "：308803591";
    }
    </style>
<!--    --><?php //include("template/$OJ_TEMPLATE/js.php");?>
    <div class="footer">
        <div class="ui center aligned container">
            <div id=footer class=center >
            <a href=setlang.php?lang=cn>中文</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href=setlang.php?lang=en>English</a>&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
                <a target="_blank" title="点击加入；推送近期比赛信息、交流算法、联系我们。"
                   id="qqun" href="https://jq.qq.com/?_wv=1027&k=40TcQP5X">算法交流群</a>&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
            GPLv2 licensed by <a href='https://github.com/zhblue/hustoj'  target='_blank'>HUSTOJ</a> <?php echo date('Y') ?> </div>

            <div>
                <?php 
                    // 确保 $domain 和 $DOMAIN 已经被定义和初始化
                    $domain = isset($domain) ? $domain : '';  // 如果 $domain 未定义，则使用默认空字符串
                    $DOMAIN = isset($DOMAIN) ? $DOMAIN : '';  // 如果 $DOMAIN 未定义，则使用默认空字符串

                    // 输出正确的 OJ 名称
                    if ($domain == $DOMAIN) {
                        echo $OJ_NAME;
                    } else {
                        echo ucwords($OJ_NAME) . "'s OJ";
                    }
                ?> 
                is powered by <a style="color: inherit !important;" class=" " title="GitHub"
                                                                                                   target="_blank" rel="noreferrer noopener" href="https://github.com/zhblue/hustoj">HUSTOJ</a>
                , Advanced by <a style="color: inherit !important;" href="https://www.hnieacm.com">HNIEACM</a>
            </div>
            <!--   <div> Running on <a href='https://debian.org' target='_blank'>Debian11</a> / <a href='https://www.loongson.cn' target='_blank'>Loongson 3A3000</a> </div> -->
            <?php if ($OJ_BEIAN) { ?>
                <div>© 2018-<?php echo date('Y') ?> HNIEACM 版权所有&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
                    <a href="https://beian.miit.gov.cn/" style="text-decoration: none; color: #444444;"
                       target="_blank"><?php echo $OJ_BEIAN; ?></a>
                </div>
            <?php } ?>       
        </div>
    </div>
    </div>

    <?php /* 蜜罐诱饵（2026-09-10）：display:none 的内容真实浏览器不会渲染，
             而解析 HTML 提取链接的爬虫会跟进去 → nginx 444 断连，
             并由 honeypot-sweep.sh 记入黑名单 7 天。
             详见 /opt/hnieoj-docker/README-operations.md */ ?>
    <div style="display:none" aria-hidden="true">
        <a href="/_oj_trap/export" rel="nofollow">数据导出</a>
        <a href="/_oj_internal/debug" rel="nofollow">系统状态</a>
    </div>

</footer>
</body>

</html>
