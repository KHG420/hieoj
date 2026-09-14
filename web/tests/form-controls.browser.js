// playwright-cli run-code: administrator session with restored HnieOJ data (problem 1000, thread 84).
// Only edits browser fields/cookies; never submits code, posts, registrations or email.
async () => {
    const origin = await page.evaluate(() => location.origin);
    const errors = [], failures = [];
    const onError = e => errors.push(e.message);
    const onResponse = r => { if (r.status() >= 400) failures.push(r.url()); };
    page.on('pageerror', onError); page.on('response', onResponse);
    let checks = 0;
    const check = (ok, message) => { if (!ok) throw Error(message); checks++; };
    try {
        // Recover the invalid cookie written by the previous implementation.
        await page.context().addCookies([{name:'lastlang', value:'undefined', url:origin}]);
        await page.goto(origin+'/submitpage.php?id=1000');
        check(await page.locator('#language').count() === 1, 'Use an authenticated browser session');
        await page.waitForFunction(() => typeof editor !== 'undefined');
        for (const [language, mode] of [['0','c_cpp'],['1','c_cpp'],['2','pascal'],['3','java'],['6','python'],['13','c_cpp'],['14','c_cpp']]) {
            await page.getByLabel('语言', {exact:true}).selectOption(language);
            await page.reload();
            await page.waitForFunction(expected => typeof editor !== 'undefined' && editor.getSession().getMode().$id === 'ace/mode/'+expected, mode);
            check(await page.getByLabel('语言', {exact:true}).inputValue() === language, 'Language survives reload: '+language);
        }
        await page.goto(origin+'/thread.php?tid=84');
        const input = page.getByRole('textbox', {name:'写回复'});
        await input.fill('QA unsent draft');
        await page.getByRole('link', {name:'回复',exact:true}).first().click();
        check((await input.inputValue()).startsWith('Reply to :'), 'Reply updates an already edited textarea');
        await input.fill('QA second unsent draft');
        await page.getByRole('link', {name:'引用',exact:true}).first().click();
        check((await input.inputValue()).startsWith('> '), 'Quote updates the current textarea value');
        await input.fill('');
        await page.goto(origin+'/registerpage.php');
        const college = page.getByLabel('学院*', {exact:true});
        await college.selectOption({label:'体育科学与工程学院'});
        await page.waitForFunction(() => !document.querySelector('#school_manual').disabled);
        check(await page.locator('#school_manual').isVisible(), 'Manual class is visible without catalog data');
        check(!await page.locator('#school_sel').isVisible(), 'Unused select is actually hidden despite Semantic UI styles');
        check(await page.locator('#school_label').getAttribute('for') === 'school_manual', 'Label targets active manual input');
        await college.selectOption({label:'信息科学与工程学院'});
        await page.waitForFunction(() => document.querySelector('#school_sel').options.length > 1);
        check(await page.locator('#school_sel').isVisible() && !await page.locator('#school_manual').isVisible(), 'Switching college restores only the select');
        check(await page.locator('#school_label').getAttribute('for') === 'school_sel', 'Label returns to select');
        const fields = await page.evaluate(() => [...new FormData(document.querySelector('.oj-registration form')).keys()].filter(k => k === 'school'));
        check(fields.length === 1, 'Only one class field is submitted');
        await page.getByRole('button', {name:'重置',exact:true}).click();
        await page.waitForFunction(() => document.querySelector('#xueyuan_sel').value === '' && document.querySelector('#school_sel').options.length === 1);
        check(!await page.locator('#school_manual').isVisible(), 'Reset clears manual field mode');
        const viewport = page.viewportSize() || await page.evaluate(() => ({width:innerWidth,height:innerHeight}));
        await page.setViewportSize({width:390,height:844});
        for (const path of ['/problemset.php','/viewnews.php?id=1016','/registerpage.php','/admin/problem_list.php','/admin/contest_list.php']) {
            await page.goto(origin+path);
            await page.evaluate(() => document.fonts.ready);
            check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No full-page mobile overflow: '+path);
            if (path === '/problemset.php') {
                check(await page.evaluate(() => [...document.querySelectorAll('.search-box')].every(form => {
                    const input = form.querySelector('input').getBoundingClientRect();
                    const button = form.querySelector('button').getBoundingClientRect();
                    const icon = form.querySelector('.search-icon').getBoundingClientRect();
                    return input.height >= 40 && button.left >= input.right && icon.top >= input.top && icon.bottom <= input.bottom && icon.right <= input.right;
                })), 'Search and jump icons stay in their inputs, clear of the buttons');
            }
        }
        if (viewport) await page.setViewportSize(viewport);
        check(errors.length === 0, 'No JavaScript exceptions: '+errors.join(', '));
        check(failures.length === 0, 'No failed resources: '+failures.join(', '));
        await page.evaluate(result => window.__controlAudit = result, {status:'passed',checks,errors,failures});
    } finally {
        page.off('pageerror', onError); page.off('response', onResponse);
    }
}
