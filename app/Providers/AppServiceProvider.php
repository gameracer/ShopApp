<?php

namespace App\Providers;

use App\Models\Product;
use App\Support\Shop;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        View::composer('layouts.app', function ($view) {
            if (! auth()->check()) {
                return;
            }

            $view->with([
                'alertCount' => Product::nearExpiry()->count() + Product::lowStock()->count(),
                'shopName' => Shop::name(),
            ]);
        });
    }
}
