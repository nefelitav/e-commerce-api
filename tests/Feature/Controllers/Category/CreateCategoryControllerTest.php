<?php

namespace Tests\Feature\Controllers\Category;

use App\Models\Category\CategoryModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CreateCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->postJson(route('v1.categories.store'), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_regular_user_returns_403(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson(route('v1.categories.store'), []);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_create_category_successfully(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $payload = [
            'name' => 'New Category',
            'description' => 'A description for new category',
            'parent_id' => null,
        ];

        $response = $this->postJson(route('v1.categories.store'), $payload);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'description', 'parent_id'],
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
            'description' => 'A description for new category',
            'parent_id' => null,
        ]);
    }

    public function test_create_category_fails_validation(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->postJson(route('v1.categories.store'), ['description' => 'Some description']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('name');
    }

    public function test_create_category_with_parent(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $parentCategory = CategoryModel::factory()->create();

        $payload = [
            'name' => 'Child Category',
            'description' => 'Child category description',
            'parent_id' => $parentCategory->id,
        ];

        $response = $this->postJson(route('v1.categories.store'), $payload);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonFragment(['parent_id' => $parentCategory->id]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Child Category',
            'parent_id' => $parentCategory->id,
        ]);
    }
}
