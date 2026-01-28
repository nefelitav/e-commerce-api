<?php

namespace Database\Factories\Order;

use App\Models\Order\OrderItemModel;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemModel>
 */
class OrderItemModelFactory extends Factory
{
    /**
     * @var class-string<OrderItemModel>
     */
    protected $model = OrderItemModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => OrderModel::factory(),
            'product_id' => ProductModel::factory(),
            'quantity' => $this->faker->numberBetween(1, 10),
            'unit_price' => $this->faker->randomFloat(2, 1, 1000),
        ];
    }
}

