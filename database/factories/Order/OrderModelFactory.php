<?php

namespace Database\Factories\Order;

use App\Models\Order\OrderModel;
use App\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderModel>
 */
class OrderModelFactory extends Factory
{
    /**
     * @var class-string<OrderModel>
     */
    protected $model = OrderModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserModel::factory(),
            'status' => $this->faker->randomElement(['pending', 'paid', 'shipped', 'cancelled']),
            'total_price' => $this->faker->randomFloat(2, 1, 5000),
        ];
    }
}

