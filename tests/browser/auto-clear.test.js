/**
 * Runs the built consent script with the vanilla-cookieconsent library in a
 * real Chromium, with categories written the way config/cmp.php writes them,
 * and checks that withdrawing analytics consent deletes the analytics cookies
 * and nothing else. Up to 1.6.3 the script read only the library's own names
 * (autoClear, readOnly), so the documented auto_clear list was ignored and
 * withdrawal cleared no cookie at all.
 *
 *   npm install && npx playwright install chromium && npm run test:browser
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const file = (name) => fs.readFileSync(path.join(__dirname, '../../', name), 'utf8');

const ORIGIN = 'http://app.test';
const ANALYTICS_COOKIES = ['/^_ga/', '_gid', '/^_wb_/'];
const PLANTED = ['_ga', '_ga_ABC123', '_gid', '_wb_id.914d', '_wb_ses.914d', 'unrelated'];

const config = (categories) => ({
    translationsPath: '/vendor/cmp/translations/',
    supportedLanguages: ['en'],
    cookieName: 'cc_cookie',
    cookieExpiryDays: 365,
    mode: 'opt-in',
    disablePageInteraction: false,
    guiOptions: {},
    categories,
    showRejectButton: true,
    showSettingsButton: false,
    settingsButtonPosition: 'bottom_left',
    privacyPolicyUrl: null,
    termsUrl: null,
});

const scenarios = {
    'config/cmp.php names (read_only, auto_clear)': config({
        necessary: { enabled: true, read_only: true },
        analytics: { enabled: true, read_only: false, auto_clear: ANALYTICS_COOKIES },
    }),
    'library names (readOnly, autoClear)': config({
        necessary: { enabled: true, readOnly: true },
        analytics: { enabled: true, readOnly: false, autoClear: ANALYTICS_COOKIES },
    }),
    'an extra category with its own auto_clear': config({
        necessary: { enabled: true, read_only: true },
        analytics: { enabled: true, read_only: false, auto_clear: ['/^_ga/', '_gid'] },
        marketing: { enabled: false, read_only: false, auto_clear: ['/^_wb_/'] },
    }),
};

(async () => {
    const browser = await chromium.launch();
    let failures = 0;

    for (const [name, cmpConfig] of Object.entries(scenarios)) {
        const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>App</title></head><body>`
            + `<script>${file('dist/js/cookieconsent.umd.min.js')}</script>`
            + `<script>window.__cmpType = 'custom'; window.__cmpConfig = ${JSON.stringify(cmpConfig)};</script>`
            + `<script>${file('dist/js/consent-cmp.min.js')}</script></body></html>`;

        const context = await browser.newContext();
        // The library hides its banner from automated browsers.
        await context.addInitScript(() => Object.defineProperty(Navigator.prototype, 'webdriver', { get: () => false }));
        const page = await context.newPage();
        await page.route('**/*', (route) => {
            const url = route.request().url();

            if (url === ORIGIN + '/vendor/cmp/translations/en.json') {
                return route.fulfill({ contentType: 'application/json', body: file('resources/translations/en.json') });
            }

            if (url.startsWith(ORIGIN)) {
                return route.fulfill({ contentType: 'text/html', body: html });
            }

            return route.abort();
        });
        page.on('pageerror', (error) => console.log('     page error: ' + error.message));
        await page.goto(ORIGIN + '/');
        await page.waitForFunction(() => window.CookieConsent && document.querySelector('#cc-main'));

        await page.evaluate(() => window.CookieConsent.acceptCategory('all'));
        await page.evaluate((names) => names.forEach((cookie) => { document.cookie = `${cookie}=1; path=/; max-age=3600`; }), PLANTED);
        await page.evaluate(() => window.CookieConsent.acceptCategory([]));
        await page.waitForTimeout(500);

        const left = (await context.cookies()).map((cookie) => cookie.name).sort();
        await context.close();

        const expected = ['cc_cookie', 'unrelated'];
        const ok = JSON.stringify(left) === JSON.stringify(expected);
        if (!ok) {
            failures++;
        }
        console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}` + (ok ? '' : `\n     left     ${JSON.stringify(left)}\n     expected ${JSON.stringify(expected)}`));
    }

    await browser.close();
    console.log(failures ? `${failures} scenario(s) failed` : 'all scenarios pass');
    process.exit(failures ? 1 : 0);
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
