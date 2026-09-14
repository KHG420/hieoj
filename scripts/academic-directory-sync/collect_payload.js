/*
 * 在已登录 jwcmis 标签的页面上下文里同步抓取学院与班级分页。
 * 由 collect_directory.py 注入 __ACADEMIC_SYNC_CONFIG__ 后通过 osascript 执行。
 * 返回 JSON 字符串；不输出任何 token / 凭证。
 */
(function () {
  var C = __ACADEMIC_SYNC_CONFIG__;
  var out = { ok: false, error: '', collegesHtml: '', pages: [] };
  // 异常兜底只报告阶段名：XHR / 远端异常文本可能内嵌完整请求 URL（含 token），
  // 绝不把 e.message 写进结果。
  var stage = 'session frame';

  function pageTotal(html) {
    var m = html.match(/id\s*=\s*["']?dataTotal["']?[^>]*?value\s*=\s*["']?(\d+)/i)
      || html.match(/value\s*=\s*["']?(\d+)["']?[^>]*?id\s*=\s*["']?dataTotal/i);
    if (m) {
      return parseInt(m[1], 10);
    }
    var c = html.match(/createPage\(\{[^}]*total\s*:\s*(\d+)/i);
    return c ? parseInt(c[1], 10) : 0;
  }

  function get(url) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, false);
    xhr.send(null);
    return { status: xhr.status, text: xhr.responseText || '' };
  }

  try {
    // 1) 课程表模块页，必要时用它内部的 iframe 地址交换根模块会话（严格同源/预期路径）
    var frm = get(C.origin + C.frm_path);
    if (frm.status !== 200) {
      out.error = 'session frame HTTP ' + frm.status;
      return JSON.stringify(out);
    }
    var frame = frm.text.match(/src\s*=\s*["']([^"']*\/Logon\.do\?method=toFinGlKbCx[^"']*)["']/i);
    if (frame) {
      // 只取路径，丢弃任意 host，避免跨源；token 仅用于本次同步 XHR，不写入结果
      var path = frame[1].replace(/^https?:\/\/[^/]+/i, '');
      if (path.charAt(0) !== '/') {
        path = '/' + path;
      }
      path = path.replace(/^\/+/, '/');
      if (path.indexOf('/Logon.do?method=toFinGlKbCx') !== 0) {
        out.error = 'unexpected session exchange target';
        return JSON.stringify(out);
      }
      stage = 'session exchange';
      var exchanged = get(C.origin + path);
      if (exchanged.status !== 200) {
        out.error = 'session exchange HTTP ' + exchanged.status;
        return JSON.stringify(out);
      }
    }

    // 2) 学院列表
    stage = 'colleges';
    var colleges = get(C.origin + C.colleges_path);
    if (colleges.status !== 200) {
      out.error = 'colleges HTTP ' + colleges.status;
      return JSON.stringify(out);
    }
    if (!/yxbh/i.test(colleges.text)) {
      out.error = 'not logged in or unexpected colleges page';
      return JSON.stringify(out);
    }
    out.collegesHtml = colleges.text;

    // 3) 班级分页
    stage = 'classes page 1';
    var first = get(C.origin + C.classes_path + '&PageNum=1&pageSize=' + C.page_size);
    if (first.status !== 200) {
      out.error = 'classes page 1 HTTP ' + first.status;
      return JSON.stringify(out);
    }
    var total = pageTotal(first.text);
    if (!total || total <= 0) {
      out.error = 'classes page 1 has no total';
      return JSON.stringify(out);
    }
    var pages = Math.ceil(total / C.page_size);
    if (pages < 1 || pages > C.max_pages) {
      out.error = 'unexpected page count ' + pages;
      return JSON.stringify(out);
    }
    out.pages.push({ page: 1, html: first.text });
    for (var p = 2; p <= pages; p++) {
      stage = 'classes page ' + p;
      var res = get(C.origin + C.classes_path + '&PageNum=' + p + '&pageSize=' + C.page_size);
      if (res.status !== 200) {
        out.error = 'classes page ' + p + ' HTTP ' + res.status;
        return JSON.stringify(out);
      }
      var t = pageTotal(res.text);
      if (t !== total) {
        out.error = 'total changed on page ' + p;
        return JSON.stringify(out);
      }
      out.pages.push({ page: p, html: res.text });
    }

    out.ok = true;
    return JSON.stringify(out);
  } catch (e) {
    // 只回传阶段，绝不回传 e.message（可能含请求 URL 与 token）。
    out.error = stage + ' request failed';
    return JSON.stringify(out);
  }
})()
