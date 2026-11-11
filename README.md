# ShopApp – Shop Management System

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
- **Point of sale.** Use ↑/↓ and Enter to pick items or a barcode scanner, and the change due is worked out for cash sales. A sale can be paid by cash, mobile money, bank or credit, and has a printable receipt.
- **Stock checks.** Stock goes down automatically on every sale. You cannot sell more than you have, and expired items cannot be sold.
- **Deleting a sale (owner only)** puts its items back in stock.
- **What sellers cannot do.** Sellers never see buying prices or profit, and cannot change prices. They can sell, add stock, and manage customers and credit.

### Role permissions

| Action | Owner | Seller |
|--------|:-----:|:------:|
| Sell (POS), view own sales, receipts | ✅ | ✅ |
| Add stock received | ✅ (can update prices) | ✅ (quantity/expiry only) |
| Customers, credit buyers, record repayments | ✅ | ✅ |
| See alerts (expiry / low stock / overdue) | ✅ | ✅ |
| Create / edit / delete products and tags | ✅ | ❌ |
| See buying prices & profit | ✅ | ❌ |
| Expenses, reports, users, settings | ✅ | ❌ |
| Delete a sale (stock is restored) | ✅ | ❌ |

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
