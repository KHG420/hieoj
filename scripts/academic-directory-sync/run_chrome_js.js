/*
 * osascript JXA 包装：在现有 Chrome 的 jwcmis 标签里执行 collect_payload.js。
 * 只查找已有标签并执行 JS，不 launch、不新建/切换窗口或标签、不改设置。
 *
 * 用法：osascript -l JavaScript run_chrome_js.js <payload.js> <tab-url-substring>
 */
ObjC.import('Foundation');

function readText(path) {
  var value = $.NSString.stringWithContentsOfFileEncodingError(path, $.NSUTF8StringEncoding, null);
  if (!value || value.isNil()) {
    return null;
  }
  return ObjC.unwrap(value);
}

function run(argv) {
  if (!argv || argv.length < 2) {
    return JSON.stringify({ ok: false, error: 'usage: run_chrome_js.js <payload> <tab-match>' });
  }
  var payload = readText(argv[0]);
  if (payload === null) {
    return JSON.stringify({ ok: false, error: 'payload file unreadable' });
  }
  var match = argv[1];
  var chrome = Application('Google Chrome');
  if (!chrome.running()) {
    return JSON.stringify({ ok: false, error: 'Chrome 未运行（不会自动启动浏览器）' });
  }
  var windows = chrome.windows();
  var target = null;
  for (var i = 0; i < windows.length && target === null; i++) {
    var tabs = windows[i].tabs();
    for (var j = 0; j < tabs.length; j++) {
      var url = '';
      try {
        url = tabs[j].url();
      } catch (e) {
        url = '';
      }
      if (url && url.indexOf(match) !== -1) {
        target = tabs[j];
        break;
      }
    }
  }
  if (target === null) {
    return JSON.stringify({ ok: false, error: 'no matching Chrome tab found (请先登录教务并保持标签打开)' });
  }
  var result;
  try {
    result = target.execute({ javascript: payload });
  } catch (e) {
    return JSON.stringify({ ok: false, error: 'JavaScript execution failed (Chrome 需开启 View > Developer > Allow JavaScript from Apple Events)' });
  }
  if (result === null || result === undefined) {
    return JSON.stringify({ ok: false, error: 'empty JavaScript result' });
  }
  return result;
}
