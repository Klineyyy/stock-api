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
        return [
            'item_code' => strtoupper(fake()->unique()->bothify('???-###')),
            'item_name' => ucfirst(fake()->unique()->words(3, true)),
            'barcode' => fake()->unique()->numerify('48000########'),
            'stock_uom' => 'Nos',
        ];
    }
}
