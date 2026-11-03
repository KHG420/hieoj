import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const template=fs.readFileSync(new URL('../template/syzoj/ranklist.php',import.meta.url),'utf8');
const script=template.match(/<script>([\s\S]*?)<\/script>/)[1];
async function run(filters,data,fail=false) {
  const calls=[],selection={options:['server selection']};
  const source=script.replace(/<\?php echo json_encode\(\$(xy|nj|school)\)\?>/g,(_,key)=>JSON.stringify(filters[key]??''));
  const context={URLSearchParams,Option:function(text,value,defaultSelected,selected){Object.assign(this,{text,value,selected:!!selected});},document:{getElementById(){return {replaceChildren(...options){selection.options=options;}};}},fetch(url,options){calls.push({url,options});return fail?Promise.reject(new Error('offline')):Promise.resolve({ok:true,json:()=>Promise.resolve(data)});}};
  vm.runInNewContext(source,context);
  await new Promise(resolve=>setImmediate(resolve));
  return {calls,selection};
}
const empty=await run({},[]);
assert.equal(empty.calls.length,0,'Unfiltered class list makes no redundant request');
const result=await run({xy:'信息学院',nj:'26',school:'计算机2602'},[{value:'计算机2601'},'计算机2602',null,{value:'<script>text</script>'}]);
const url=new URL(result.calls[0].url,'http://example.test/');
assert.equal(url.searchParams.get('nj'),'2026');
assert.equal(url.searchParams.get('xy'),'信息学院');
assert.equal(result.calls[0].options.credentials,'same-origin');
assert.equal(result.selection.options.length,4);
assert.equal(result.selection.options[2].selected,true);
assert.equal(result.selection.options[3].text,'<script>text</script>','Option text remains literal');
assert.equal((await run({nj:'26'},[],true)).selection.options[0],'server selection','Network failure retains the rendered choice');
assert.equal((await run({nj:'26'},{unexpected:true})).selection.options[0],'server selection','Malformed data retains the rendered choice');
console.log('PASS: 9 native ranking-filter checks');
