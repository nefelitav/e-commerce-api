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
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    }

    public function test_list_categories_with_pagination_returns_correct_page(): void
    {
        CategoryModel::factory()->count(25)->create();

        $page = 2;
        $perPage = 10;

        $response = $this->getJson(route('v1.categories.index', [
            'page' => $page,
            'per_page' => $perPage,
        ]));

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);

        $json = $response->json();

        $this->assertEquals($page, $json['meta']['current_page']);
        $this->assertEquals($perPage, $json['meta']['per_page']);
        $this->assertEquals(25, $json['meta']['total']);
        $this->assertEquals(3, $json['meta']['last_page']);

        $this->assertCount($perPage, $json['data']);

        $expectedIds = range(11, 20);
        $actualIds = array_column($json['data'], 'id');
        $this->assertEquals($expectedIds, $actualIds);
    }
}
