// Run via playwright-cli run-code in an authenticated LOCAL fixture composer.
async (page) => {
    if (!/^http:\/\/127\.0\.0\.1:/.test(page.url())) throw Error('Local fixtures only');
    let checks = 0;
    const check = (ok,message) => { if (!ok) throw Error(message); checks++; };
    await page.reload();
    const source = '## 关键思路\n\n**前缀和**，公式 $O(n)$。\n\n$$\n\\sum_{i=1}^{n} a_i\n$$\n\n```cpp\n#include <iostream>\nint main() { return 0; }\n```';
    await page.locator('#editorial-title').fill('浏览器回归验证');
    await page.locator('#editorial-content').fill(source);
    await page.getByRole('button',{name:'对照',exact:true}).click();
    await page.waitForFunction(() => document.querySelectorAll('#editorial-preview .katex').length === 2);
    await page.waitForFunction(() => document.querySelector('#editorial-preview .ed-token-keyword'));
    check(await page.locator('#editorial-preview h2').textContent() === '关键思路','Markdown headings');
    check(await page.locator('#editorial-preview strong').textContent() === '前缀和','Markdown emphasis');
    const security = await page.evaluate(() => {
        const target = document.createElement('div'); document.body.appendChild(target);
        const vectors = ['<script>window.edXss=1</script>','<img src=x onerror="window.edXss=1">','<svg onload="window.edXss=1"></svg>','[x](javascript:alert(1))','[x](data:text/html,bad)','<iframe srcdoc="bad"></iframe>','![x](https://example.com/x.png)','$\\href{javascript:alert(1)}{x}$','$\\htmlClass{evil}{x}$'];
        let safe = true;
        for (const vector of vectors) {
            editorialMarkdown(vector,target);
            if (target.querySelector('script,img,svg,iframe,object,embed') || Array.from(target.querySelectorAll('*')).some(e => Array.from(e.attributes).some(a => /^on/i.test(a.name))) || Array.from(target.querySelectorAll('a')).some(a => !['http:','https:','mailto:'].includes(a.protocol))) safe = false;
        }
        target.remove(); return safe && !window.edXss;
    });
    check(security,'Raw HTML, math and unsafe URLs cannot execute');
    await page.waitForFunction(() => document.getElementById('draft-status').textContent.includes('已保存'));
    await page.reload();
    check(await page.locator('#editorial-content').inputValue() === source,'Draft survives reload');
    await page.getByRole('button',{name:'插入题解模板',exact:true}).click();
    check((await page.locator('#editorial-content').inputValue()).includes(source),'Template preserves existing text');
    await page.locator('#editorial-content').fill(source);
    await page.waitForFunction(() => document.getElementById('draft-status').textContent.includes('已保存'));
    const second = await page.context().newPage(); await second.goto(page.url());
    await second.locator('#editorial-content').fill('另一标签页的草稿');
    await second.waitForFunction(() => document.getElementById('draft-status').textContent.includes('已保存'));
    await page.locator('#draft-restore').waitFor({state:'visible'});
    check(await page.locator('#editorial-content').inputValue() === source,'Other tab cannot overwrite current text');
    await page.getByRole('button',{name:'恢复草稿',exact:true}).click();
    check(await page.locator('#editorial-content').inputValue() === '另一标签页的草稿','Explicit conflict recovery');
    await second.close();
    await page.evaluate(() => { window.edOriginalSetItem = Storage.prototype.setItem; Storage.prototype.setItem = function () { throw new Error('quota'); }; });
    await page.locator('#editorial-content').fill('配额失败时保留的正文');
    await page.waitForFunction(() => document.getElementById('draft-status').textContent.includes('保存失败'));
    check(await page.locator('#editorial-content').inputValue() === '配额失败时保留的正文','Storage failure preserves editor');
    await page.evaluate(() => { Storage.prototype.setItem = window.edOriginalSetItem; });
    await page.locator('#editorial-content').press('Control+s');
    await page.waitForFunction(() => document.getElementById('draft-status').textContent.includes('已保存'));
    check(!(await page.locator('#draft-restore').isVisible()),'Storage recovery does not invent a conflict');
    await page.locator('#editorial-content').fill(source);
    await page.getByRole('button',{name:'预览',exact:true}).click();
    check(!(await page.locator('#editorial-content').isVisible()),'Preview mode');
    await page.getByRole('button',{name:'专注写作',exact:true}).click();
    check(await page.locator('body').evaluate(e => e.classList.contains('ed-focus-writing')),'Focus mode');
    await page.keyboard.press('Escape');
    check(!await page.locator('body').evaluate(e => e.classList.contains('ed-focus-writing')),'Escape exits focus');
    await page.setViewportSize({width:390,height:844});
    await page.getByRole('button',{name:'编辑',exact:true}).click();
    check(await page.locator('.ed-composer').evaluate(e => e.scrollWidth <= e.clientWidth),'Mobile editor has no overflow');
    await page.setViewportSize({width:1440,height:1000});
    await page.getByRole('button',{name:'对照',exact:true}).click();
    await page.waitForFunction(() => document.getElementById('draft-status').textContent.includes('已保存'));
    await page.evaluate(n => { document.body.dataset.editorialChecks = String(n); },checks);
    return 'PASS: ' + checks + ' browser checks (rendering, security, drafts, conflicts, recovery, focus, mobile).';
}
