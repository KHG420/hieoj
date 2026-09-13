// Read-only integration checks. Defaults to the local restored HnieOJ data.
// Run: node tests/frontend-regression.mjs
// Production: OJ_TEST_ORIGIN=https://www.hnieacm.com node tests/frontend-regression.mjs
import assert from 'node:assert/strict';

const origin = process.env.OJ_TEST_ORIGIN || 'http://localhost';
async function html(path) {
  const response = await fetch(origin + path, {headers:{'Accept-Language':'zh-CN'}});
  assert.equal(response.status, 200, path);
  const body = await response.text();
  assert.doesNotMatch(body, /Fatal error|Parse error|db error/, path);
  return body;
}
const ids = body => [...body.matchAll(/class=['"]problem-title['"] href="problem\.php\?id=(\d+)"/g)].map(m => m[1]);
const first = await html('/problemset.php?search=C%E8%AF%AD%E8%A8%80&page=1');
const second = await html('/problemset.php?search=C%E8%AF%AD%E8%A8%80&page=2');
assert.equal(ids(first).length, 50, 'first search page contains 50 problems');
assert.equal(ids(second).length, 50, 'second search page contains 50 problems');
assert.equal(ids(first).filter(id => ids(second).includes(id)).length, 0, 'search pages do not overlap');
const total = Number(first.match(/共 (\d+) 道题目/)[1]);
const pages = Math.ceil(total / 50);
const last = await html('/problemset.php?search=C%E8%AF%AD%E8%A8%80&page=' + pages);
assert.equal(ids(last).length, total - (pages - 1) * 50, 'last page matches filtered total');
assert.deepEqual(ids(await html('/problemset.php?search=C%E8%AF%AD%E8%A8%80&page=99999')), ids(last), 'out-of-range page clamps to last page');
const empty = await html('/problemset.php?search=__frontend_regression_no_match_20260913__');
assert.equal(ids(empty).length, 0);
assert.match(empty, /未找到匹配题目/);
assert.doesNotMatch(empty, /class="pagination-container"/, 'no empty-result pagination');
assert.equal(ids(await html('/problemset.php?list=1000,1001')).length, 2, 'explicit problem lists keep working');
assert.doesNotMatch(await html('/discuss.php'), /db error/);
const profile = await html('/userinfo.php?user=202501200116');
assert.equal((profile.match(/<!doctype html>/gi) || []).length, 1, 'single profile document');
assert.equal((profile.match(/<body\b/gi) || []).length, 1, 'single profile body');
assert.match(profile, /<html lang="zh-CN">/);
assert.match(await html('/submitpage.php?id=1000'), /登录后继续/);
assert.match(await html('/loginpage.php'), /autocomplete="current-password" required/);
assert.match(await html('/knowledge_graph.php'), /id="kg-size-view"/);
console.log(JSON.stringify({status:'passed', searchTotal:total, pages, firstPage:50, lastPage:ids(last).length, duplicateProblems:0}, null, 2));
