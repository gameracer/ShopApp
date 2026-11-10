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

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
