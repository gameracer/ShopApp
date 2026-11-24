#!/usr/bin/env node
// Takes the screenshots used by the in-app user manual (docs/manual/*.md → /help).
//
//   npm run manual:screenshots            # every shot, English and Kiswahili
//   npm run manual:screenshots -- till    # only shots whose name contains "till"
//
// It never touches your real data: it builds a throwaway SQLite database with the demo seeder,
// serves the app with PHP's built-in server, and drives headless Chrome or Edge over the
// DevTools protocol (Node 22's built-in WebSocket, so no extra packages).
// Output: public/manual/<locale>/<name>.webp
//
// Environment overrides: PHP_BIN (PHP 8.3+ with pdo_sqlite, mbstring), CHROME (browser path).
// Run `npm run build` first so the screenshots show the current styles.

import { execFileSync, spawn } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outRoot = path.join(root, 'public', 'manual');
const only = process.argv[2] ?? '';
const LOCALES = ['en', 'sw'];
const VIEW = { width: 1366, height: 800 };
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------------------------------------------------------------- PHP

/** PHP_BIN, else the desktop build's portable PHP (with its extensions), else `php` on PATH. */
function phpCommand() {
    if (process.env.PHP_BIN) return [process.env.PHP_BIN];
    const portable = path.join(root, 'desktop', 'build', 'php', process.platform === 'win32' ? 'php.exe' : 'php');
    if (fs.existsSync(portable)) {
        const ext = path.join(path.dirname(portable), 'ext');
        return [portable, '-n', '-d', `extension_dir=${ext}`, '-d', 'memory_limit=512M',
            ...['mbstring', 'pdo_sqlite', 'sqlite3', 'fileinfo', 'openssl', 'intl', 'zip'].flatMap((e) => ['-d', `extension=${e}`])];
    }
    return ['php'];
}

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'shopapp-manual-'));
const env = {
    ...process.env,
    APP_ENV: 'local', // the seeder only adds the demo shop outside production
    APP_DEBUG: 'false',
    APP_KEY: `base64:${crypto.randomBytes(32).toString('base64')}`,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.join(tmp, 'manual.sqlite'),
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'sync',
    VIEW_COMPILED_PATH: path.join(tmp, 'views'),
};
fs.writeFileSync(env.DB_DATABASE, '');
fs.mkdirSync(env.VIEW_COMPILED_PATH);
const [phpBin, ...phpArgs] = phpCommand();

function artisan(...args) {
    execFileSync(phpBin, [...phpArgs, 'artisan', ...args], { cwd: root, env, stdio: ['ignore', 'ignore', 'inherit'] });
}

function freePort() {
    return new Promise((resolve) => {
        const srv = net.createServer().listen(0, '127.0.0.1', () => {
            const { port } = srv.address();
            srv.close(() => resolve(port));
        });
    });
}

// ---------------------------------------------------------------- Chrome DevTools

function findBrowser() {
    const candidates = [
        process.env.CHROME,
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser',
    ].filter(Boolean);
    const found = candidates.find((p) => fs.existsSync(p));
    if (!found) throw new Error('Chrome or Edge not found. Set CHROME to the browser path.');
    return found;
}

class Page {
    static async open() {
        const browser = spawn(findBrowser(), [
            '--headless=new', '--remote-debugging-port=0', `--user-data-dir=${path.join(tmp, 'chrome')}`,
            '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', '--disable-extensions',
            '--force-device-scale-factor=1', `--window-size=${VIEW.width},${VIEW.height}`, 'about:blank',
        ], { stdio: ['ignore', 'ignore', 'pipe'] });
        const wsBrowser = await new Promise((resolve, reject) => {
            let buf = '';
            browser.stderr.on('data', (d) => {
                buf += d;
                const m = buf.match(/DevTools listening on (ws:\/\/\S+)/);
                if (m) resolve(m[1]);
            });
            browser.on('exit', () => reject(new Error(`Browser exited:\n${buf}`)));
        });
        const port = new URL(wsBrowser).port;
        const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
        const page = new Page(targets.find((t) => t.type === 'page').webSocketDebuggerUrl, browser);
        await page.ready;
        await page.send('Page.enable');
        await page.send('Runtime.enable');
        await page.send('Network.enable');
        return page;
    }

    constructor(url, browser) {
        this.browser = browser;
        this.id = 0;
        this.pending = new Map();
        this.listeners = new Set();
        this.ws = new WebSocket(url);
        this.ready = new Promise((r) => this.ws.addEventListener('open', r, { once: true }));
        this.ws.addEventListener('message', ({ data }) => {
            const msg = JSON.parse(data);
            if (msg.id && this.pending.has(msg.id)) {
                const { resolve, reject } = this.pending.get(msg.id);
                this.pending.delete(msg.id);
                msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
            } else if (msg.method) {
                for (const l of this.listeners) l(msg);
            }
        });
    }

    send(method, params = {}) {
        const id = ++this.id;
        this.ws.send(JSON.stringify({ id, method, params }));
        return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject }));
    }

    once(method, timeout = 15000) {
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => { this.listeners.delete(l); reject(new Error(`Timed out waiting for ${method}`)); }, timeout);
            const l = (msg) => {
                if (msg.method !== method) return;
                clearTimeout(timer);
                this.listeners.delete(l);
                resolve(msg.params);
            };
            this.listeners.add(l);
        });
    }

    async goto(url) {
        const loaded = this.once('Page.loadEventFired');
        await this.send('Page.navigate', { url });
        await loaded;
        await this.settle();
    }

    /** Wait for fonts, Alpine and any fetches the page starts on load. */
    async settle(ms = 700) {
        await this.eval('document.fonts ? document.fonts.ready.then(() => true) : true');
        await wait(ms);
    }

    async eval(expression) {
        const { result, exceptionDetails } = await this.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (exceptionDetails) throw new Error(`${expression}\n→ ${exceptionDetails.exception?.description ?? exceptionDetails.text}`);
        return result.value;
    }

    /** Run something that navigates (a form submit, a link) and wait for the new page. */
    async navigating(expression) {
        const loaded = this.once('Page.loadEventFired');
        await this.eval(expression);
        await loaded;
        await this.settle();
    }

    async shot(file, { width = VIEW.width, height = VIEW.height, full = false, maxHeight = 1800 } = {}) {
        await this.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
        await wait(250);
        let h = height;
        if (full) {
            h = Math.min(maxHeight, await this.eval('Math.ceil(document.documentElement.scrollHeight)'));
            await this.send('Emulation.setDeviceMetricsOverride', { width, height: h, deviceScaleFactor: 1, mobile: false });
            await wait(250);
        }
        const { data } = await this.send('Page.captureScreenshot', {
            format: 'webp', quality: 82, clip: { x: 0, y: 0, width, height: h, scale: 1 }, captureBeyondViewport: full,
        });
        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, Buffer.from(data, 'base64'));
        await this.send('Emulation.setDeviceMetricsOverride', { width: VIEW.width, height: VIEW.height, deviceScaleFactor: 1, mobile: false });
    }

    close() {
        this.ws.close();
        this.browser.kill();
    }
}

// ---------------------------------------------------------------- the shots

const USERS = { owner: 'owner@shop.test', seller: 'seller@shop.test' };

/** Click the first element matching `selector` whose text includes `text` (or the first match). */
const click = (selector, text = '') => `(() => {
    const el = [...document.querySelectorAll(${JSON.stringify(selector)})].find((e) => e.textContent.includes(${JSON.stringify(text)}));
    if (!el) throw new Error('No ' + ${JSON.stringify(selector)} + ' with ' + ${JSON.stringify(text)});
    el.click();
})()`;

/** The Alpine component on the element matching `selector`. */
const alpine = (selector) => `Alpine.$data(document.querySelector(${JSON.stringify(selector)}))`;

/** Shots shown in the manual. `as`: who is logged in (null: logged out). `run`: sets the page up. */
const SHOTS = [
    { name: 'setup', as: null, path: '/setup', setup: true },
    { name: 'login', as: null, path: '/login' },
    { name: 'dashboard', as: 'owner', path: '/' },
    { name: 'seller-home', as: 'seller', path: '/' },
    {
        name: 'till', as: 'seller', path: '/sales/new',
        async run(page) {
            await page.eval(`(() => { const t = document.querySelectorAll('.pos-tile'); [0, 2, 2, 5].forEach((i) => t[i]?.click()); })()`);
            await wait(300);
            await page.eval(`(() => { const d = ${alpine('[x-data^="posApp"]')}; d.cashGiven = String(d.cashSuggestions[1] ?? d.total); })()`);
            await wait(300);
        },
    },
    {
        name: 'till-credit', as: 'seller', path: '/sales/new',
        async run(page) {
            await page.eval(`(() => { const t = document.querySelectorAll('.pos-tile'); [1, 3].forEach((i) => t[i]?.click()); })()`);
            await page.eval(`(() => { const d = ${alpine('[x-data^="posApp"]')}; d.paymentMethod = 'credit'; d.amountPaid = '1000'; })()`);
            await wait(400);
        },
    },
    {
        name: 'till-done', as: 'seller', path: '/sales/new',
        async run(page) {
            await page.eval(`(() => { const t = document.querySelectorAll('.pos-tile'); [0, 4].forEach((i) => t[i]?.click()); })()`);
            await page.eval(`(() => { const d = ${alpine('[x-data^="posApp"]')}; d.cashGiven = String(d.cashSuggestions[1] ?? d.total); })()`);
            await wait(200);
            await page.eval(`${alpine('[x-data^="posApp"]')}.checkout()`);
            await wait(1500);
        },
    },
    {
        name: 'receipt', as: 'owner', path: async (page) => page.eval(`fetch('/sales').then(r => r.text()).then(h => (h.match(/\\/sales\\/(\\d+)/) || [])[1]).then(id => '/sales/' + id + '/receipt')`),
        shot: { width: 420, height: 200, full: true },
    },
    {
        name: 'display', as: null, path: '/display',
        async run(page) {
            await page.eval(`(() => {
                const lines = [
                    { name: 'Coca-Cola 500ml', quantity: 2, unit: 'bottle', price: 1000, subtotal: 2000 },
                    { name: 'Unga wa Sembe 2kg', quantity: 1, unit: 'packet', price: 4000, subtotal: 4000 },
                    { name: 'Fresh Milk 500ml', quantity: 3, unit: 'packet', price: 1500, subtotal: 4500 },
                ];
                new BroadcastChannel('shopapp-customer-display').postMessage({
                    type: 'cart', lines, last: 2, total: 10500, units: 6, method: 'cash',
                    customer: null, cashier: 'Muuzaji', cashGiven: 20000, change: 9500, amountPaid: null, balance: 0, splitChange: 0,
                });
            })()`);
            await wait(500);
        },
        shot: { width: 1600, height: 900 }, // a typical customer-facing monitor
    },
    { name: 'products', as: 'owner', path: '/products' },
    { name: 'product-form', as: 'owner', path: '/products/create', shot: { full: true, maxHeight: 1100 } },
    { name: 'import', as: 'owner', path: '/products/import', shot: { full: true, maxHeight: 1100 } },
    {
        name: 'stock', as: 'owner', path: '/stock/add',
        async run(page) {
            await page.eval(`(() => { const b = document.querySelectorAll('[x-data^="stockApp"] [\\\\@click="add(p)"]'); [0, 3].forEach((i) => b[i]?.click()); })()`);
            await wait(400);
            await page.eval(`(() => { const d = ${alpine('[x-data^="stockApp"]')}; d.lines.forEach((l, i) => { l.quantity = [24, 12][i] ?? 6; }); if (d.lines[0]) d.supplier = 'Bakhresa Wholesale'; })()`);
            await wait(300);
        },
    },
    { name: 'credit', as: 'owner', path: '/credit' },
    {
        name: 'sale-payment', as: 'owner',
        path: async (page) => page.eval(`fetch('/credit').then(r => r.text()).then(h => '/sales/' + (h.match(/\\/sales\\/(\\d+)/) || [])[1])`),
        shot: { full: true, maxHeight: 1200 },
    },
    { name: 'alerts', as: 'owner', path: '/alerts', shot: { full: true, maxHeight: 1300 } },
    { name: 'expenses', as: 'owner', path: '/expenses' },
    { name: 'reports', as: 'owner', path: '/reports?period=monthly', shot: { full: true, maxHeight: 1700 } },
    { name: 'tags', as: 'owner', path: '/tags' },
    { name: 'users', as: 'owner', path: '/users' },
    { name: 'settings', as: 'owner', path: '/settings', shot: { full: true, maxHeight: 1500 } },
];

async function login(page, base, who) {
    await page.send('Network.clearBrowserCookies');
    if (!who) return;
    await page.goto(`${base}/login`);
    await page.navigating(`(() => {
        document.querySelector('input[name=email]').value = ${JSON.stringify(USERS[who])};
        document.querySelector('input[name=password]').value = 'password';
        document.querySelector('input[name=password]').form.submit();
    })()`);
}

async function main() {
    const wanted = SHOTS.filter((s) => s.name.includes(only));
    if (!wanted.length) throw new Error(`No shot matches "${only}". Shots: ${SHOTS.map((s) => s.name).join(', ')}`);

    console.log('Preparing a throwaway database…');
    artisan('migrate:fresh', '--force'); // empty: the first-run setup page is still open

    const port = await freePort();
    const base = `http://127.0.0.1:${port}`;
    const server = spawn(phpBin, [...phpArgs, '-S', `127.0.0.1:${port}`, path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
        cwd: path.join(root, 'public'), env: { ...env, APP_URL: base }, stdio: 'ignore',
    });
    for (let i = 0; i < 50; i++) {
        try { await fetch(`${base}/login`); break; } catch { await wait(200); }
    }

    const page = await Page.open();
    let seeded = false;
    try {
        for (const shot of [...wanted].sort((a, b) => Number(Boolean(b.setup)) - Number(Boolean(a.setup)))) {
            if (!shot.setup && !seeded) {
                console.log('Adding the demo shop…');
                artisan('db:seed', '--force');
                seeded = true;
            }
            for (const locale of LOCALES) {
                await login(page, base, shot.as);
                await page.goto(`${base}/locale/${locale}`);
                const target = typeof shot.path === 'function' ? await shot.path(page) : shot.path;
                await page.goto(base + target);
                if (shot.run) await shot.run(page);
                const file = path.join(outRoot, locale, `${shot.name}.webp`);
                await page.shot(file, shot.shot);
                console.log(`  ${locale}/${shot.name}.webp`);
            }
        }
    } finally {
        page.close();
        server.kill();
    }
}

main()
    .catch((e) => { console.error(e); process.exitCode = 1; })
    .finally(() => { try { fs.rmSync(tmp, { recursive: true, force: true }); } catch { /* browser still letting go */ } });
