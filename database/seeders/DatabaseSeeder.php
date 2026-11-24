<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::put(Setting::DEFAULTS);

        // In production no accounts are created: the first visit opens the
        // setup page (/setup), where the shop name, owner and seller are chosen.
        if (app()->isProduction()) {
            return;
        }

        User::updateOrCreate(['email' => 'owner@shop.test'], [
            'name' => 'Shop Owner',
            'password' => 'password',
            'role' => User::ROLE_OWNER,
            'locale' => 'en',
        ]);

        User::updateOrCreate(['email' => 'seller@shop.test'], [
            'name' => 'Muuzaji',
            'password' => 'password',
            'role' => User::ROLE_SELLER,
            'locale' => 'sw',
        ]);

        $this->call(DemoSeeder::class);
    }
}
