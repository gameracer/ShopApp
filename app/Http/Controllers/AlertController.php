<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Support\Shop;

class AlertController extends Controller
{
    public function __invoke()
    {
        $days = Shop::expiryAlertDays();
        $nearExpiry = Product::nearExpiry($days)->with('tags')->orderBy('expiry_date')->get();

        return view('alerts', [
            'days' => $days,
            'expired' => $nearExpiry->filter->isExpired(),
            'expiring' => $nearExpiry->reject->isExpired(),
            'lowStock' => Product::lowStock()->orderBy('quantity')->get(),
            'overdue' => Sale::unpaid()->with('customer')->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())->orderBy('due_date')->get(),
        ]);
    }
}
