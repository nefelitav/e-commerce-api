<?php

namespace Tests\Feature\Controllers\Category;

use App\Models\Category\CategoryModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ListCategoriesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_list_of_categories(): void
    {
        CategoryModel::factory()->count(3)->create();

        $response = $this->getJson(route('v1.categories.index'));

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
