# ShopApp – Shop Management System

Developed by **Mndima Technologies**.

A shop management app built with **Laravel 13** and **Tailwind CSS 4** (with Alpine.js). The whole interface is available in **English** and **Kiswahili**.

## Features

| # | Feature | Where |
|---|---------|-------|
| 1 | Laravel + Tailwind CSS | whole app |
| 2 | Two roles: **Owner** and **Seller** | *Users* (owner only) |
| 3 | Register items with **buying and selling price**, unit, reorder level and expiry date; record stock received | *Products*, *Add stock* |
| 4 | **English / Kiswahili**: switch with the EN/SW toggle; each user's choice is saved | header, login page, *My profile* |
| 5 | **Fast search** by name, SKU/barcode or tag, plus **tag filters**, on the product list, the point of sale and the stock-in screen | *Products*, *New sale*, *Add stock*, *Tags* |
| 6 | **Daily, monthly and yearly reports**: revenue, cost of goods, gross profit, expenses, net profit, money received, credit given, top products, sales by seller and payment method; printable and exportable to CSV | *Reports* (owner) |
| 7 | **Expenses** such as electricity, water, food, salaries, rent, transport and taxes | *Expenses* (owner) |
| 8 | **Credit buyers**: who owes what, unpaid invoices, due dates, overdue invoices, and repayments in instalments | *Credit buyers*, *Customers* |
| 9 | **Near-expiry alerts**: a bell badge, a dashboard panel and an *Alerts* page for expired and soon-to-expire items (the number of days is set in *Settings*), plus low stock and overdue credit | header bell, *Dashboard*, *Alerts* |

Other details:
- **Point of sale.** Use ↑/↓ and Enter to pick items or a barcode scanner, and the change due is worked out for cash sales. A sale can be paid by cash, mobile money, bank or credit, or **split** across cash, mobile and bank (change comes from the cash part, and anything left unpaid becomes credit). Every sale has a printable receipt. The customer is optional unless part of the bill is left unpaid.
- **Seller panel.** Sellers log in to a home screen of big cards: **Sell**, **Credit customers**, **My sales**, **Customers**, **Products** and **Alerts**, with badges for low stock, debtors and alerts, and their sales today. A slim top bar (*Home*, *Sell*, *My sales*, *Credit buyers*) replaces the sidebar. Products show as large tiles with category tabs; tap or scan to add. Pick the payment method, tap a cash amount, and press **Pay** (or **F9**). The till shows the change, prints the receipt if auto-print is on, and stays open for the next customer: tap *New sale*, press Enter, or just scan the next item. **F2** jumps to search. The customer field stays hidden unless part of the bill is left unpaid.
- **Barcode scanner and receipt printer.** See [Hardware](#hardware-barcode-scanner--receipt-printer) below.
- **Stock checks.** Stock goes down automatically on every sale. You cannot sell more than you have, and expired items cannot be sold.
- **Deleting a sale (owner only)** puts its items back in stock.
- **What sellers cannot do.** Sellers never see buying prices or profit, and cannot change prices or add stock. They can sell and manage customers and credit.

### Role permissions

| Action | Owner | Seller |
|--------|:-----:|:------:|
| Sell (POS), view own sales, receipts | ✅ | ✅ |
| Add stock received | ✅ | ❌ |
| Customers, credit buyers, record repayments | ✅ | ✅ |
| See alerts (expiry / low stock / overdue) | ✅ | ✅ |
| Create / edit / delete products and tags | ✅ | ❌ |
| See buying prices & profit | ✅ | ❌ |
| Expenses, reports, users, settings | ✅ | ❌ |
| Delete a sale (stock is restored) | ✅ | ❌ |

## User manual

An illustrated manual in English and Kiswahili is built into the app under **Help** (owners: sidebar; sellers: the menu under their name; Windows app: **F1**). It follows the user's language.

- Text: [docs/manual/en.md](docs/manual/en.md) and [docs/manual/sw.md](docs/manual/sw.md). Edit these; the Help page renders them.
- Screenshots: `public/manual/<locale>/*.webp`. Retake them all after UI changes with `npm run build && npm run manual:screenshots` (add a name to retake one, e.g. `npm run manual:screenshots -- till`). The script uses a throwaway demo database and headless Chrome or Edge, so it never touches real data.

## Requirements

- PHP 8.3+ with the `pdo_sqlite` extension (or MySQL/PostgreSQL)
- Composer
- Node.js 20+ and npm

## Installation

```bash
git clone <repo-url> ShopApp && cd ShopApp
composer setup        # install, create .env, generate key, migrate + seed, build assets
php artisan serve     # http://localhost:8000
```

Or step by step:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite          # default database is SQLite
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

To use MySQL instead, set `DB_CONNECTION=mysql` and the `DB_*` values in `.env` before running `migrate`.

### Demo logins (created by the seeder)

| Role | Email | Password | Language |
|------|-------|----------|----------|
| Owner | `owner@shop.test` | `password` | English |
| Seller | `seller@shop.test` | `password` | Kiswahili |

These accounts, plus sample products, tags, customers, three weeks of sales, credit sales and expenses, are only created when the app is **not** running in production.

### First-run setup (production)

In production (`APP_ENV=production`, the Docker image and the desktop app) no accounts are created. The first visit opens **/setup**, where you choose:

- the **shop name** (optional; defaults to "My Shop"),
- the **owner** account (name, email, password, language),
- optionally a **seller** account.

The setup page closes for good once the first user exists. More users can be added later under *Users*.

### Automatic backups (server)

*Settings → Backups* sets the schedule (`php artisan backup:run --if-due` is registered with the Laravel scheduler every minute). On a server, run the scheduler from cron:

```
* * * * * cd /path/to/shopapp && php artisan schedule:run >> /dev/null 2>&1
```

Backups are SQLite copies written to `storage/app/backups` (or `SHOPAPP_BACKUP_PATH`). `php artisan backup:run` makes one by hand. With MySQL/PostgreSQL, use the database's own backup tools instead.

## Desktop app (Windows)

`desktop/` packages ShopApp as a Windows app with [Electron](https://www.electronjs.org/). It bundles a portable PHP 8.4 and the Laravel app, runs fully offline on SQLite, and updates itself from GitHub Releases.

- **Data** is kept in `%APPDATA%\ShopApp` (database, logs, backups), so reinstalling or updating never touches it.
- **Backups**: automatic backups run on the schedule set in *Settings → Backups* (every hour, day or week at a chosen time; daily at 20:00 by default), and optionally when ShopApp closes. A backup missed while the computer was off is taken as soon as ShopApp opens again. Each backup can also be copied to a second folder (USB drive, OneDrive…). Backups are kept in `%APPDATA%\ShopApp\backups`: the newest *N* automatic ones (set in Settings) plus the newest 30 manual / before-update ones. One is also taken before every update, and *File → Back up data now / Restore a backup…* handles manual ones.
- **Date check**: on every launch, the user confirms the computer's date and time. Answering *No* opens Windows date settings and closes ShopApp, because sales, expiry alerts and reports depend on the clock. There is an extra warning if the clock is earlier than the last launch.
- **Updates**: when you publish releases to GitHub (see below), the app checks for updates on start and under *Help → Check for updates*, downloads them in the background and asks to restart.
- **Installer clean-up**: after a successful install, the installer deletes itself (`desktop/resources/installer.nsh`), both for downloaded installers and for automatic updates. It is kept when run from a USB stick, CD or network drive, so one copy can set up several computers, or when started with `/KEEP` (`ShopApp-Setup-x.y.z.exe /KEEP`).

### Building and releasing

Builds run locally on Windows (Node 22+ and git; the build downloads its own PHP and Composer):

```bash
npm install && cd desktop && npm install
npm run dist                 # → desktop/dist/ShopApp-Setup-<version>.exe
npm run release -- patch     # bump version, commit, tag vX.Y.Z, build
```

See **[desktop/BUILD.md](desktop/BUILD.md)** for the full procedure: setup, testing, versioning, publishing auto-updates, code signing and troubleshooting.
## Hardware: barcode scanner & receipt printer

Both are plug and play, with no extra software in ShopApp.

**Barcode scanner** (USB or Bluetooth, in keyboard mode, which is the default for almost all scanners). Plug it in and scan. ShopApp tells a scan from typing by how fast the keys arrive:

| Where you scan | What happens |
|---|---|
| *New sale* | The item is added to the cart, or its quantity goes up by one. Out-of-stock and expired items are refused with a warning beep. |
| *Add stock* | The item is added, or its quantity goes up by one. |
| *New / Edit product* | The code goes into *SKU / Barcode*. |
| Anywhere else | The product opens. If the code is unknown, the owner goes to *New product* with the code filled in. |

A product's barcode is its **SKU / Barcode** field. Matching ignores letter case and leading zeros, so `012345678905`, `12345678905` and `0012345678905` find the same product.

If a barcode doesn't work, scan it in *Settings → Devices on this computer → Barcode scanner*. It shows exactly what the scanner sent, how many characters, and which product (if any) it matches. Common causes:
- The SKU saved on the product differs from the code on the pack. Fix the SKU on the product.
- The barcode column was opened in Excel before a CSV import. Excel turns long codes into `6.00123E+12`, which can't be recovered. The import now refuses such rows: format the column as *Text*, retype the codes and import again.
- The scanner's keyboard language differs from Windows' (letters or symbols come out wrong). Set the scanner to the same layout using its setup barcodes. You can switch off the scan beep in *Settings*, and *Settings → Devices on this computer* has a box for testing the scanner.

**Thermal receipt printer** (58 mm or 80 mm, USB/LAN/Bluetooth, any brand with a Windows driver, e.g. Xprinter, Epson TM, Rongta). Plug it in, and install the maker's driver if Windows does not find one. Then:

- *Settings → Receipt printer & barcode scanner* sets the paper width, a footer line, and **auto-print after every sale**.
- *Print receipt* on a sale prints a receipt sized for the roll. *A4* prints the full page.
- **Desktop app**: prints with no dialog. The receipt is drawn at the printer's own resolution and sent straight to the printer in **ESC/POS**, the command language almost all 58/80 mm thermal printers understand. This skips the Windows driver, which on many cheap thermal printers prints blank paper when used silently. Under *Settings → Devices on this computer* you can leave the printer on *Automatic* (it finds a printer named like POS/receipt/thermal/XP-/TM-) or pick one, and switch the *Printing method* to the Windows driver for a printer that is not a thermal receipt printer. *Print with dialog* always uses the Windows driver. The choices are saved per computer in `%APPDATA%\ShopApp\devices.json`. *Print receipt* shows a preview first; auto-print after a sale prints straight away.
- **Web browser**: the print window opens. Choose the receipt printer, set margins to *None*, and the browser remembers it.
- **Cash drawer** (RJ11 into the printer): in the desktop app, tick *Open the cash drawer with every receipt* under *Settings → Devices on this computer*. With the Windows driver methods, turn on the driver's own *open cash drawer* option instead (in Xprinter/Epson drivers, under *Printing Preferences → Peripherals / Cash Drawer*).

**Dual-screen touch POS** (touch screen for the cashier, second screen facing the customer):

- **Customer display.** Like a supermarket screen: the customer sees a numbered list of their items (quantity, price, amount), the last item scanned in large type, the number of items, the total to pay, the amount paid and change or balance (never the payment method, which is internal), plus the customer and cashier names. After payment it shows *Thank you* with the invoice number, the order and the change for 15 seconds, then a welcome screen with the shop name.
  - **Desktop app:** opens it full screen on the second screen automatically, and follows screens being plugged in or out. The display never takes keyboard focus, so the barcode scanner keeps typing into the till. Turn it off or pick a screen under *View → Customer display* (**Ctrl+Shift+D**) or *Settings → Devices on this computer*.
  - **Web browser:** *Customer display* on *New sale* opens it in a popup. Drag it to the second screen and press F11. The page is `/display`. It needs no login because it only shows what the till sends it.
- **Desktop app shortcuts.** **F11**: full screen on/off. **Ctrl+Shift+D**: customer display on/off. **Ctrl+Shift+M**: show/hide the menu bar (when hidden, **Alt** shows it for a moment). These choices are remembered on each computer.
- **Touch screens.** Larger buttons, inputs and list rows, quick cash buttons (*Exact*, then round amounts above the total), number pads for amounts, and no accidental double-tap or pinch zoom. With a mouse, the screens look the same as before.
- **On-screen keyboard.** Tapping a field with a finger opens ShopApp's own keyboard: letters for text fields, a number pad for amounts and quantities. It types straight into the field, ↵ works like Enter, ⌄ closes it, and it closes when you tap elsewhere. Mouse clicks and the barcode scanner never open it. Turn it off per computer in *Settings → Devices on this computer*. ShopApp has its own keyboard because Windows doesn't open its touch keyboard while any keyboard is attached, and a USB scanner counts as one.

## Development

```bash
npm run dev           # Vite dev server with hot reload
php artisan test      # feature test suite
./vendor/bin/pint     # code style
```

## Project structure

```
app/
  Http/Controllers/     Products, Stock, Sales (POS), Credit, Customers, Expenses, Reports, Users, Settings, Alerts
  Http/Middleware/      SetLocale, EnsureRole (role:owner), EnsureUserIsActive
  Models/               Product, Tag, StockEntry, Sale, SaleItem, Payment, Customer, Expense, Setting, User
  Services/             SaleService (sell, repay, void), ReportService (daily/monthly/yearly)
lang/sw.json            Kiswahili translations (English strings are the keys)
lang/sw/*.php           Kiswahili validation, auth and pagination messages
resources/js/shop.js    Alpine components for the POS and stock-in screens
resources/views/        Blade + Tailwind views
```

### Adding or changing translations

Every string in the interface is wrapped in `__('English text')`. To translate a new string, add `"English text": "Maandishi ya Kiswahili"` to `lang/sw.json`.
