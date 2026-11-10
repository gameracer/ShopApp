<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $buying = fake()->numberBetween(5, 200) * 100;

        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'sku' => fake()->unique()->ean13(),
            'unit' => 'pcs',
            'buying_price' => $buying,
            'selling_price' => $buying * 1.25,
            'quantity' => fake()->numberBetween(10, 100),
            'reorder_level' => 5,
            'expiry_date' => null,
        ];
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn () => ['expiry_date' => now()->addDays($days)->toDateString()]);
    }
}
