<?php

namespace Tests\Unit\Services;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Models\InventoryHistory\InventoryHistoryModel;
use App\Repositories\InventoryHistory\InventoryHistoryRepository;
use App\Services\InventoryHistory\InventoryHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var InventoryHistoryRepository&\PHPUnit\Framework\MockObject\MockObject $repository */
    private InventoryHistoryRepository $repository;
    private InventoryHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(InventoryHistoryRepository::class);
        $this->service = new InventoryHistoryService($this->repository);
    }

    public function test_listByProductId_returns_array_of_entries(): void
    {
        $productId = 1;
        $entries = [InventoryHistoryEntry::fromModel(InventoryHistoryModel::factory()->create())];

        $this->repository
            ->expects($this->once())
            ->method('listByProductId')
            ->with($productId)
            ->willReturn($entries);

        $result = $this->service->listByProductId($productId);

        $this->assertSame($entries, $result);
    }
}

