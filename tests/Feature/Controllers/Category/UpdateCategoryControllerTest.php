<?php

namespace Tests\Feature\Controllers\Category;

use App\Models\Category\CategoryModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class UpdateCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->putJson(route('v1.categories.update', ['id' => 1]), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_regular_user_returns_403(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $category = CategoryModel::factory()->create();
        $response = $this->putJson(route('v1.categories.update', $category->id), []);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_update_category_successfully(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create([
            'name' => 'Old Category Name',
            'description' => 'Old Category Description',
            'parent_id' => null,
        ]);

        $payload = [
            'id' => $category->id,
            'name' => 'Updated Category Name',
            'description' => 'New Category Description',
            'parent_id' => null,
        ];

        $response = $this->putJson(route('v1.categories.update', $category->id), $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                'name' => 'Updated Category Name',
                'description' => 'New Category Description',
                'parent_id' => null,
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category Name',
        ]);
    }

    public function test_update_category_with_parent_successfully(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $parentCategory = CategoryModel::factory()->create();

        $childCategory = CategoryModel::factory()->create([
            'name' => 'Child Category',
            'description' => 'Old Category Description',
            'parent_id' => null,
        ]);

        $payload = [
            'id' => $childCategory->id,
            'name' => 'Updated Child Category',
            'parent_id' => $parentCategory->id,
            'description' => 'New Category Description',
        ];

        $response = $this->putJson(route('v1.categories.update', $childCategory->id), $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                'name' => 'Updated Child Category',
                'parent_id' => $parentCategory->id,
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $childCategory->id,
            'name' => 'Updated Child Category',
            'parent_id' => $parentCategory->id,
        ]);
    }

    public function test_update_category_fails_with_invalid_data(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $response = $this->putJson(route('v1.categories.update', $category->id), [
            'name' => '',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['name']);
    }
}
