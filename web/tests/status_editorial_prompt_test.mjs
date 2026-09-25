import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source = fs.readFileSync(new URL('../template/syzoj/auto_refresh.js', import.meta.url), 'utf8');
function page(results, eligible) {
  const elements = {
    'editorial-reward-prompt': {hidden: true},
    'editorial-reward-problem': {}, 'editorial-reward-link': {}
  };
  const rows = [{}].concat(results.map(([sid, result]) => ({cells: Array.from({length: 12}, (_, i) => ({innerHTML: i === 0 ? String(sid) : '', result: i === 5 ? String(result) : undefined}))})));
  elements['result-tab'] = {rows};
  const requests = [];
  function XMLHttpRequest() { requests.push(this); this.open = () => {}; this.send = () => {}; }
  const context = {document: {getElementById: id => elements[id]}, editorial_prompt_problems: eligible,
    judge_color: [], judge_result: [], XMLHttpRequest, setTimeout() {},
    $: value => ({find() { return {attr() {return value.result;}}; }, append() {}, mouseover() {}, hide() {}})};
  context.window = context;
  vm.createContext(context); vm.runInContext(source, context);
  return {elements, context, respond(sid, result) {
    context.fresh_result(sid);
    const request = requests.at(-1);
    Object.assign(request, {readyState: 4, status: 200, responseText: `${result},1 KB,1 ms,none,100`});
    request.onreadystatechange();
  }};
}
let p = page([[20,4]], {20:1000});
assert.equal(p.elements['editorial-reward-prompt'].hidden, false);
assert.equal(p.elements['editorial-reward-link'].href, 'solutions.php?problem_id=1000&write=1');
p = page([[20,0]], {20:1000});
assert.equal(p.elements['editorial-reward-prompt'].hidden, true);
p.respond(20,4);
assert.equal(p.elements['editorial-reward-prompt'].hidden, false);
p.respond(20,4);
assert.equal(p.context.editorial_prompt_solution,20);
for (const result of [0,6,11]) {
 p = page([[20,result]], {20:1000});
 assert.equal(p.elements['editorial-reward-prompt'].hidden,true);
 if (result===0) {p.respond(20,6); assert.equal(p.elements['editorial-reward-prompt'].hidden,true);}
}
p = page([[20,4],[19,0]], {});
p.respond(19,4);
assert.equal(p.elements['editorial-reward-prompt'].hidden,true);
p = page([[22,4],[20,4]], {20:1000,22:1001});
assert.equal(p.elements['editorial-reward-problem'].textContent,'P1001');
p.context.show_editorial_prompt(20);
assert.equal(p.elements['editorial-reward-problem'].textContent,'P1001');
console.log('PASS: initial AC, polled AC, no duplicate prompt, WA/CE, ineligible submissions, latest AC link');
