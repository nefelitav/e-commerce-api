<?php

namespace Database\Factories\Cart;

use App\Models\Cart\CartItemModel;
use App\Models\Cart\CartModel;
use App\Models\Product\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItemModel>
 */
class CartItemModelFactory extends Factory
{
    /**
     * @var string
     */
    protected $model = CartItemModel::class;

    /**
     * Model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => CartModel::factory(),
            'product_id' => ProductModel::factory(),
            'quantity' => $this->faker->numberBetween(1, 10),
        ];
    }
}
