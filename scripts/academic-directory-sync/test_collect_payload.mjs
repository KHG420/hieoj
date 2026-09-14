// collect_payload.js 的 Node 回归测试：用 node:vm + 桩 XMLHttpRequest 在本地执行页面脚本，
// 不访问网络、不启动浏览器、不执行任何远端脚本。
//
// 重点：会话交换（session exchange）等 XHR 抛出的浏览器错误信息可能内嵌完整请求 URL
// （含 token），页面脚本的异常兜底绝不能把它回传到结果里。
//
// 运行：node scripts/academic-directory-sync/test_collect_payload.mjs
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const SOURCE = fs.readFileSync(path.join(HERE, 'collect_payload.js'), 'utf8');
const ORIGIN = 'https://jwcmis.hnie.edu.cn';
const TOKEN = 'review-secret-token';

const CONFIG = {
  origin: ORIGIN,
  frm_path: '/jsxsd/view/kbxx/kbcx/llsykb_frm.jsp',
  colleges_path: '/tkglAction.do?method=llsykbFind&kbtype=xx04&init=1&isview=1',
  classes_path: '/common/llsykb/xx04_select.htmlx?id=xx04id&name=xx04mc&type=1&where=',
  page_size: 2,
  max_pages: 60,
};

const COLLEGES_HTML =
  '<html><body><select name="yxbh" id="yxbh">' +
  '<option value="">---请选择---</option>' +
  '<option value="01">【01】电气与信息工程学院</option>' +
  '</select></body></html>';

function classPage(total) {
  return (
    '<html><script>var rows=[];$("#pager").createPage({pageNum:1,current:1,total:' + total + ',each:2});</script>' +
    '<input type="hidden" id="dataTotal" name="dataTotal" value="' + total + '"/></html>'
  );
}

// step = { status, text } 或 { networkError: true }
function makeXhrFactory(plan) {
  let index = 0;
  return function XMLHttpRequest() {
    const self = this;
    self.status = 0;
    self.responseText = '';
    self.open = function (method, url) {
      self.url = url;
    };
    self.send = function () {
      const step = plan[index++];
      if (!step) {
        throw new Error('unexpected request #' + index + ' to ' + self.url);
      }
      if (step.networkError) {
        // 复现浏览器 XHR 网络异常：message 内嵌完整 URL（真实场景含 token）。
        throw new Error("Failed to load '" + self.url + "'");
      }
      self.status = step.status;
      self.responseText = step.text || '';
    };
  };
}

function runPayload(plan, config = CONFIG) {
  const sandbox = {
    __ACADEMIC_SYNC_CONFIG__: config,
    XMLHttpRequest: makeXhrFactory(plan),
  };
  vm.createContext(sandbox);
  const raw = vm.runInContext(SOURCE, sandbox, { filename: 'collect_payload.js', timeout: 5000 });
  return JSON.parse(raw);
}

const serialized = (result) => JSON.stringify(result);

const tests = [];
function test(name, fn) {
  tests.push({ name, fn });
}

const iframeWithToken =
  '<iframe src="http://jwcmis.hnie.edu.cn//Logon.do?method=toFinGlKbCx&token=' + TOKEN + '"></iframe>';

test('session exchange network error does not leak token/URL', () => {
  const result = runPayload([
    { status: 200, text: iframeWithToken },
    { networkError: true },
  ]);
  assert.equal(result.ok, false);
  assert.equal(result.error, 'session exchange request failed');
  assert.ok(!serialized(result).includes(TOKEN), 'token must not appear in result');
  assert.ok(!serialized(result).includes('Failed to load'), 'remote error text must not appear');
  assert.ok(!serialized(result).includes('Logon.do'), 'request URL must not appear');
});

test('first request network error is stage-labeled and URL-free', () => {
  const result = runPayload([{ networkError: true }]);
  assert.equal(result.ok, false);
  assert.equal(result.error, 'session frame request failed');
  assert.ok(!serialized(result).includes(ORIGIN));
  assert.ok(!serialized(result).includes('Failed to load'));
});

test('pagination network error reports page stage without URL', () => {
  const result = runPayload([
    { status: 200, text: '' },
    { status: 200, text: COLLEGES_HTML },
    { status: 200, text: classPage(4) },
    { networkError: true },
  ]);
  assert.equal(result.ok, false);
  assert.equal(result.error, 'classes page 2 request failed');
  assert.ok(!serialized(result).includes(ORIGIN));
  assert.ok(!serialized(result).includes('Failed to load'));
});

test('HTTP status errors keep their original message', () => {
  const result = runPayload([
    { status: 200, text: iframeWithToken },
    { status: 500, text: '' },
  ]);
  assert.equal(result.ok, false);
  assert.equal(result.error, 'session exchange HTTP 500');
  assert.ok(!serialized(result).includes(TOKEN));
});

test('normal collection still succeeds and drops token from output', () => {
  const result = runPayload([
    { status: 200, text: iframeWithToken },
    { status: 200, text: '' },
    { status: 200, text: COLLEGES_HTML },
    { status: 200, text: classPage(2) },
  ]);
  assert.equal(result.ok, true);
  assert.equal(result.error, '');
  assert.equal(result.pages.length, 1);
  assert.equal(result.pages[0].page, 1);
  assert.ok(result.collegesHtml.includes('yxbh'));
  assert.ok(!serialized(result).includes(TOKEN), 'token must not appear in a successful result');
});

let failed = 0;
for (const { name, fn } of tests) {
  try {
    fn();
    console.log('ok - ' + name);
  } catch (error) {
    failed += 1;
    console.error('not ok - ' + name);
    console.error('    ' + (error && error.message ? error.message : String(error)));
  }
}

if (failed > 0) {
  console.error(failed + ' of ' + tests.length + ' collect_payload tests failed');
  process.exit(1);
}
console.log(tests.length + ' collect_payload tests passed');
