// Builds everything the desktop app ships with, into desktop/build/:
//   build/php  portable PHP for Windows (NTS x64) + php.ini
//   build/app  the Laravel app: production vendor/, compiled assets, no dev files
//
// Usage: node scripts/prepare.mjs   (env SHOPAPP_PHP_SERIES=8.4 to pick the PHP branch)

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const desktopDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const repoRoot = path.resolve(desktopDir, '..');
const buildDir = path.join(desktopDir, 'build');
const phpDir = path.join(buildDir, 'php');
const appDir = path.join(buildDir, 'app');
const cacheDir = path.join(desktopDir, '.cache');
const PHP_SERIES = process.env.SHOPAPP_PHP_SERIES || '8.4';
const isWin = process.platform === 'win32';

const PHP_EXTENSIONS = ['curl', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo_sqlite', 'sqlite3', 'zip'];

// Files from the repo that the packaged app does not need.
const EXCLUDE = [
    /^desktop\//, /^tests\//, /^\.github\//, /^docker\//, /^node_modules\//,
    /^Dockerfile$/, /^compose\.yaml$/, /^\.dockerignore$/, /^phpunit\.xml$/,
    /^\.env/, /^database\/.*\.sqlite/, /^storage\//, /^bootstrap\/cache\/.*\.php$/,
    /^\.(editorconfig|gitattributes|gitignore|styleci\.yml|npmrc)$/,
];

function run(cmd, args, opts = {}) {
    console.log(`> ${path.basename(cmd)} ${args.join(' ')}`);
    execFileSync(cmd, args, { stdio: 'inherit', ...opts });
}

async function download(url, dest) {
    if (fs.existsSync(dest)) return dest;
    console.log(`Downloading ${url}`);
    const res = await fetch(url, { headers: { 'User-Agent': 'shopapp-desktop-build' } });
    if (!res.ok) throw new Error(`Download failed (${res.status}): ${url}`);
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.writeFileSync(dest + '.part', Buffer.from(await res.arrayBuffer()));
    fs.renameSync(dest + '.part', dest);
    return dest;
}

const sha256 = (file) => createHash('sha256').update(fs.readFileSync(file)).digest('hex');

// Windows' own bsdtar unpacks .zip; GNU tar from Git Bash does not.
const tarBin = () => {
    const sys = path.join(process.env.SystemRoot || 'C:\\Windows', 'System32', 'tar.exe');
    return fs.existsSync(sys) ? sys : 'tar';
};

/** Env that makes php.exe load our php.ini plus an absolute extension_dir. */
export function phpEnv(phpRoot, scanDir) {
    fs.mkdirSync(scanDir, { recursive: true });
    const extDir = path.join(phpRoot, 'ext').replaceAll('\\', '/');
    fs.writeFileSync(path.join(scanDir, 'extension_dir.ini'), `extension_dir="${extDir}"\n`);
    return { ...process.env, PHPRC: phpRoot, PHP_INI_SCAN_DIR: scanDir };
}

async function preparePhp() {
    const releases = await (await fetch('https://windows.php.net/downloads/releases/releases.json')).json();
    const series = releases[PHP_SERIES];
    if (!series) throw new Error(`PHP ${PHP_SERIES} not found on windows.php.net`);
    const key = Object.keys(series).find((k) => /^nts-vs\d+-x64$/.test(k));
    const { path: zipName, sha256: expected } = series[key].zip;

    const zip = await download(`https://windows.php.net/downloads/releases/${zipName}`, path.join(cacheDir, zipName));
    if (expected && sha256(zip) !== expected.toLowerCase()) {
        fs.rmSync(zip);
        throw new Error(`Checksum mismatch for ${zipName}; deleted, run again.`);
    }

    fs.rmSync(phpDir, { recursive: true, force: true });
    fs.mkdirSync(phpDir, { recursive: true });
    run(tarBin(), ['-xf', zip, '-C', phpDir]);

    // Trim what a desktop runtime never uses.
    for (const f of ['dev', 'extras', 'phpdbg.exe', 'php-cgi.exe', 'php.ini-development', 'php.ini-production']) {
        fs.rmSync(path.join(phpDir, f), { recursive: true, force: true });
    }

    // App-local Visual C++ runtime, so users without the redistributable can run PHP.
    const sys32 = path.join(process.env.SystemRoot || 'C:\\Windows', 'System32');
    for (const dll of ['vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll']) {
        const src = path.join(sys32, dll);
        if (fs.existsSync(src)) fs.copyFileSync(src, path.join(phpDir, dll));
    }

    fs.writeFileSync(path.join(phpDir, 'php.ini'), [
        '[PHP]',
        '; extension_dir is set at runtime (absolute path) via PHP_INI_SCAN_DIR.',
        'memory_limit = 512M',
        'max_execution_time = 120',
        'variables_order = "EGPCS"',
        'display_errors = Off',
        'log_errors = On',
        'upload_max_filesize = 20M',
        'post_max_size = 25M',
        'expose_php = Off',
        ...PHP_EXTENSIONS.map((e) => `extension=${e}`),
        'zend_extension=opcache',
        'opcache.enable = 1',
        'opcache.enable_cli = 1',
        '',
    ].join('\r\n'));

    console.log(`PHP ${series.version} ready in build/php`);
}

async function prepareApp() {
    const php = path.join(phpDir, isWin ? 'php.exe' : 'php');
    const env = phpEnv(phpDir, path.join(cacheDir, 'php-scan'));

    // 1. Compile frontend assets in the repo.
    run(isWin ? 'npm.cmd' : 'npm', ['run', 'build'], { cwd: repoRoot, shell: isWin });

    // 2. Copy tracked + untracked-but-not-ignored files (so .env, vendor, node_modules never ship).
    fs.rmSync(appDir, { recursive: true, force: true });
    const files = execFileSync('git', ['ls-files', '-co', '--exclude-standard', '-z'], { cwd: repoRoot })
        .toString().split('\0').filter(Boolean)
        .filter((f) => !EXCLUDE.some((re) => re.test(f)));
    for (const f of files) {
        const src = path.join(repoRoot, f);
        if (!fs.existsSync(src) || !fs.statSync(src).isFile()) continue;
        fs.mkdirSync(path.dirname(path.join(appDir, f)), { recursive: true });
        fs.copyFileSync(src, path.join(appDir, f));
    }
    fs.cpSync(path.join(repoRoot, 'public', 'build'), path.join(appDir, 'public', 'build'), { recursive: true });
    fs.copyFileSync(path.join(desktopDir, 'router.php'), path.join(appDir, 'desktop-server.php'));

    // Laravel needs these to exist (at runtime storage is redirected to %APPDATA%).
    for (const d of ['bootstrap/cache', 'storage/app/public', 'storage/framework/cache/data',
        'storage/framework/sessions', 'storage/framework/views', 'storage/logs']) {
        fs.mkdirSync(path.join(appDir, d), { recursive: true });
    }

    // 3. Production PHP dependencies, using the bundled PHP (so the build doesn't depend on the dev machine's PHP).
    const composer = await download('https://getcomposer.org/download/latest-stable/composer.phar', path.join(cacheDir, 'composer.phar'));
    run(php, [composer, 'install', '--no-dev', '--no-interaction', '--no-progress', '--prefer-dist',
        '--optimize-autoloader', '--classmap-authoritative'], { cwd: appDir, env });

    // Composer's package:discover writes these into the app; they're regenerated per user at runtime.
    for (const f of fs.readdirSync(path.join(appDir, 'bootstrap', 'cache'))) {
        if (f.endsWith('.php')) fs.rmSync(path.join(appDir, 'bootstrap', 'cache', f));
    }

    console.log(`Laravel app staged in build/app (${files.length} files + vendor)`);
}

if (!isWin) {
    console.error('The desktop build targets Windows; run it on Windows (or the GitHub Actions workflow).');
    process.exit(1);
}

await preparePhp();
await prepareApp();
