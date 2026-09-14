// Read-only regression against a restored OJ database or the deployed site.
// OJ_TEST_ORIGIN=http://127.0.0.1:18888 node web/tests/live-audit-regression.mjs
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {setTimeout as delay} from 'node:timers/promises';
const origin = process.env.OJ_TEST_ORIGIN || 'http://127.0.0.1:18888';
let checks=0;
async function get(path, status=200) {
  await delay(300);
  const r=await fetch(origin+path); assert.equal(r.status,status,path);
  const body=await r.text(); assert.doesNotMatch(body,/Fatal error|Parse error|SQLSTATE|Uncaught /,path); checks++;
  return body;
}
const text=s=>s.replace(/<[^>]*>/g,'').trim();
const rows=body=>[...body.matchAll(/<tr\b[^>]*>([\s\S]*?)<\/tr>/g)].map(m=>[...m[1].matchAll(/<td\b[^>]*>([\s\S]*?)<\/td>/g)].map(c=>text(c[1]))).filter(r=>r.length>1);
for (const sort of ['latest','hot']) {
  await get('/discuss.php?keyword='+encodeURIComponent('错误')+'&sort='+sort);
  const empty=await get('/discuss.php?keyword=__live_audit_no_match_20260914__&sort='+sort);
  assert.doesNotMatch(empty,/class="oj-topic-row"/,'non-numeric search must not match all pid=0 topics');
}
await get('/discuss.php?keyword=1000');
const statusPath='/status.php?problem_id=1000&language=0&jresult=4';
const base=rows(await get(statusPath)).map(r=>r[0]);
assert.ok(base.length>0,'fixture problem has submissions');
assert.deepEqual(rows(await get(statusPath+'&user_id=')).map(r=>r[0]),base,'blank user filter');
assert.deepEqual(rows(await get(statusPath+'&user_id=not_authorized_user')).map(r=>r[0]),base,'ignored anonymous user filter');
await get('/contest.php?cid=99999999',404);
const missingThread = await get('/thread.php?tid=99999999',404);
assert.match(missingThread, /主页/); assert.match(missingThread, /问题/);
for (const path of ['/viewnews.php','/viewnews.php?id=99999999','/problem.php?id=99999999','/problemstatus.php?id=99999999','/userinfo.php?user=__missing_audit_user__','/contestrank.php?cid=99999999','/contestrank2.php?cid=99999999','/contestrank3.php?cid=99999999','/contestrank-oi.php?cid=99999999']) {
  await get(path,404);
}
const contests=rows(await get('/contest.php?keyword=2024&page=2'));
assert.ok(contests.length>0); assert.ok(contests.every(r=>r[1].includes('2024')),'page 2 retains search');
const contestFirst=await get('/contest.php?keyword=2024');
const pageLinks=[...contestFirst.matchAll(/href="(contest.php\?page=[^"]+)"/g)].map(m=>m[1]);
assert.ok(pageLinks.length); assert.ok(pageLinks.every(p=>p.includes('keyword=2024')));
assert.equal(rows(await get('/contest.php?my=1')).length,0,'anonymous my contests is empty');
assert.equal(rows(await get('/contest.php?keyword=__live_audit_no_match__')).length,0);
assert.deepEqual(rows(await get('/contest.php?page=-1')),rows(await get('/contest.php?page=1')));
for (const range of ['year','all']) {
  const empty=rows(await get('/ranklist.php?range='+range+'&xy='+encodeURIComponent('不存在的测试学院')));
  assert.equal(empty.length,0,'college name must actually constrain query');
  assert.ok(rows(await get('/ranklist.php?range='+range+'&xy='+encodeURIComponent('机械工程学院'))).length>0);
}
const replay=await get('/contestrank2.php?cid=1163');
const source=replay.match(/var solutions=(.*);/)[1];const submissions=JSON.parse(source);
const cePenalty=replay.match(/var CE_PENALTY=(true|false)/)[1]==='true';
const rankHTML=replay.match(/<table id="rank"[\s\S]*?<tbody>([\s\S]*?)<\/tbody>/)[1];
const ranked=rows(rankHTML);const expected=new Map();
for(const s of submissions){
  if(!expected.has(s.user_id))expected.set(s.user_id,{solved:0,penalty:0,done:new Set(),wrong:new Map()});
  const u=expected.get(s.user_id), n=Number(s.num), result=Number(s.result);
  if(u.done.has(n))continue;
  if(result===4){u.done.add(n);u.solved++;u.penalty+=Number(s.in_date)+(u.wrong.get(n)||0)*1200;}
  else if(cePenalty||result!==11)u.wrong.set(n,(u.wrong.get(n)||0)+1);
}
assert.equal(ranked.length,expected.size,'one row per user');
assert.equal(new Set(ranked.map(r=>r[1])).size,ranked.length,'no duplicate or blank users');
let previous=null;
for(const row of ranked){
 const u=expected.get(row[1]);assert.ok(u);assert.equal(Number(row[3]),u.solved);
 const seconds=row[4].split(':').reduce((sum,n)=>sum*60+Number(n),0);assert.equal(seconds,u.penalty,'penalty is relative seconds exactly once');
 if(previous)assert.ok(previous.solved>u.solved||(previous.solved===u.solved&&previous.penalty<=u.penalty),'rank order');previous=u;
}
const labels=[...replay.matchAll(/title="Problem ([^"]+)"/g)].map(m=>m[1]).join('');
assert.equal(labels,'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'.slice(0,labels.length));
// Exercise the actual shipped trend function with duplicate AC and a zero-second AC.
const trendSource=replay.slice(replay.indexOf('function computeTrend(){'),replay.indexOf('$(function(){',replay.indexOf('function computeTrend(){')));
const context={solutions:[{user_id:'a',nick:'A',num:0,result:4,in_date:0},{user_id:'a',nick:'A',num:0,result:4,in_date:20},{user_id:'b',nick:'B',num:0,result:4,in_date:30},{user_id:'b',nick:'B',num:1,result:4,in_date:40}],SELF:null,CE_PENALTY:true};
vm.createContext(context);vm.runInContext(trendSource,context);const trend=context.computeTrend();
assert.ok(trend,'trend data exists');
const datasets=trend.datasets||trend;
assert.equal(datasets[0].label,'B','duplicate AC must not promote A above B');
assert.equal(datasets[0].data.length,3,'only first AC per problem creates a trend event');
assert.equal(datasets[0].data.at(-1).y,1);
for(const route of ['registerpage.php','loginpage.php','modifypage.php']){
 const b=await get('/'+route);assert.match(b,/name="viewport" content="width=device-width, initial-scale=1"/);
}
const scroll=await get('/contestrank3.php?cid=1163');assert.doesNotMatch(scroll,/src="mathjax\/MathJax.js/);
const news=await get('/viewnews.php?id=1016');
assert.doesNotMatch(news,/ti<x>tle|li<x>nk\.zhihu/,'stored news markup is repaired');
for(const id of [518930,513327,513328,513329]) assert.ok(news.includes('href="https://vjudge.net/contest/'+id+'"'),'direct VJudge link '+id);
assert.match(await get('/admin/js/popper.min.js'),/Popper/,'vendored admin dependency is served');
console.log(JSON.stringify({status:'passed',httpChecks:checks,replayUsers:ranked.length,replaySubmissions:submissions.length,problemLabels:labels.length},null,2));
