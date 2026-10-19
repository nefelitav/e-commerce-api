<?php

namespace Tests\Feature\Controllers\InventoryHistory;

use App\Models\InventoryHistory\InventoryHistoryModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ListInventoryHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $product = ProductModel::factory()->create();
        $response = $this->getJson(route('v1.products.inventory-history.index', ['id' => $product->id]));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_regular_user_returns_403(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create();
        $response = $this->getJson(route('v1.products.inventory-history.index', ['id' => $product->id]));
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_admin_gets_inventory_history_for_product(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $product = ProductModel::factory()->create();
        InventoryHistoryModel::factory()->count(3)->create(['product_id' => $product->id]);

        $otherProduct = ProductModel::factory()->create();
        InventoryHistoryModel::factory()->count(2)->create(['product_id' => $otherProduct->id]);

        $response = $this->getJson(route('v1.products.inventory-history.index', ['id' => $product->id]));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'product_id', 'change_type', 'quantity_changed', 'previous_quantity', 'new_quantity'],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_admin_can_paginate_inventory_history(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $product = ProductModel::factory()->create();
        InventoryHistoryModel::factory()->count(5)->create(['product_id' => $product->id]);

        $response = $this->getJson(route('v1.products.inventory-history.index', ['id' => $product->id, 'per_page' => 2, 'page' => 3]));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(5, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
    }

    public function test_per_page_is_capped(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $product = ProductModel::factory()->create();

        $this->getJson(route('v1.products.inventory-history.index', ['id' => $product->id, 'per_page' => 1000]))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_index_returns_validation_error_for_nonexistent_product(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->getJson(route('v1.products.inventory-history.index', ['id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('id');
    }
}
