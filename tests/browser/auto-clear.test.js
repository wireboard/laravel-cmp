/**
 * Runs the built consent script with the vanilla-cookieconsent library in a
 * real Chromium, with categories written the way config/cmp.php writes them,
 * and checks that withdrawing analytics consent deletes the analytics cookies
 * and nothing else. Up to 1.6.3 the script read only the library's own names
 * (autoClear, readOnly), so the documented auto_clear list was ignored and
 * withdrawal cleared no cookie at all. Up to 1.6.4, Google Analytics was
 * not told before the reload that follows a withdrawal, and wrote its session
 * cookie again in between. Up to 1.6.5, the WireBoard tracker's localStorage
 * entries (wb_duid device id, visit state, event queue) survived, and nothing
 * was cleaned on later page loads while consent stayed refused.
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
// What the WireBoard tracker stores with consent, plus an app's own entry
const PLANTED_STORAGE = ['wb_duid', 'wireboard_step', 'wireboard_last_request', 'wireboard_last_url', 'wireboardOutQueue_wireboard_wb_post2', 'app_setting'];

// Stands in for GA4: once started, it writes its session cookie every 10 ms
// until Consent Mode says analytics_storage is denied, as the real one keeps
// writing on every hit while it runs.
const FAKE_GA = `window.__analyticsStorage = 'granted';
window.gtag = function (command, action, params) {
    if (command === 'consent' && params && params.analytics_storage) window.__analyticsStorage = params.analytics_storage;
};
window.__startGa = function () {
    setInterval(function () {
        if (window.__analyticsStorage !== 'denied') document.cookie = '_ga_LIVE=1; path=/; max-age=3600';
    }, 10);
};`;

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

const documented = {
    necessary: { enabled: true, read_only: true },
    analytics: { enabled: true, read_only: false, auto_clear: ANALYTICS_COOKIES },
};

const scenarios = {
    'config/cmp.php names (read_only, auto_clear)': { config: config(documented) },
    'library names (readOnly, autoClear)': {
        config: config({
            necessary: { enabled: true, readOnly: true },
            analytics: { enabled: true, readOnly: false, autoClear: ANALYTICS_COOKIES },
        }),
    },
    'an extra category with its own auto_clear': {
        config: config({
            necessary: { enabled: true, read_only: true },
            analytics: { enabled: true, read_only: false, auto_clear: ['/^_ga/', '_gid'] },
            marketing: { enabled: false, read_only: false, auto_clear: ['/^_wb_/'] },
        }),
    },
    'GA4 still writing its session cookie when consent is withdrawn': { config: config(documented), gaKeepsWriting: true },
    'leftovers erased on the next page load while consent stays refused': { config: config(documented), refusedThenReload: true },
};

(async () => {
    const browser = await chromium.launch();
    let failures = 0;

    for (const [name, scenario] of Object.entries(scenarios)) {
        const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>App</title></head><body>`
            + `<script>${FAKE_GA}</script>`
            + `<script>${file('dist/js/cookieconsent.umd.min.js')}</script>`
            + `<script>window.__cmpType = 'custom'; window.__cmpConfig = ${JSON.stringify(scenario.config)};</script>`
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

        const plant = () => page.evaluate(([cookies, keys]) => {
            cookies.forEach((cookie) => { document.cookie = `${cookie}=1; path=/; max-age=3600`; });
            keys.forEach((key) => localStorage.setItem(key, '1'));
        }, [PLANTED, PLANTED_STORAGE]);

        if (scenario.refusedThenReload) {
            // Refused first (no reload, nothing changed), then data appears
            // (a script after the withdrawal's reload, or an older version)
            await page.evaluate(() => window.CookieConsent.acceptCategory([]));
            await plant();
            await page.reload();
            // Consent already given: no banner is built, wait for the library
            await page.waitForFunction(() => window.CookieConsent && window.CookieConsent.validConsent());
        } else {
            await page.evaluate(() => window.CookieConsent.acceptCategory('all'));
            await plant();
            if (scenario.gaKeepsWriting) {
                await page.evaluate(() => window.__startGa());
                await page.waitForTimeout(50);
            }
            // A withdrawal reloads the page 100 ms later: wait for the new one.
            await Promise.all([
                page.waitForEvent('load', { timeout: 3000 }).catch(() => null),
                page.evaluate(() => window.CookieConsent.acceptCategory([])),
            ]);
        }
        await page.waitForTimeout(300);

        const left = (await context.cookies()).map((cookie) => cookie.name).sort();
        const leftStorage = (await page.evaluate(() => Object.keys(localStorage))).sort();
        await context.close();

        const expected = ['cc_cookie', 'unrelated'];
        const expectedStorage = ['app_setting'];
        const ok = JSON.stringify(left) === JSON.stringify(expected)
            && JSON.stringify(leftStorage) === JSON.stringify(expectedStorage);
        if (!ok) {
            failures++;
        }
        console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}` + (ok ? '' : `\n     left     ${JSON.stringify(left)} ${JSON.stringify(leftStorage)}\n     expected ${JSON.stringify(expected)} ${JSON.stringify(expectedStorage)}`));
    }

    await browser.close();
    console.log(failures ? `${failures} scenario(s) failed` : 'all scenarios pass');
    process.exit(failures ? 1 : 0);
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
