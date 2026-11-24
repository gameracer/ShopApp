// Local release: bump the version, commit + tag it, and build the installer on this machine.
//
//   npm run release -- patch      1.0.0 -> 1.0.1   (bug fixes)
//   npm run release -- minor      1.0.0 -> 1.1.0   (new features)
//   npm run release -- major      1.0.0 -> 2.0.0   (breaking changes)
//   npm run release -- 1.4.0      exact version
//
// If GH_TOKEN is set, the installer is also uploaded to GitHub Releases so installed
// apps auto-update. Without it, the installer is only written to desktop/dist/.
// Nothing is pushed; the script prints the push command at the end.

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const desktopDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const repoRoot = path.resolve(desktopDir, '..');
const pkgFile = path.join(desktopDir, 'package.json');
const isWin = process.platform === 'win32';

const git = (...args) => execFileSync('git', args, { cwd: repoRoot, encoding: 'utf8' }).trim();
const run = (cmd, args) => execFileSync(cmd, args, { cwd: desktopDir, stdio: 'inherit', shell: isWin });
const fail = (msg) => { console.error(`\n✖ ${msg}\n`); process.exit(1); };

const bump = process.argv[2];
if (!bump) fail('Say how to bump the version: npm run release -- patch | minor | major | <x.y.z>');

if (git('status', '--porcelain')) fail('Commit or stash your changes first; a release must match a commit exactly.');

const pkg = JSON.parse(fs.readFileSync(pkgFile, 'utf8'));
const [maj, min, pat] = pkg.version.split('-')[0].split('.').map(Number);
const next = {
    patch: `${maj}.${min}.${pat + 1}`,
    minor: `${maj}.${min + 1}.0`,
    major: `${maj + 1}.0.0`,
}[bump] ?? bump;

if (!/^\d+\.\d+\.\d+$/.test(next)) fail(`"${bump}" is not patch, minor, major or a version like 1.2.3.`);
const tag = `v${next}`;
if (git('tag', '--list', tag)) fail(`Tag ${tag} already exists.`);

console.log(`\nReleasing ShopApp ${pkg.version} → ${next}\n`);

// Version bump, committed and tagged so every installer maps to exact source.
pkg.version = next;
fs.writeFileSync(pkgFile, JSON.stringify(pkg, null, 4) + '\n');
run(isWin ? 'npm.cmd' : 'npm', ['install', '--package-lock-only', '--no-audit', '--no-fund']);
git('add', 'desktop/package.json', 'desktop/package-lock.json');
git('commit', '-m', `Release ${tag}`);
git('tag', '-a', tag, '-m', `ShopApp ${tag}`);

try {
    const publish = process.env.GH_TOKEN ? 'always' : 'never';
    run(isWin ? 'npm.cmd' : 'npm', ['run', 'build:app']);
    run(isWin ? 'npx.cmd' : 'npx', ['electron-builder', '--win', '--publish', publish]);
} catch {
    // Undo the version commit + tag so the release can simply be re-run.
    git('tag', '-d', tag);
    git('reset', '--hard', 'HEAD~1');
    fail('Build failed; the version bump was rolled back.');
}

console.log(`\n✔ Built desktop/dist/ShopApp-Setup-${next}.exe`);
console.log(process.env.GH_TOKEN
    ? '✔ Uploaded to GitHub Releases; installed apps will update automatically.'
    : '• GH_TOKEN not set: not uploaded. Share the .exe yourself, or set GH_TOKEN to publish updates.');
console.log(`\nPush the release commit and tag when ready:\n  git push origin HEAD ${tag}\n`);
