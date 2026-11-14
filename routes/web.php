<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

// Customer-facing second screen. Public: it only shows what the till broadcasts to it.
Route::view('/display', 'display')->name('display');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');

    // First-run setup (only available while no users exist).
    Route::get('/setup', [SetupController::class, 'show'])->name('setup');
    Route::post('/setup', [SetupController::class, 'store'])->name('setup.store')->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/alerts', AlertController::class)->name('alerts');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Products: everyone can browse / search, only the owner manages them.
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('/products/barcode', [ProductController::class, 'barcode'])->name('products.barcode');
    Route::middleware('role:owner')->group(function () {
        Route::get('/products/import', [ProductImportController::class, 'create'])->name('products.import');
        Route::post('/products/import', [ProductImportController::class, 'store'])->name('products.import.store')->middleware('throttle:10,1');
        Route::get('/products/import/template', [ProductImportController::class, 'template'])->name('products.import.template');
    });
    Route::resource('products', ProductController::class)->except(['index', 'show'])->middleware('role:owner');
    Route::resource('products', ProductController::class)->only(['index', 'show']);

    // Stock in (receiving goods)
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/add', [StockController::class, 'create'])->name('stock.create');
    Route::post('/stock', [StockController::class, 'store'])->name('stock.store');

    // Sales / point of sale
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/new', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('/sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
    Route::post('/sales/{sale}/payments', [CreditController::class, 'pay'])->name('sales.payments.store');

    // Customers & credit
    Route::get('/credit', [CreditController::class, 'index'])->name('credit.index');
    Route::resource('customers', CustomerController::class)->except(['destroy']);

    Route::middleware('role:owner')->group(function () {
        Route::resource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::resource('expenses', ExpenseController::class)->except(['show']);
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::get('/settings/test-receipt', [SettingController::class, 'testReceipt'])->name('settings.test-receipt');
    });
});
