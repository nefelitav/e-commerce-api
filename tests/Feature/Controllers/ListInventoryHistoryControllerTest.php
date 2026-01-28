<?php

namespace Tests\Feature\Controllers;

use App\Models\InventoryHistory\InventoryHistoryModel;
use App\Models\Product\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ListInventoryHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_inventory_history_for_product(): void
    {
        $product = ProductModel::factory()->create();
        InventoryHistoryModel::factory()->count(3)->create([
            'product_id' => $product->id,
        ]);

        $otherProduct = ProductModel::factory()->create();
        InventoryHistoryModel::factory()->count(2)->create([
            'product_id' => $otherProduct->id,
        ]);

        $response = $this->getJson(route('products.inventory-history.index', ['id' => $product->id]));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'product_id',
                        'change_type',
                        'quantity_changed',
                        'previous_quantity',
                        'new_quantity',
                        'created_at',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_returns_validation_error_for_nonexistent_product(): void
    {
        $response = $this->getJson(route('products.inventory-history.index', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('id');
    }
}

