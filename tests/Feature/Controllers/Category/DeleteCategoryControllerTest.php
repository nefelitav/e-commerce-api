<?php

namespace Tests\Feature\Controllers\Category;

use App\Models\Category\CategoryModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DeleteCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_category_successfully(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $category = CategoryModel::factory()->create();

        $response = $this->deleteJson(route('v1.categories.destroy', ['id' => $category->id]));

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_delete_nonexistent_category_returns_404(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $response = $this->deleteJson(route('v1.categories.destroy', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
