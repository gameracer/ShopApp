// ShopApp desktop shell.
//
// Runs the bundled Laravel app with the bundled PHP on 127.0.0.1 and shows it in
// a window. All shop data lives in %APPDATA%\ShopApp so installs and updates
// never touch it:
//   database.sqlite   the shop database
//   app.key           APP_KEY, generated on first run
//   storage/          Laravel storage (logs, sessions, compiled views)
//   cache/            Laravel bootstrap cache
//   backups/          automatic backups (before every update) + manual ones
//   devices.json      this computer's receipt printer choice
//   logs/desktop.log  this shell's log

const { app, BrowserWindow, Menu, dialog, ipcMain, shell } = require('electron');
const { autoUpdater } = require('electron-updater');
const { spawn, execFile } = require('node:child_process');
const crypto = require('node:crypto');
const fs = require('node:fs');
const http = require('node:http');
const net = require('node:net');
const path = require('node:path');

const RESOURCES = app.isPackaged ? process.resourcesPath : path.join(__dirname, 'build');
const PHP_DIR = path.join(RESOURCES, 'php');
const PHP_BIN = path.join(PHP_DIR, 'php.exe');
const APP_DIR = path.join(RESOURCES, 'app');

const DATA = app.getPath('userData');
const DB_PATH = path.join(DATA, 'database.sqlite');
const STORAGE = path.join(DATA, 'storage');
const CACHE = path.join(DATA, 'cache');
const BACKUPS = path.join(DATA, 'backups');
const LOGS = path.join(DATA, 'logs');
const DEVICES = path.join(DATA, 'devices.json');
const KEEP_BACKUPS = 30;
const PREFERRED_PORT = 8765;

let mainWindow = null;
let splash = null;
let phpServer = null;
let quitting = false;
let baseUrl = '';
let env = {};

// ---------------------------------------------------------------- helpers

function log(...parts) {
    const line = `[${new Date().toISOString()}] ${parts.join(' ')}\n`;
    try { fs.appendFileSync(path.join(LOGS, 'desktop.log'), line); } catch { /* ignore */ }
    if (!app.isPackaged) process.stdout.write(line);
}

function ensureDirs() {
    for (const d of [
        'storage/app/public', 'storage/app/private', 'storage/framework/cache/data',
        'storage/framework/sessions', 'storage/framework/views', 'storage/logs',
        'cache', 'backups', 'logs', 'php',
    ]) fs.mkdirSync(path.join(DATA, d), { recursive: true });
}

function appKey() {
    const file = path.join(DATA, 'app.key');
    if (fs.existsSync(file)) return fs.readFileSync(file, 'utf8').trim();
    const key = 'base64:' + crypto.randomBytes(32).toString('base64');
    fs.writeFileSync(file, key);
    return key;
}

function buildEnv(port) {
    // Absolute extension_dir, loaded through PHP_INI_SCAN_DIR next to php.ini.
    const extDir = path.join(PHP_DIR, 'ext').replaceAll('\\', '/');
    fs.writeFileSync(path.join(DATA, 'php', 'extension_dir.ini'), `extension_dir="${extDir}"\n`);

    return {
        ...process.env,
        PHPRC: PHP_DIR,
        PHP_INI_SCAN_DIR: path.join(DATA, 'php'),
        APP_NAME: 'ShopApp',
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        APP_KEY: appKey(),
        APP_URL: `http://127.0.0.1:${port}`,
        APP_VERSION: app.getVersion(),
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: DB_PATH,
        SHOPAPP_STORAGE_PATH: STORAGE,
        APP_SERVICES_CACHE: path.join(CACHE, 'services.php'),
        APP_PACKAGES_CACHE: path.join(CACHE, 'packages.php'),
        APP_CONFIG_CACHE: path.join(CACHE, 'config.php'),
        APP_ROUTES_CACHE: path.join(CACHE, 'routes-v7.php'),
        APP_EVENTS_CACHE: path.join(CACHE, 'events.php'),
        LOG_CHANNEL: 'daily',
        SESSION_DRIVER: 'database',
        CACHE_STORE: 'database',
        QUEUE_CONNECTION: 'sync',
    };
}

function php(args, opts = {}) {
    return new Promise((resolve, reject) => {
        execFile(PHP_BIN, args, { cwd: APP_DIR, env, windowsHide: true, timeout: 120000, ...opts }, (err, stdout, stderr) => {
            log(`php ${args.join(' ')}`, stdout.trim(), stderr.trim());
            err ? reject(new Error(stderr.trim() || stdout.trim() || err.message)) : resolve(stdout);
        });
    });
}

const artisan = (...args) => php(['artisan', ...args, '--no-interaction']);

function freePort(preferred) {
    const tryPort = (port) => new Promise((resolve, reject) => {
        const srv = net.createServer();
        srv.once('error', reject);
        srv.listen(port, '127.0.0.1', () => srv.close(() => resolve(srv.address()?.port ?? port)));
    });
    return tryPort(preferred).catch(() => new Promise((resolve, reject) => {
        const srv = net.createServer();
        srv.once('error', reject);
        srv.listen(0, '127.0.0.1', () => { const { port } = srv.address(); srv.close(() => resolve(port)); });
    }));
}

function waitForServer(url, timeoutMs = 30000) {
    const started = Date.now();
    return new Promise((resolve, reject) => {
        const attempt = () => {
            const req = http.get(url, (res) => { res.resume(); res.statusCode < 500 ? resolve() : retry(); });
            req.on('error', retry);
            req.setTimeout(2000, () => req.destroy());
        };
        const retry = () => (Date.now() - started > timeoutMs
            ? reject(new Error('The local server did not start in time.'))
            : setTimeout(attempt, 250));
        attempt();
    });
}

// ---------------------------------------------------------------- backups

async function backupDatabase(label) {
    if (!fs.existsSync(DB_PATH)) return null;
    const stamp = new Date().toISOString().replace(/[-:]/g, '').replace('T', '-').slice(0, 15);
    const dest = path.join(BACKUPS, `shopapp-${stamp}-${label}.sqlite`);
    // VACUUM INTO gives a consistent copy even while the app is running.
    await php(['-r', '$db = new PDO("sqlite:" . $argv[1]); $db->exec("VACUUM INTO " . $db->quote($argv[2]));', DB_PATH, dest]);

    const old = fs.readdirSync(BACKUPS).filter((f) => f.endsWith('.sqlite')).sort().reverse().slice(KEEP_BACKUPS);
    for (const f of old) fs.rmSync(path.join(BACKUPS, f), { force: true });
    log('Backup written', dest);
    return dest;
}

async function backupNow() {
    try {
        const dest = await backupDatabase('manual');
        const { response } = await dialog.showMessageBox(mainWindow, {
            type: 'info', message: 'Backup saved', detail: dest, buttons: ['OK', 'Show in folder'],
        });
        if (response === 1) shell.showItemInFolder(dest);
    } catch (e) {
        dialog.showErrorBox('Backup failed', e.message);
    }
}

async function restoreBackup() {
    const { canceled, filePaths } = await dialog.showOpenDialog(mainWindow, {
        title: 'Restore a backup', defaultPath: BACKUPS,
        filters: [{ name: 'ShopApp backup', extensions: ['sqlite'] }], properties: ['openFile'],
    });
    if (canceled || !filePaths[0]) return;
    const { response } = await dialog.showMessageBox(mainWindow, {
        type: 'warning', buttons: ['Cancel', 'Restore and restart'], defaultId: 0, cancelId: 0,
        message: 'Replace the current data with this backup?',
        detail: 'A backup of the current data is made first. ShopApp restarts afterwards.',
    });
    if (response !== 1) return;
    try {
        await backupDatabase('before-restore');
        await stopServer();
        fs.copyFileSync(filePaths[0], DB_PATH);
        app.relaunch();
        app.quit();
    } catch (e) {
        dialog.showErrorBox('Restore failed', e.message);
    }
}

// ---------------------------------------------------------------- devices
//
// Receipt printers are used through their Windows driver, so any printer Windows
// knows about works: plug it in (install the maker's driver if Windows doesn't
// find one) and it shows up. With no printer chosen in Settings, a receipt
// printer is detected by name, falling back to the Windows default printer.
// Barcode scanners need nothing here: they act as a keyboard (see resources/js/devices.js).

const RECEIPT_PRINTER = /\b(pos|receipt|thermal)\b|pos-?\d|xp-?\d|xprinter|\btm-|tsp\d|rongta|gprinter|bixolon|sewoo|\b(58|80) ?mm\b/i;
const VIRTUAL_PRINTER = /pdf|xps|onenote|fax|send to/i;

function readDevices() {
    try { return JSON.parse(fs.readFileSync(DEVICES, 'utf8')); } catch { return {}; }
}

function writeDevices(values) {
    fs.writeFileSync(DEVICES, JSON.stringify({ ...readDevices(), ...values }, null, 2));
}

async function listPrinters(webContents) {
    return (await webContents.getPrintersAsync()).map((p) => ({
        name: p.name,
        displayName: p.displayName || p.name,
        isDefault: Boolean(p.isDefault || p.options?.['printer-is-default'] === 'true' || p.options?.isDefault === 'true'),
    }));
}

/** The printer receipts go to: the saved choice if still installed, else a detected receipt printer, else the default. */
function resolvePrinter(printers) {
    const { printer } = readDevices();
    return printers.find((p) => p.name === printer)
        ?? printers.find((p) => RECEIPT_PRINTER.test(`${p.name} ${p.displayName}`) && !VIRTUAL_PRINTER.test(p.name))
        ?? printers.find((p) => p.isDefault)
        ?? null;
}

async function printReceipt(url) {
    if (!isInternal(url)) throw new Error('Only ShopApp pages can be printed.');

    const win = new BrowserWindow({
        show: false, width: 420, height: 900,
        webPreferences: { contextIsolation: true, sandbox: true, nodeIntegration: false },
    });
    try {
        await win.loadURL(url);
        const page = await win.webContents.executeJavaScript(
            '({ paper: document.body?.dataset.paper, height: document.documentElement.scrollHeight })');
        if (!page.paper) throw new Error('The receipt could not be opened.');

        const printer = resolvePrinter(await listPrinters(win.webContents));
        // Thermal paper is a roll: make the page exactly as long as the receipt (px -> microns, +5 mm).
        const pageSize = {
            width: (page.paper === '58' ? 58 : 80) * 1000,
            height: Math.max(20000, Math.ceil((page.height * 25400) / 96) + 5000),
        };
        await new Promise((resolve, reject) => {
            win.webContents.print({
                silent: true, printBackground: true, deviceName: printer?.name,
                margins: { marginType: 'none' }, pageSize,
            }, (ok, reason) => (ok ? resolve() : reject(new Error(reason || 'Printing failed.'))));
        });
        log('Receipt printed on', printer?.name ?? 'default printer', url);
        return { ok: true, printer: printer?.displayName ?? null };
    } catch (e) {
        log('Receipt print failed:', e.message);
        return { ok: false, error: e.message };
    } finally {
        win.destroy();
    }
}

function setupDeviceIpc() {
    const fromApp = (event) => isInternal(event.senderFrame?.url ?? '');

    ipcMain.handle('devices:printers', async (event) => {
        if (!fromApp(event)) throw new Error('Not allowed.');
        const printers = await listPrinters(event.sender);
        const selected = readDevices().printer ?? null;
        return {
            printers,
            selected: printers.some((p) => p.name === selected) ? selected : null,
            detected: resolvePrinter(printers)?.displayName ?? null,
        };
    });
    ipcMain.handle('devices:set-printer', (event, name) => {
        if (!fromApp(event)) throw new Error('Not allowed.');
        writeDevices({ printer: typeof name === 'string' && name ? name : null });
        return true;
    });
    ipcMain.handle('devices:print-receipt', (event, url) => {
        if (!fromApp(event)) throw new Error('Not allowed.');
        return printReceipt(String(url));
    });
}

// ---------------------------------------------------------------- server

function startServer(port) {
    phpServer = spawn(PHP_BIN, ['-S', `127.0.0.1:${port}`, '-t', 'public', 'desktop-server.php'], {
        cwd: APP_DIR, env, windowsHide: true,
    });
    const out = fs.createWriteStream(path.join(LOGS, 'server.log'), { flags: 'a' });
    phpServer.stdout.pipe(out);
    phpServer.stderr.pipe(out);
    phpServer.on('exit', (code) => {
        log('PHP server exited with code', code);
        phpServer = null;
        if (!quitting) {
            dialog.showErrorBox('ShopApp stopped', `The local server stopped unexpectedly (code ${code}).\nLogs: ${LOGS}`);
            app.quit();
        }
    });
}

function stopServer() {
    quitting = true;
    return new Promise((resolve) => {
        if (!phpServer) return resolve();
        phpServer.once('exit', () => resolve());
        phpServer.kill();
    });
}

// ---------------------------------------------------------------- updates

let manualUpdateCheck = false;

function setupAutoUpdates() {
    autoUpdater.autoDownload = true;
    autoUpdater.autoInstallOnAppQuit = true;
    autoUpdater.logger = { info: (m) => log('[updater]', m), warn: (m) => log('[updater]', m), error: (m) => log('[updater]', m), debug: () => {} };

    autoUpdater.on('update-not-available', () => {
        if (manualUpdateCheck) dialog.showMessageBox(mainWindow, { message: `You have the latest version (${app.getVersion()}).` });
        manualUpdateCheck = false;
    });
    autoUpdater.on('error', (e) => {
        if (manualUpdateCheck) dialog.showErrorBox('Update check failed', e?.message ?? String(e));
        manualUpdateCheck = false;
    });
    autoUpdater.on('update-downloaded', async (info) => {
        manualUpdateCheck = false;
        const { response } = await dialog.showMessageBox(mainWindow, {
            type: 'info', buttons: ['Restart now', 'Later'], defaultId: 0, cancelId: 1,
            message: `ShopApp ${info.version} is ready to install.`,
            detail: 'Your data is backed up automatically before the update is applied. Choosing "Later" installs it when you close ShopApp.',
        });
        if (response === 0) { await stopServer(); autoUpdater.quitAndInstall(); }
    });

    if (app.isPackaged) autoUpdater.checkForUpdates().catch((e) => log('[updater]', e.message));
}

function checkForUpdatesManually() {
    if (!app.isPackaged) {
        dialog.showMessageBox(mainWindow, { message: 'Updates are only checked in the installed app.' });
        return;
    }
    manualUpdateCheck = true;
    autoUpdater.checkForUpdates().catch(() => {});
}

// ---------------------------------------------------------------- windows

function buildMenu() {
    Menu.setApplicationMenu(Menu.buildFromTemplate([
        {
            label: 'File',
            submenu: [
                { label: 'Back up data now', click: backupNow },
                { label: 'Restore a backup…', click: restoreBackup },
                { type: 'separator' },
                { label: 'Open data folder', click: () => shell.openPath(DATA) },
                { label: 'Open logs folder', click: () => shell.openPath(LOGS) },
                { type: 'separator' },
                { role: 'quit' },
            ],
        },
        { role: 'editMenu' },
        {
            label: 'View',
            submenu: [
                { role: 'reload' }, { type: 'separator' },
                { role: 'resetZoom' }, { role: 'zoomIn' }, { role: 'zoomOut' }, { type: 'separator' },
                { role: 'togglefullscreen' }, { role: 'toggleDevTools' },
            ],
        },
        {
            label: 'Help',
            submenu: [
                { label: 'Check for updates', click: checkForUpdatesManually },
                { type: 'separator' },
                {
                    label: 'About ShopApp',
                    click: () => dialog.showMessageBox(mainWindow, {
                        message: `ShopApp ${app.getVersion()}`,
                        detail: `Electron ${process.versions.electron}\nData: ${DATA}`,
                    }),
                },
            ],
        },
    ]));
}

const isInternal = (url) => Boolean(baseUrl) && (url.startsWith(baseUrl + '/') || url === baseUrl);

function createMainWindow() {
    mainWindow = new BrowserWindow({
        width: 1280, height: 820, minWidth: 900, minHeight: 600, show: false, title: 'ShopApp',
        webPreferences: {
            contextIsolation: true, sandbox: true, nodeIntegration: false,
            preload: path.join(__dirname, 'preload.js'),
        },
    });

    // Same-origin popups (e.g. printable receipts) stay in the app; everything else opens in the browser.
    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        if (isInternal(url)) return { action: 'allow' };
        shell.openExternal(url);
        return { action: 'deny' };
    });
    mainWindow.webContents.on('will-navigate', (event, url) => {
        if (!isInternal(url)) { event.preventDefault(); shell.openExternal(url); }
    });

    mainWindow.once('ready-to-show', () => {
        mainWindow.maximize();
        mainWindow.show();
        splash?.destroy();
        splash = null;
    });
    mainWindow.on('closed', () => { mainWindow = null; });
    mainWindow.loadURL(baseUrl + '/');
}

// Sales, expiry alerts and reports all depend on the computer's clock, so the
// user confirms the date on every launch. Returns false if they say it's wrong.
async function confirmSystemDate() {
    const now = new Date();
    const fmt = (locale) => now.toLocaleString(locale, { dateStyle: 'full', timeStyle: 'short' });

    const lastRunFile = path.join(DATA, 'last-run');
    const lastRun = fs.existsSync(lastRunFile) ? new Date(fs.readFileSync(lastRunFile, 'utf8').trim()) : null;
    const clockWentBack = lastRun && !Number.isNaN(lastRun.getTime()) && now < lastRun;

    const warning = clockWentBack
        ? `\n\n⚠ This is earlier than the last time ShopApp was opened (${lastRun.toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })}).`
          + `\n⚠ Tarehe hii ni ya nyuma kuliko mara ya mwisho ShopApp ilipofunguliwa.`
        : '';

    const { response } = await dialog.showMessageBox({
        type: clockWentBack ? 'warning' : 'question',
        title: 'ShopApp – Confirm date / Thibitisha tarehe',
        message: 'Is the date and time on this computer correct?\nJe, tarehe na saa ya kompyuta hii ni sahihi?',
        detail: `${fmt('en-GB')}\n${fmt('sw')}${warning}\n\n`
            + 'Sales, expiry alerts and reports use this date.\nMauzo, tahadhari za kuisha muda na ripoti hutumia tarehe hii.',
        buttons: ['Yes, continue / Ndiyo, endelea', 'No, close and fix / Hapana, funga nirekebishe'],
        defaultId: clockWentBack ? 1 : 0,
        cancelId: 1,
        noLink: true,
    });

    if (response !== 0) {
        await shell.openExternal('ms-settings:dateandtime').catch(() => {});
        return false;
    }

    fs.writeFileSync(lastRunFile, now.toISOString());
    return true;
}

async function boot() {
    ensureDirs();
    if (!(await confirmSystemDate())) {
        log('User reported a wrong system date; quitting.');
        quitting = true;
        app.quit();
        return;
    }

    splash = new BrowserWindow({ width: 420, height: 260, frame: false, resizable: false, show: true, alwaysOnTop: true });
    splash.loadFile(path.join(__dirname, 'splash.html'));

    if (!fs.existsSync(PHP_BIN) || !fs.existsSync(path.join(APP_DIR, 'artisan'))) {
        throw new Error(`Bundled files are missing (${RESOURCES}). Run "npm run build:app" first.`);
    }

    const port = await freePort(PREFERRED_PORT);
    baseUrl = `http://127.0.0.1:${port}`;
    env = buildEnv(port);

    const fresh = !fs.existsSync(DB_PATH);
    if (fresh) fs.writeFileSync(DB_PATH, '');

    // After an update: back up first, then drop caches compiled by the old version.
    const versionFile = path.join(DATA, 'version');
    const lastVersion = fs.existsSync(versionFile) ? fs.readFileSync(versionFile, 'utf8').trim() : null;
    if (!fresh && lastVersion !== app.getVersion()) {
        await backupDatabase(`before-v${app.getVersion()}`);
        await artisan('optimize:clear').catch((e) => log('optimize:clear failed:', e.message));
    }

    await artisan('migrate', '--force');
    fs.writeFileSync(versionFile, app.getVersion());
    // No seeding: with no users, the app opens its first-run setup page (shop name, owner, seller).

    startServer(port);
    await waitForServer(baseUrl + '/up');
    log(`ShopApp ${app.getVersion()} running at ${baseUrl}`);

    buildMenu();
    setupDeviceIpc();
    createMainWindow();
    setupAutoUpdates();
}

// ---------------------------------------------------------------- lifecycle

if (!app.requestSingleInstanceLock()) {
    app.quit();
} else {
    app.on('second-instance', () => {
        if (mainWindow) { if (mainWindow.isMinimized()) mainWindow.restore(); mainWindow.focus(); }
    });

    app.whenReady().then(() => boot().catch((e) => {
        log('Startup failed:', e.stack || e.message);
        splash?.destroy();
        dialog.showErrorBox('ShopApp could not start', `${e.message}\n\nLogs: ${LOGS}`);
        quitting = true;
        app.quit();
    }));

    app.on('window-all-closed', () => app.quit());
    app.on('before-quit', () => { quitting = true; stopServer(); });
}
