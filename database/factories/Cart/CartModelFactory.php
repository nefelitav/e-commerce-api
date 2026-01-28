<?php

namespace Database\Factories\Cart;

use App\Models\Cart\CartModel;
use App\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartModel>
 */
class CartModelFactory extends Factory
{
    /**
     * @var string
     */
    protected $model = CartModel::class;

    /**
     * Model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserModel::factory(),
        ];
    }
}
