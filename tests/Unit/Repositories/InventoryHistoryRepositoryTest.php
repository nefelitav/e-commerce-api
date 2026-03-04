<?php

namespace Tests\Unit\Repositories;

use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Enums\InventoryChangeType;
use App\Models\InventoryHistory\InventoryHistoryModel;
use App\Models\Product\ProductModel;
use App\Repositories\InventoryHistory\InventoryHistoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryHistoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private InventoryHistoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InventoryHistoryRepository();
    }

    public function test_it_lists_entries_by_product_id(): void
    {
        $product = ProductModel::factory()->create();
        InventoryHistoryModel::factory()->count(3)->create(['product_id' => $product->id]);

        $other = ProductModel::factory()->create();
        InventoryHistoryModel::factory()->count(2)->create(['product_id' => $other->id]);

        $result = $this->repository->listByProductId($product->id);

        $this->assertCount(3, $result);
        $this->assertEquals($product->id, $result[0]->productId);
    }

    public function test_it_records_entry(): void
    {
        $product = ProductModel::factory()->create();

        $entry = new UnpersistedInventoryHistoryEntry(
            productId: $product->id,
            changeType: InventoryChangeType::Adjustment,
            quantityChanged: 5,
            previousQuantity: 10,
            newQuantity: 15,
        );

        $result = $this->repository->record($entry);

        $this->assertDatabaseHas('inventory_history', [
            'id' => $result->id,
            'product_id' => $product->id,
            'change_type' => 'adjustment',
            'quantity_changed' => 5,
            'previous_quantity' => 10,
            'new_quantity' => 15,
        ]);
    }
}

