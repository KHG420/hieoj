# webroot 覆盖文件备份

这里的文件是 `/home/judge/src/web/` 下**被手工修改过**的原件副本，
仅作版本留痕（它们不在本 compose 仓库的覆盖范围内，是通过
`- /home/judge/src/web:/home/judge/src/web` 目录挂载直接生效的）。

修改后需执行（PHP 改动受 `opcache.revalidate_freq=60` 影响）：

```bash
docker exec hnieoj-web php -l /home/judge/src/web/status.php   # 语法检查
docker exec hnieoj-web sh -c 'for p in /proc/[0-9]*; do c=$(tr "\0" " " < $p/cmdline 2>/dev/null); case "$c" in *"php-fpm: master"*) kill -USR2 "${p#/proc/}";; esac; done'
```

| 文件 | 改动 | 时间 |
|---|---|---|
| `status.php` | 反枚举：匿名访客禁止按 `user_id` 过滤；非特权用户限制翻页深度（`$OJ_ANON_MAX_SCAN = 20000`） | 2026-09-10 22:51 |
| `robots.txt` | 声明禁止全部 AI/SEO 爬虫；新增 4 条蜜罐诱饵 `Disallow` | 2026-09-10 22:53 |
| `template/syzoj/footer.php` | 页脚加入 `display:none` 蜜罐诱饵链接 | 2026-09-10 22:53 |

详见 `/opt/hnieoj-docker/README-anti-crawler.md`。
