<?php

namespace Database\Factories\Product;

use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductModel>
 */
class ProductModelFactory extends Factory
{
    /**
     * @var string
     */
    protected $model = ProductModel::class;

    /**
     * Model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'price' => $this->faker->randomFloat(2, 1, 1000),
            'quantity' => $this->faker->numberBetween(0, 100),
            'category_id' => CategoryModel::factory(),
        ];
    }

    /**
     * State for zero-stock products.
     */
    public function outOfStock(): self
    {
        return $this->state(fn () => [
            'quantity' => 0,
        ]);
    }
}
