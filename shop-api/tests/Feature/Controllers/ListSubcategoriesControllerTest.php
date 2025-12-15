<?php

namespace Tests\Feature\Controllers;

use App\Models\Category\CategoryModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ListSubcategoriesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_subcategories_returned_for_parent_category(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $parentCategory = CategoryModel::factory()->create();

        $subcategories = CategoryModel::factory()->count(3)->create([
            'parent_id' => $parentCategory->id,
        ]);

        $response = $this->getJson(route('categories.subcategories', ['id' => $parentCategory->id]));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'description', 'parent_id'],
                ],
            ]);

        $data = $response->json('data');

        foreach ($data as $subcategory) {
            $this->assertEquals($parentCategory->id, $subcategory['parent_id']);
        }
    }

    public function test_subcategories_returns_empty_for_category_with_no_children(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $category = CategoryModel::factory()->create();

        $response = $this->getJson(route('categories.subcategories', ['id' => $category->id]));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_subcategories_returns_404_for_nonexistent_category(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson(route('categories.subcategories', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
