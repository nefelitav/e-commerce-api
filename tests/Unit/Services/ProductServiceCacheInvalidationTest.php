<?php

namespace Tests\Unit\Services;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\ProductNotFoundException;
use App\Models\InventoryHistory\InventoryHistoryModel;
use App\Models\Product\ProductModel;
use App\Repositories\InventoryHistory\InventoryHistoryRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Services\AuditLogger;
use App\Services\Product\ProductService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ProductServiceCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    /** @var ProductRepositoryInterface&MockObject */
    private ProductRepositoryInterface $repository;

    /** @var InventoryHistoryRepositoryInterface&MockObject */
    private InventoryHistoryRepositoryInterface $inventoryHistoryRepository;

    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::tags(['products'])->flush();
        $this->repository = $this->createMock(ProductRepositoryInterface::class);
        $this->inventoryHistoryRepository = $this->createMock(InventoryHistoryRepositoryInterface::class);
        $this->service = new ProductService($this->repository, $this->inventoryHistoryRepository, new AuditLogger);
    }

    protected function tearDown(): void
    {
        Cache::tags(['products'])->flush();
        parent::tearDown();
    }

    public function test_create_product_flushes_product_cache(): void
    {
        Cache::tags(['products'])->put('products.all.test', 'cached_value', 300);
        Cache::tags(['products'])->put('products.category.1', 'cached_value', 300);

        $this->assertTrue(Cache::tags(['products'])->has('products.all.test'));

        $unpersisted = new UnpersistedProduct(
            name: 'New Product',
            description: 'desc',
            price: 100,
            quantity: 5,
            categoryId: 1,
        );

        $persistedProduct = Product::fromModel(ProductModel::factory()->create());

        $this->repository->method('persist')->willReturn($persistedProduct);
        $this->inventoryHistoryRepository
            ->method('record')
            ->willReturn(InventoryHistoryEntry::fromModel(InventoryHistoryModel::factory()->create()));

        $this->service->createProduct($unpersisted);

        $this->assertFalse(Cache::tags(['products'])->has('products.all.test'));
        $this->assertFalse(Cache::tags(['products'])->has('products.category.1'));
    }

    public function test_update_product_flushes_product_cache_and_forgets_specific_key(): void
    {
        $id = 1;
        Cache::tags(['products'])->put("products.{$id}", 'cached_value', 300);
        Cache::tags(['products'])->put('products.all.test', 'cached_value', 300);

        $unpersisted = new UnpersistedProduct(
            name: 'Updated Product',
            description: 'desc',
            price: 100,
            quantity: 5,
            categoryId: 1,
        );

        /** @var ProductModel $lockedModel */
        $lockedModel = ProductModel::factory()->create(['quantity' => 5]);
        $updatedProduct = Product::fromModel($lockedModel);

        $this->repository->method('findByIdForUpdate')->willReturn($lockedModel);
        $this->repository->method('update')->willReturn($updatedProduct);

        $this->service->updateProduct($id, $unpersisted);

        $this->assertFalse(Cache::tags(['products'])->has("products.{$id}"));
        $this->assertFalse(Cache::tags(['products'])->has('products.all.test'));
    }

    public function test_delete_product_flushes_product_cache_and_forgets_specific_key(): void
    {
        $id = 42;
        Cache::tags(['products'])->put("products.{$id}", 'cached_value', 300);
        Cache::tags(['products'])->put('products.all.test', 'cached_value', 300);

        $this->repository->method('delete')->willReturn(true);

        $this->service->deleteProduct($id);

        $this->assertFalse(Cache::tags(['products'])->has("products.{$id}"));
        $this->assertFalse(Cache::tags(['products'])->has('products.all.test'));
    }

    public function test_cache_not_flushed_when_create_product_throws(): void
    {
        Cache::tags(['products'])->put('products.all.test', 'cached_value', 300);

        $unpersisted = new UnpersistedProduct(
            name: 'Existing Product',
            description: 'desc',
            price: 100,
            quantity: 1,
            categoryId: 1,
        );

        $this->repository->method('persist')->willThrowException(
            new UniqueConstraintViolationException('pgsql', 'insert', [], new \Exception('duplicate key')),
        );

        try {
            $this->service->createProduct($unpersisted);
        } catch (\Throwable) {
        }

        $this->assertTrue(Cache::tags(['products'])->has('products.all.test'));
    }

    public function test_cache_not_flushed_when_delete_product_throws(): void
    {
        Cache::tags(['products'])->put('products.all.test', 'cached_value', 300);

        $this->repository->method('delete')->willThrowException(new ProductNotFoundException(999));

        try {
            $this->service->deleteProduct(999);
        } catch (\Throwable) {
        }

        $this->assertTrue(Cache::tags(['products'])->has('products.all.test'));
    }
}
