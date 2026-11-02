// Run: node web/tests/editorial_prose_test.mjs
// Compare the real editor counter against the renderer and the HTTP test fixtures.
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { runInNewContext } from 'node:vm';
const require = createRequire(import.meta.url);
const marked = require('../template/bs3/marked.min.js');
const source = readFileSync(new URL('../template/syzoj/js/editorial-editor.js', import.meta.url), 'utf8');
const start = source.indexOf('    function proseLength(');
const end = source.indexOf('    function counts()', start);
if (start < 0 || end < 0) throw new Error('Editor prose counter not found');
const counter = runInNewContext(source.slice(start, end) + '\nproseLength;', {format:'markdown'});
const cases = JSON.parse(readFileSync(new URL('editorial_prose_cases.json', import.meta.url), 'utf8'));
for (const fixture of cases) {
    const html = marked.parse(fixture.content, {gfm:true,breaks:true,headerIds:false,mangle:false});
    const prose = html.replace(/<pre\b[^>]*>[\s\S]*?<\/pre>/gi, '').replace(/<code\b[^>]*>[\s\S]*?<\/code>/gi, '').replace(/<[^>]+>/g, '');
    const renderedCount = (prose.match(/[\u4e00-\u9fff]/g) || []).length;
    const actual = counter(fixture.content);
    if (renderedCount !== fixture.expected || actual !== fixture.expected) {
        throw new Error(fixture.name + ': expected ' + fixture.expected + ', renderer ' + renderedCount + ', editor ' + actual);
    }
}
console.log('PASS: ' + cases.length + ' editorial prose cases agree with the shipped Markdown renderer.');
