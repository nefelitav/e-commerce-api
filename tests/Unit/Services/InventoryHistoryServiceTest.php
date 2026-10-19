<?php

namespace Tests\Unit\Services;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Models\InventoryHistory\InventoryHistoryModel;
use App\Repositories\InventoryHistory\InventoryHistoryRepositoryInterface;
use App\Services\InventoryHistory\InventoryHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class InventoryHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var InventoryHistoryRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private InventoryHistoryRepositoryInterface $repository;

    private InventoryHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(InventoryHistoryRepositoryInterface::class);
        $this->service = new InventoryHistoryService($this->repository);
    }

    public function test_list_by_product_id_returns_paginator_of_entries(): void
    {
        $productId = 1;
        $entries = [InventoryHistoryEntry::fromModel(InventoryHistoryModel::factory()->create())];
        $paginator = new LengthAwarePaginator($entries, 1, 100, 1);

        $this->repository
            ->expects($this->once())
            ->method('listByProductId')
            ->with($productId, 2, 25)
            ->willReturn($paginator);

        $result = $this->service->listByProductId($productId, 2, 25);

        $this->assertSame($paginator, $result);
    }
}
