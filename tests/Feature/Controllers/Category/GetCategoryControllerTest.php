<?php

namespace Tests\Feature\Controllers\Category;

use App\Models\Category\CategoryModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class GetCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_successful_response(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $category = CategoryModel::factory()->create();

        $response = $this->getJson(route('v1.categories.show', ['id' => $category->id]));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                ],
            ]);

        $this->assertEquals($category->id, $response->json('data.id'));
    }

    public function test_show_returns_validation_error_for_nonexistent_category(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson(route('v1.categories.show', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('id');
    }
}
