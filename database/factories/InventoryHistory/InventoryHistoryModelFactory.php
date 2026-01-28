<?php

namespace Database\Factories\InventoryHistory;

use App\Models\InventoryHistory\InventoryHistoryModel;
use App\Models\Product\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryHistoryModel>
 */
class InventoryHistoryModelFactory extends Factory
{
    /**
     * @var class-string<InventoryHistoryModel>
     */
    protected $model = InventoryHistoryModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $previous = $this->faker->numberBetween(0, 100);
        $new = $this->faker->numberBetween(0, 100);

        return [
            'product_id' => ProductModel::factory(),
            'change_type' => $this->faker->randomElement([
                'addition',
                'removal',
                'sale',
                'return',
                'adjustment',
                'transfer',
            ]),
            'quantity_changed' => $new - $previous,
            'previous_quantity' => $previous,
            'new_quantity' => $new,
            'created_at' => now(),
        ];
    }
}

