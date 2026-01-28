<?php

namespace Database\Factories\Category;

use App\Models\Category\CategoryModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryModel>
 */
class CategoryModelFactory extends Factory
{
    /**
     * Model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'description' => fake()->text(),
            'parent_id' => null,
        ];
    }

    /**
     * State for child category with a valid parent.
     */
    public function child(CategoryModel $parent): self
    {
        return $this->state(function () use ($parent) {
            return ['parent_id' => $parent->id];
        });
    }
}
