// Playwright CLI run-code function. Open the target site's homepage first.
// Read-only: checks layout, navigation reachability and mobile viewport behavior.
async page => {
    const origin = await page.evaluate(() => location.origin);
    const results = [];
    for (const width of [320, 390, 768, 999, 1024, 1200, 1280, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        const state = await page.evaluate(() => {
            const card = document.querySelector('[aria-labelledby="lab-home-title"]');
            const box = card.getBoundingClientRect();
            const columns = [...document.querySelectorAll('.syzoj-container > .grid > .column')];
            return {
                width: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                cardCount: document.querySelectorAll('#lab-home-title').length,
                previous: card.previousElementSibling.textContent.includes('上月做题榜'),
                next: card.nextElementSibling.tagName === 'STYLE'
                    ? card.nextElementSibling.nextElementSibling.textContent.includes('友情链接')
                    : card.nextElementSibling.textContent.includes('友情链接'),
                buttonsInside: [...card.querySelectorAll('a')].every(a => {
                    const r = a.getBoundingClientRect();
                    return r.left >= box.left && r.right <= box.right;
                }),
                fullColumns: columns.every(c => Math.abs(c.getBoundingClientRect().width - columns[0].getBoundingClientRect().width) < 1),
                icon: getComputedStyle(card.querySelector('i'), '::before').content
            };
        });
        if (state.documentWidth > width || state.cardCount !== 1 || !state.previous || !state.next
            || !state.buttonsInside || (width <= 999 && !state.fullColumns)
            || ['none', 'normal', '""'].includes(state.icon)) {
            throw new Error('Homepage layout regression: ' + JSON.stringify(state));
        }
        const account = page.locator('.oj-account-menu');
        const target = await account.count() ? account : page.getByRole('link', { name: '注册', exact: true });
        await target.focus();
        const bounds = await target.boundingBox();
        if (!bounds || bounds.x < 0 || bounds.x + bounds.width > width + 1) {
            throw new Error('Account navigation is not reachable at ' + width);
        }
        if (await account.count()) {
            await account.getByRole('link', { name: /我的申请与反馈/ }).focus();
            const visible = await account.locator(':scope > .menu').evaluate(menu => {
                const r = menu.getBoundingClientRect();
                return r.left >= 0 && r.right <= innerWidth + 1
                    && menu.contains(document.elementFromPoint(r.left + r.width / 2, r.top + 25));
            });
            if (!visible) throw new Error('Account dropdown is clipped at ' + width);
        }
        results.push(state);
    }
    const context = await page.context().browser().newContext({
        viewport: { width: 390, height: 844 }, isMobile: true, deviceScaleFactor: 1
    });
    try {
        const mobile = await context.newPage();
        await mobile.goto(origin + '/index.php');
        const state = await mobile.evaluate(() => ({ width: innerWidth, scale: visualViewport.scale }));
        if (state.width !== 390 || Math.abs(state.scale - 1) > 0.01) {
            throw new Error('Mobile homepage uses a scaled desktop viewport: ' + JSON.stringify(state));
        }
    } finally {
        await context.close();
    }
    return { passed: true, widths: results.map(r => r.width), mobileScale: 1 };
}
