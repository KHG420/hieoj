# HnieOJ 前端（浏览器端）性能优化记录 — 2026-09-10

目标：降低真实用户在**浏览器里**感知的首屏时间。此前一轮优化只降低了服务端 TTFB（1~3ms），
而用户痛点在「隧道带宽/RTT × 首屏资源体积 × 渲染阻塞 × 未压缩图片」。

## 一、优化前实测基线（Chrome for Testing 131 + Lighthouse 12.8.2，mobile 模拟）

| 指标 | 本机直连 | 经 frp 隧道（真实用户） |
|---|---|---|
| 性能分 | 74 | 80 |
| FCP / LCP | 3.5 s / 4.5 s | 2.4 s / 3.2 s |
| TBT | 180 ms | 340 ms |
| 请求数 / 总字节 | 23 / 590 KB | 23 / 588 KB |
| 渲染阻塞 | 2720 ms | 1490 ms |

Chrome coverage 显示：semantic.min.css 未用 102.9KB(98.5%)、layui.js 未用 68.9KB(73%)、
semantic.min.js 未用 65.3KB(91%)、Chart.min.js 未用 21KB、bootstrap.min.css 未用 18.7KB ——
合计约 290KB（占首屏 49%）是纯浪费。

## 二、已实施的改动（按 git 提交顺序）

### A. 合并 CSS + 消除 @import 串行 + 字体转 woff2（commit 0da4d1e）
- 原 5 个 `<link>` + 2 个 CSS 内 `@import`（style.css→bootstrap、semantic.min.css→latin）串行加载，
  现合并为单个 `template/syzoj/css/oj-bundle.css`：**CSS 请求 7 → 1**，消除 2 次串行往返。
- Lato 字体 4 个 TTF（242KB）转 woff2（95KB），`latin.css` 改为 woff2 优先、ttf 兜底。
- 涉及：`template/syzoj/css.php`、`css/latin.css`、`css/oj-bundle.css`、`css/*.woff2`

### B. 裁剪 CSS（commit 25f67b2）
- PurgeCSS + **安全 safelist**（含全部 dynamic 状态类与 semantic 组件名）：
  `oj-bundle.css` 757KB → 563KB，gzip **127.7KB → 98.4KB**。
- 验证：8 个页面在同一 DOM 下切换「裁剪版 vs 原始版」CSS 截图，ImageMagick `compare -metric AE`
  **差异像素全部为 0**（视觉零差异）。

### C. JS 异步化（本提交）
- `layui.js`（首页 95KB，head 同步阻塞）→ `defer`；依赖它的两段内联初始化（`layui.table`）
  包入 `DOMContentLoaded`。
- `Chart.min.js`、`semantic.min.js` → `defer`（内联绘图代码本就在 `$(function(){})` 中，安全）。
- 验证：首页 `layui` 已加载、layui 表格实例 2、图表 canvas 1、4 个页面 **0 JS 错误**。

### D. 图片批量压缩（不入 git，见下）
- `upload/` 下 **162 张 >50KB 图片**（23.9MB）：PNG 走 `pngquant --quality=65-90`、
  JPG 走 `jpegoptim -m85 --strip-all`，**节省 14.4MB**（23.9MB → 9.5MB），4 张已优化则跳过。
- 尺寸与格式逐张校验一致（`identify`），属主统一 `www-data:www-data 644`。
- 原图完整备份：`/home/admin123/oj-optimize-backup-2026-09-10/frontend-images/`（162 个文件，25MB）。
- `upload/` 属运行期用户数据，已列入 `.gitignore`，故图片改动不体现在 git 中。

## 三、git 记录

```
b5b3968 chore: 站点优化前基线快照（2026-09-10）      <- 回滚到此即为优化前
0da4d1e perf(frontend): 合并 CSS + Lato 转 woff2
25f67b2 perf(frontend): 裁剪 oj-bundle.css（像素级验证零差异）
??????? perf(frontend): JS 改 defer + 本记录文档
```

## 四、回滚

```bash
# 代码/CSS/JS 回滚到任意步骤
sudo git -C /home/judge/src/web checkout b5b3968~0 -- .     # 回到优化前
sudo git -C /home/judge/src/web revert <commit>             # 撤销单步

# 图片回滚
sudo cp -a /home/admin123/oj-optimize-backup-2026-09-10/frontend-images/. /home/judge/src/web/upload/

# 内容改动后需失效缓存
sudo systemctl reload php8.1-fpm && sudo rm -rf /tmp/hustoj_page_cache/*
```

## 五、注意
- 改 PHP/CSS 后必须 `systemctl reload php8.1-fpm`（`opcache.revalidate_freq=60`，否则最多 60 秒不生效）
  并清 `/tmp/hustoj_page_cache/`。
- 静态资源带 `?v=<oj_ver>`，改样式后需递增 `css.php` 里的 `$oj_ver` 才能让浏览器缓存失效。
