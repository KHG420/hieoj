// Isolated regression checks for the editorial draft cleanup and for the
// Markdown indentation the reader/reviewer receives. No browser, database or
// network is required: the real editor script runs against a minimal DOM stub,
// and the shipped Marked bundle is loaded with the same options as the page.
//
// Run with: node web/tests/editorial_draft_test.mjs
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { runInNewContext } from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const editor = readFileSync(join(here, '../template/syzoj/js/editorial-editor.js'), 'utf8');
const require = createRequire(import.meta.url);
const marked = require(join(here, '../template/bs3/marked.min.js'));
let checks = 0;
function check(ok, message) { if (!ok) throw new Error(message); checks++; }

// Run the real editor script with just enough DOM and storage to reach its
// draft-cleanup branch. Without a composer element it returns immediately
// after the comparison, so none of the other editor wiring is needed.
// `text` models a plain article (no editorialSource); `source` models Markdown.
function runCleanup(draft, article) {
    const key = 'oj-editorial:test:user:1';
    const store = new Map([[key, JSON.stringify(draft)]]);
    const localStorage = {
        getItem: function (k) { return store.has(k) ? store.get(k) : null; },
        setItem: function (k, v) { store.set(k, v); },
        removeItem: function (k) { store.delete(k); },
    };
    const body = {
        dataset: { submittedDraft: key, submittedTitle: article.title, contentFormat: article.format },
        textContent: article.text !== undefined ? article.text : article.source,
    };
    if (article.source !== undefined) body.editorialSource = article.source;
    const document = {
        querySelectorAll: function () { return [body]; },
        getElementById: function () { return null; },
        addEventListener: function () {},
    };
    runInNewContext(editor, { document: document, localStorage: localStorage, window: { addEventListener: function () {} } });
    return store.has(key);
}

try {
    // A body whose first line is indented and which ends with a newline is the
    // exact shape that used to leave a stale draft behind.
    const indented = "    int main() {\n        return 0;\n    }\n";
    check(indented !== indented.trim(), 'Fixture keeps whitespace the old trim() comparison removed');
    check(!runCleanup(
        { title: ' 缩进题解 ', content: indented, format: 'markdown' },
        { title: '缩进题解', source: indented, format: 'markdown' }
    ), 'Matching draft with leading indentation and trailing newline is cleared');
    check(!runCleanup(
        { title: '换行题解', content: '解释正文加末尾换行\n', format: 'markdown' },
        { title: '换行题解', source: '解释正文加末尾换行\n', format: 'markdown' }
    ), 'Matching draft with a trailing newline is cleared');
    const crlf = "    int main() {\r\n        return 0;\r\n    }\r\n";
    check(!runCleanup(
        { title: 'CRLF 题解', content: crlf, format: 'markdown' },
        { title: 'CRLF 题解', source: indented, format: 'markdown' }
    ), 'CRLF draft matches the LF article body');
    check(!runCleanup(
        { title: '纯文本', content: '  纯文本首行缩进\n    第二行更深\n', format: 'plain' },
        { title: '纯文本', text: '  纯文本首行缩进\n    第二行更深\n', format: 'plain' }
    ), 'Plain article clears a draft with surrounding whitespace');
    check(runCleanup(
        { title: '缩进题解', content: indented + '稍后补充的段落', format: 'markdown' },
        { title: '缩进题解', source: indented, format: 'markdown' }
    ), 'A newer draft with different body is kept');
    check(runCleanup(
        { title: '另一个标题', content: indented, format: 'markdown' },
        { title: '缩进题解', source: indented, format: 'markdown' }
    ), 'A draft with a different title is kept');
    check(runCleanup(
        { title: '缩进题解', content: indented, format: 'plain' },
        { title: '缩进题解', source: indented, format: 'markdown' }
    ), 'A draft in another format is kept');

    // The shipped renderer must keep an indented body as a code block; the old
    // trimmed body collapsed into a paragraph instead.
    const options = { gfm: true, breaks: true, headerIds: false, mangle: false };
    const rendered = marked.parse(indented, options);
    check(rendered.indexOf('<pre><code>') !== -1, 'Indented body renders as a code block');
    check(rendered.indexOf('\n    return 0;') !== -1, 'Code indentation survives rendering');
    const trimmed = marked.parse(indented.trim(), options);
    check(trimmed.indexOf('<p>') !== -1 && trimmed.indexOf('<pre><code>') === -1, 'A trimmed body would collapse into a paragraph');

    console.log('PASS: ' + checks + ' editorial draft and indentation checks.');
} catch (error) {
    console.error('FAIL: ' + error.message);
    process.exit(1);
}
