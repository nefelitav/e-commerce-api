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

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->deleteJson(route('v1.categories.destroy', ['id' => 1]));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_regular_user_returns_403(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $category = CategoryModel::factory()->create();
        $response = $this->deleteJson(route('v1.categories.destroy', ['id' => $category->id]));
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_admin_can_delete_category(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $response = $this->deleteJson(route('v1.categories.destroy', ['id' => $category->id]));

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_delete_nonexistent_category_returns_422(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->deleteJson(route('v1.categories.destroy', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}


