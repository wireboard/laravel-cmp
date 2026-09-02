/**
 * Runs the WireBoard view together with the SPA bridge in a real Chromium,
 * with the vendor SDK replaced by a stub that records every call, and checks
 * what the tracker would send: one page view per page, each under its own
 * address, referrer and title, whether the visitor navigated before or after
 * the tracker came up, and in both loading modes.
 *
 *   npm install && npx playwright install chromium && npm run test:browser
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const blade = require('./blade');

const view = (file) => fs.readFileSync(path.join(__dirname, '../../resources/views/components/', file), 'utf8');

const bridge = blade.scripts(blade.render(view('spa-bridge.blade.php'), {
    '$hashRouting': false,
    '$titleWait': 250,
    '$watchHistory': true,
    '$events': ['inertia:navigate'],
}));

const wireboard = (mode) => blade.scripts(blade.render(view('wireboard.blade.php'), {
    "$loadingMode === 'consent_required'": mode === 'consent_required',
    '$sdkUrl': 'https://static.wireboard.io/wireboard.js',
    '$eventsUrl': 'https://static.wireboard.io/events.min.js',
    "$config['pipeline']": 'pipeline.test',
    "$config['app_id']": 'app',
    '$performanceTiming': true,
    "!empty($config['load_events_script'])": false,
    '!empty($publisher)': true,
    '$publisher': 'pub',
    "(int) ($config['initialization_timeout'] ?? 2000)": 400,
}));

// Stands in for the SDK: takes over the queue the snippet created and records
// every call, the way the real one replays the queue on load.
const STUB_SDK = `(function () {
    var calls = window.__calls = [];
    var queued = (window.wireboard && window.wireboard.q) || [];
    window.wireboard = function () { calls.push(Array.prototype.slice.call(arguments)); };
    queued.forEach(function (args) { window.wireboard.apply(null, args); });
})();`;

const ORIGIN = 'http://app.test';
const CTX = [{ schema: 'wb:io.wireboard/publisher', data: { publisher: 'pub' } }];

// A framework navigation: the address moves first, the title lands as the
// new page mounts.
const nav = (url, title) => `history.pushState({}, '', '${url}'); setTimeout(function () { document.title = '${title}'; }, 1);`;
const consent = () => `window.dispatchEvent(new CustomEvent('consent.update', { detail: { analytics_storage: 'granted' } }));`;
const at = (ms, code) => `setTimeout(function () { ${code} }, ${ms});`;

const pageView = (url, referrer, title) => [
    ...(url ? [['setCustomUrl', ORIGIN + url]] : []),
    ...(referrer ? [['setReferrerUrl', ORIGIN + referrer]] : []),
    ['trackPageView', title, CTX],
];
const boot = (cookies) => [
    ['newTracker', 'wb', 'pipeline.test', { appId: 'app', forceSecureTracker: true, useCookies: cookies, useLocalStorage: cookies, contexts: { performanceTiming: true } }],
    ['enableActivityTracking', 5, 10],
];

const scenarios = {
    'cookieless: nobody navigates before the tracker comes up': {
        mode: 'cookieless_first',
        run: at(600, nav('/b', 'B - App')),
        expect: [...boot(false), ...pageView(null, null, null), ...pageView('/b', '/landing', 'B - App')],
    },
    'cookieless: two pages reached before the tracker comes up': {
        mode: 'cookieless_first',
        run: at(50, nav('/b', 'B - App')) + at(150, nav('/c', 'C - App')) + at(700, nav('/d', 'D - App')),
        expect: [
            ...boot(false),
            ...pageView('/landing', null, 'Landing - App'),
            ...pageView('/b', '/landing', 'B - App'),
            ...pageView('/c', '/b', 'C - App'),
            ...pageView('/d', '/c', 'D - App'),
        ],
    },
    'cookieless: consent given at once, then navigation': {
        mode: 'cookieless_first',
        run: at(20, consent()) + at(200, nav('/b', 'B - App')) + at(600, consent()),
        expect: [...boot(true), ...pageView(null, null, null), ...pageView('/b', '/landing', 'B - App'), ['upgradeStorage', { useCookies: true, useLocalStorage: true }]],
    },
    'cookieless: a same-title page flushed by the next navigation keeps its address': {
        mode: 'cookieless_first',
        run: at(600, nav('/b', 'Landing - App')) + at(660, nav('/c', 'C - App')),
        expect: [...boot(false), ...pageView(null, null, null), ...pageView('/b', '/landing', 'Landing - App'), ...pageView('/c', '/b', 'C - App')],
    },
    'consent required: nothing before consent, own address after it': {
        mode: 'consent_required',
        run: at(50, nav('/b', 'B - App')) + at(300, consent()) + at(600, nav('/c', 'C - App')),
        expect: [...boot(true), ...pageView(null, null, null), ...pageView('/c', '/b', 'C - App')],
    },
};

(async () => {
    const browser = await chromium.launch();
    let failures = 0;

    for (const [name, scenario] of Object.entries(scenarios)) {
        const html = `<!doctype html><html><head><meta charset="utf-8"><title>Landing - App</title></head><body>`
            + `<script>${bridge}</script><script>${wireboard(scenario.mode)}</script></body></html>`;

        const page = await browser.newPage();
        await page.route('**/*', (route) => {
            const url = route.request().url();

            if (url.startsWith(ORIGIN)) {
                return route.fulfill({ contentType: 'text/html', body: html });
            }

            if (url === 'https://static.wireboard.io/wireboard.js') {
                return route.fulfill({ contentType: 'application/javascript', body: STUB_SDK });
            }

            return route.abort();
        });
        page.on('pageerror', (error) => console.log('     page error: ' + error.message));
        await page.goto(ORIGIN + '/landing');
        await page.evaluate(scenario.run);
        await page.waitForTimeout(1300);
        const calls = await page.evaluate(() => window.__calls || []);
        await page.close();

        const ok = JSON.stringify(calls) === JSON.stringify(scenario.expect);
        if (!ok) {
            failures++;
        }
        console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}` + (ok ? '' : `\n     got      ${JSON.stringify(calls)}\n     expected ${JSON.stringify(scenario.expect)}`));
    }

    await browser.close();
    console.log(failures ? `${failures} scenario(s) failed` : 'all scenarios pass');
    process.exit(failures ? 1 : 0);
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
