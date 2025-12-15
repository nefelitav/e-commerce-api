<?php

namespace Tests\Feature\Controllers;

use App\Models\Category\CategoryModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ListCategoriesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticate()
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);
    }

    public function test_index_returns_list_of_categories(): void
    {
        $this->authenticate();

        CategoryModel::factory()->count(3)->create();

        $response = $this->getJson(route('categories.index'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                    ],
                ],
            ]);
    }
}
