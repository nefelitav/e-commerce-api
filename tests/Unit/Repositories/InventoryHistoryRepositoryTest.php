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
        $this->repository = new InventoryHistoryRepository;
    }

    public function test_it_lists_entries_by_product_id(): void
    {
        $product = ProductModel::factory()->create();
        InventoryHistoryModel::factory()->count(3)->create(['product_id' => $product->id]);

        $other = ProductModel::factory()->create();
        InventoryHistoryModel::factory()->count(2)->create(['product_id' => $other->id]);

        $result = $this->repository->listByProductId($product->id);

        $this->assertSame(3, $result->total());
        $this->assertCount(3, $result->items());
        $this->assertEquals($product->id, $result->items()[0]->productId);
    }

    public function test_it_lists_entries_oldest_first_and_paginates(): void
    {
        $product = ProductModel::factory()->create();
        $ids = InventoryHistoryModel::factory()->count(5)->create(['product_id' => $product->id])->pluck('id')->all();

        $page2 = $this->repository->listByProductId($product->id, page: 2, perPage: 2);

        $this->assertSame(5, $page2->total());
        $this->assertSame(3, $page2->lastPage());
        $this->assertSame([$ids[2], $ids[3]], array_map(static fn ($e) => $e->id, $page2->items()));
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
            'change_type' => InventoryChangeType::Adjustment->value,
            'quantity_changed' => 5,
            'previous_quantity' => 10,
            'new_quantity' => 15,
        ]);
    }
}
