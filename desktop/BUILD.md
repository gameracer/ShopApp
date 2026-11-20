# Building and releasing the ShopApp desktop app

This guide covers building the Windows installer and releasing new versions. Everything runs on your own Windows machine; no CI server is involved.

## 1. One-time setup

### Requirements

| Tool | Version | Check with |
|------|---------|------------|
| Windows | 10 or 11, x64 | — |
| Node.js | 22 or newer | `node -v` |
| npm | comes with Node | `npm -v` |
| git | any recent | `git --version` |

You do **not** need PHP or Composer installed. The build downloads its own portable PHP 8.4 from windows.php.net and its own `composer.phar`, so the result does not depend on what is installed on your machine.

### Install the dependencies

From the repository root:

```bash
npm install            # Vite + Tailwind, used to compile the CSS/JS
cd desktop
npm install            # Electron, electron-builder, electron-updater
```

Downloads are cached after the first run:

| What | Cached in |
|------|-----------|
| PHP zip, composer.phar | `desktop/.cache/` |
| Electron | `%LOCALAPPDATA%\electron\Cache` |
| NSIS, rcedit (installer tools) | `%LOCALAPPDATA%\electron-builder\Cache` |

### If Electron downloads hang

Electron and electron-builder download their binaries from GitHub release assets (`release-assets.githubusercontent.com`). On some networks these downloads stall at 0 bytes and the build fails with `Timeout awaiting 'request'`. Pick one way around it. You only need to do this once, because the files are cached afterwards:

- **Use another network** (a phone hotspot or VPN) for the first `npm install` and the first `npm run dist`.
- **Download in a browser.** Get `electron-v<version>-win32-x64.zip` (version from `node_modules/electron/package.json`) from <https://github.com/electron/electron/releases> and put it in `%LOCALAPPDATA%\electron\Cache`. Then run `node node_modules/electron/install.js`.
- **Use a mirror.** Create `desktop/.npmrc` with:
  ```ini
  electron_mirror=https://npmmirror.com/mirrors/electron/
  electron_builder_binaries_mirror=https://npmmirror.com/mirrors/electron-builder-binaries/
  ```
  This downloads build tools from a third-party server, so decide whether that is acceptable for your releases.

Check that Electron is installed: `desktop/node_modules/electron/dist/electron.exe` must exist.

## 2. Build and try it locally

```bash
cd desktop
npm run dev       # stage the app and open it in Electron (no installer)
npm run dist      # stage the app and build desktop/dist/ShopApp-Setup-<version>.exe
```

`npm run build:app` (run by both commands) does the following:

1. Downloads portable PHP (NTS x64, checksum-verified) into `desktop/build/php` and writes its `php.ini`.
2. Runs `npm run build` in the repository root to compile the assets into `public/build`.
3. Copies the Laravel app into `desktop/build/app`. Only git-tracked files are copied, so `.env`, `vendor/`, `node_modules/`, tests and Docker files never ship.
4. Runs `composer install --no-dev` there with the bundled PHP.

electron-builder then packs `main.js` plus `build/php` and `build/app` into an NSIS installer.

Use a different PHP branch with `SHOPAPP_PHP_SERIES=8.3 npm run dist`. The app requires PHP 8.3 or newer.

### What to check before releasing

Install the `.exe` from `desktop/dist/` and check:

- [ ] The date confirmation appears. *No* opens Windows date settings and closes the app.
- [ ] On a fresh install, the setup page asks for the shop name, owner and seller.
- [ ] You can log in, make a sale, print a receipt and export a report to CSV.
- [ ] *File → Back up data now* writes a file to `%APPDATA%\ShopApp\backups`.
- [ ] *Help → About* shows the expected version.

To test a fresh install again, close ShopApp and rename `%APPDATA%\ShopApp`. **Never delete it on a real shop computer**: it holds the shop's database.

## 3. Release a new version

The version lives in `desktop/package.json`, and each release gets a git tag `vX.Y.Z`. Use [semantic versioning](https://semver.org/):

| Command | Example | Use for |
|---------|---------|---------|
| `npm run release -- patch` | 1.0.0 → 1.0.1 | bug fixes |
| `npm run release -- minor` | 1.0.0 → 1.1.0 | new features |
| `npm run release -- major` | 1.0.0 → 2.0.0 | breaking changes |
| `npm run release -- 1.4.0` | → 1.4.0 | an exact version |

Steps:

```bash
git status                       # must be clean: commit your work first
cd desktop
npm run release -- minor         # bump, commit "Release v1.1.0", tag v1.1.0, build
git push origin HEAD v1.1.0      # publish the commit and tag (never done automatically)
```

If the build fails, the script removes the version commit and tag, so you can fix the problem and run it again.

### Shipping the update to shops

You can deliver the update in one of two ways:

**A. Auto-update through GitHub Releases (recommended).** Create a GitHub token with `repo` scope (*GitHub → Settings → Developer settings → Personal access tokens*), then run the release with it:

```powershell
$env:GH_TOKEN = "<your token>"
npm run release -- minor
```

The installer and `latest.yml` are uploaded to a GitHub Release in `gameracer/ShopApp`. Running apps check for updates on start (and under *Help → Check for updates*), download in the background and ask to restart. Auto-update only works if the repository's releases are publicly downloadable. For a private repository, use option B.

**B. Manual.** Without `GH_TOKEN`, the installer is only written to `desktop/dist/`. Copy `ShopApp-Setup-<version>.exe` to the shop computer (USB, shared folder, email) and run it. It upgrades in place and keeps all data.

### What happens on the shop computer during an update

1. The database is backed up to `%APPDATA%\ShopApp\backups\shopapp-<date>-before-v<version>.sqlite`.
2. Caches from the old version are cleared.
3. Database migrations run (`php artisan migrate --force`).

The 30 most recent backups are kept. *File → Restore a backup…* rolls the data back if needed.

## 4. Optional

- **Icon.** Put a 256×256 `icon.ico` in `desktop/resources/` and rebuild.
- **Code signing.** Without a certificate, Windows SmartScreen warns on first install (*More info → Run anyway*). With a code-signing certificate, set `CSC_LINK` (path to the `.pfx`) and `CSC_KEY_PASSWORD` before building, and electron-builder signs the installer.

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `Timeout awaiting 'request'` during `npm run dist` | See [If Electron downloads hang](#if-electron-downloads-hang). |
| `electron` won't start / `electron.exe` missing | Electron's download didn't finish. Run `node node_modules/electron/install.js` (see above). |
| `Checksum mismatch for php-…zip` | The cached zip was corrupt and has been deleted. Run the build again. |
| `Commit or stash your changes first` | `npm run release` needs a clean `git status`. |
| The app says "ShopApp could not start" | Check `%APPDATA%\ShopApp\logs\desktop.log` and `%APPDATA%\ShopApp\storage\logs\`. |

## Files

| Path | Purpose |
|------|---------|
| `desktop/main.js` | Electron main process: date check, starts PHP, window, menu, backups, updates |
| `desktop/scripts/prepare.mjs` | `npm run build:app`: fetches PHP and Composer, stages the Laravel app |
| `desktop/scripts/release.mjs` | `npm run release`: version bump, tag and build |
| `desktop/electron-builder.yml` | Installer and update settings |
| `desktop/router.php` | Router for PHP's built-in server (shipped as `desktop-server.php`) |
| `desktop/splash.html` | Loading screen |
