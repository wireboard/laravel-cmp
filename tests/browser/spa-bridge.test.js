/**
 * Runs the SPA bridge in a real Chromium against the navigation patterns of
 * Inertia, Turbo-style routers and hand-announced views, and checks that each
 * navigation is reported exactly once, with its own address, its own title and
 * the right referrer. Both signals are covered: the Navigation API and the
 * History patch used where the API is missing.
 *
 *   npm install && npx playwright install chromium && npm run test:browser
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const blade = require('./blade');

// The view as the component renders it for a host listening to
// inertia:navigate with the default settings.
const bridge = blade.scripts(blade.render(fs.readFileSync(path.join(__dirname, '../../resources/views/components/spa-bridge.blade.php'), 'utf8'), {
    '$hashRouting': false,
    '$titleWait': 250,
    '$watchHistory': true,
    '$events': ['inertia:navigate'],
}));

// The starting page: an Inertia head, the old page's title and meta tags.
const HTML = '<!doctype html><html><head><meta charset="utf-8"><meta data-inertia="old-only" content="x"><meta data-inertia="description" content="old desc"><title data-inertia="">Old - App</title></head><body>page</body></html>';

// Inertia's head rewrite (core/src/head.ts, Renderer.update): one synchronous
// pass that drops, replaces and appends the managed elements.
const INERTIA_PASS = `function inertiaPass(title) {
    var src = [];
    var t = document.createElement('title'); t.setAttribute('data-inertia', ''); t.textContent = title; src.push(t);
    var m = document.createElement('meta'); m.setAttribute('data-inertia', 'description'); m.setAttribute('content', 'new desc ' + Math.random()); src.push(m);
    var n = document.createElement('meta'); n.setAttribute('data-inertia', 'new-only'); n.setAttribute('content', 'y'); src.push(n);
    Array.from(document.head.childNodes).filter(function (e) { return e.nodeType === 1 && e.getAttribute('data-inertia') !== null; }).forEach(function (target) {
        var i = src.findIndex(function (e) { return e.getAttribute('data-inertia') === target.getAttribute('data-inertia'); });
        if (i === -1) { target.remove(); return; }
        var source = src.splice(i, 1)[0];
        if (!target.isEqualNode(source)) target.replaceWith(source);
    });
    src.forEach(function (e) { document.head.appendChild(e); });
}`;

// Inertia's order: pushState, the component swap in a microtask, the head
// rewrite debounced by 1ms, then inertia:navigate.
const inertia = (url, title, delay = 1) => `history.pushState({}, '', '${url}'); Promise.resolve().then(function () { setTimeout(function () { inertiaPass('${title}'); }, ${delay}); document.dispatchEvent(new Event('inertia:navigate')); });`;

const scenarios = {
    'Inertia: one head pass': {
        run: inertia('/new', 'New - App'),
        expect: [['/new', 'New - App', '/old']],
    },
    'meta tags dropped before the title arrives': {
        run: `history.pushState({}, '', '/new'); Promise.resolve().then(function () { setTimeout(function () { document.querySelector('meta[data-inertia="old-only"]').remove(); }, 1); setTimeout(function () { inertiaPass('New - App'); }, 6); document.dispatchEvent(new Event('inertia:navigate')); });`,
        expect: [['/new', 'New - App', '/old']],
    },
    'an unrelated head insert before the title arrives': {
        run: `history.pushState({}, '', '/new'); Promise.resolve().then(function () { setTimeout(function () { var l = document.createElement('link'); l.rel = 'modulepreload'; l.href = '/x.js'; document.head.appendChild(l); }, 2); setTimeout(function () { inertiaPass('New - App'); }, 4); document.dispatchEvent(new Event('inertia:navigate')); });`,
        expect: [['/new', 'New - App', '/old']],
    },
    'title set before the navigation is announced': {
        run: `inertiaPass('New - App'); history.pushState({}, '', '/new');`,
        expect: [['/new', 'New - App', '/old']],
    },
    'title set synchronously after pushState': {
        run: `history.pushState({}, '', '/new'); inertiaPass('New - App');`,
        expect: [['/new', 'New - App', '/old']],
    },
    'title arriving late, within the wait': {
        run: inertia('/new', 'New - App', 200),
        expect: [['/new', 'New - App', '/old']],
    },
    'same title, then a second navigation within the wait': {
        run: inertia('/b', 'Old - App') + ` setTimeout(function () { ${inertia('/c', 'C - App')} }, 60);`,
        expect: [['/b', 'Old - App', '/old'], ['/c', 'C - App', '/b']],
    },
    'same title, then a second navigation after the wait': {
        run: inertia('/b', 'Old - App') + ` setTimeout(function () { ${inertia('/c', 'C - App')} }, 300);`,
        expect: [['/b', 'Old - App', '/old'], ['/c', 'C - App', '/b']],
    },
    'new title, then a second navigation moments later': {
        run: inertia('/b', 'B - App') + ` setTimeout(function () { ${inertia('/c', 'C - App')} }, 30);`,
        expect: [['/b', 'B - App', '/old'], ['/c', 'C - App', '/b']],
    },
    'address corrected in the same tick': {
        run: `history.pushState({}, '', '/b'); history.pushState({}, '', '/c'); Promise.resolve().then(function () { setTimeout(function () { inertiaPass('C - App'); }, 1); document.dispatchEvent(new Event('inertia:navigate')); });`,
        expect: [['/c', 'C - App', '/old']],
    },
    'fragment only': {
        run: `history.pushState({}, '', '/old#x');`,
        expect: [],
    },
    'hand-announced views on an unchanged address': {
        run: `window.cmpTrackPageView(); setTimeout(function () { window.cmpTrackPageView(); }, 50);`,
        expect: [['/old', 'Old - App', '/old'], ['/old', 'Old - App', '/old']],
    },
    'back button': {
        run: inertia('/b', 'B - App') + ` setTimeout(function () { window.addEventListener('popstate', function () { setTimeout(function () { inertiaPass('Old - App'); }, 1); }); history.back(); }, 350);`,
        expect: [['/b', 'B - App', '/old'], ['/old', 'Old - App', '/b']],
    },
};

(async () => {
    const browser = await chromium.launch();
    let failures = 0;

    for (const signal of ['navigation-api', 'history-patch']) {
        for (const [name, scenario] of Object.entries(scenarios)) {
            const page = await browser.newPage();
            await page.route('**/*', (route) => route.fulfill({ contentType: 'text/html', body: HTML }));
            await page.goto('http://app.test/old');
            await page.evaluate((signal) => {
                if (signal === 'history-patch') {
                    Object.defineProperty(window, 'navigation', { value: undefined, configurable: true });
                }
                window.__views = [];
                window.addEventListener('cmp:pageview', (e) => window.__views.push([
                    e.detail.url.replace('http://app.test', ''),
                    e.detail.title,
                    e.detail.referrer.replace('http://app.test', ''),
                ]));
            }, signal);
            await page.addScriptTag({ content: bridge });
            await page.evaluate('(function () {' + INERTIA_PASS + ';' + scenario.run + '})()');
            await page.waitForTimeout(900);
            const views = await page.evaluate(() => window.__views);
            await page.close();

            const ok = JSON.stringify(views) === JSON.stringify(scenario.expect);
            if (!ok) {
                failures++;
            }
            console.log(`${ok ? 'ok  ' : 'FAIL'} [${signal}] ${name}` + (ok ? '' : `\n     got      ${JSON.stringify(views)}\n     expected ${JSON.stringify(scenario.expect)}`));
        }
    }

    await browser.close();
    console.log(failures ? `${failures} scenario(s) failed` : 'all scenarios pass');
    process.exit(failures ? 1 : 0);
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
