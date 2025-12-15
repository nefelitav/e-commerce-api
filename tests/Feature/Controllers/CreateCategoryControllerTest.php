<?php

namespace Tests\Feature\Controllers;

use App\Models\Category\CategoryModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CreateCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_category_successfully(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $payload = [
            'name' => 'New Category',
            'description' => 'A description for new category',
            'parent_id' => null,
        ];

        $response = $this->postJson(route('categories.store'), $payload);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'parent_id',
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
            'description' => 'A description for new category',
            'parent_id' => null,
        ]);
    }

    public function test_create_category_fails_validation(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $payload = [
            'description' => 'Some description',
        ];

        $response = $this->postJson(route('categories.store'), $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('name');
    }

    public function test_create_category_with_parent(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $parentCategory = CategoryModel::factory()->create();

        $payload = [
            'name' => 'Child Category',
            'description' => 'Child category description',
            'parent_id' => $parentCategory->id,
        ];

        $response = $this->postJson(route('categories.store'), $payload);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonFragment([
                'parent_id' => $parentCategory->id,
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Child Category',
            'parent_id' => $parentCategory->id,
        ]);
    }
}
