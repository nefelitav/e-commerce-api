<?php

namespace Tests\Unit\Services;

use App\Dto\InventoryHistory\InventoryHistoryEntry;
use App\Dto\InventoryHistory\UnpersistedInventoryHistoryEntry;
use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Enums\InventoryChangeType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductAlreadyExistsException;
use App\Exceptions\ProductNotFoundException;
use App\Models\InventoryHistory\InventoryHistoryModel;
use App\Models\Product\ProductModel;
use App\Repositories\InventoryHistory\InventoryHistoryRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Services\AuditLogger;
use App\Services\Product\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ProductServiceTest extends TestCase
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

        $this->repository = $this->createMock(ProductRepositoryInterface::class);
        $this->inventoryHistoryRepository = $this->createMock(InventoryHistoryRepositoryInterface::class);
        $this->service = new ProductService($this->repository, $this->inventoryHistoryRepository, new AuditLogger());
    }

    public function test_listProducts_returns_array_of_products(): void
    {
        $productModels = ProductModel::factory()->count(2)->create();

        $products = $productModels->map(fn($model) => Product::fromModel($model));

        $paginator = new LengthAwarePaginator(
            $products,
            count($products),
            15,
            1
        );

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn($paginator);

        $result = $this->service->listProducts();

        $this->assertSame($paginator, $result);
    }

    public function test_getProductsByCategoryId_returns_array_of_products(): void
    {
        $categoryId = 1;
        $products = [Product::fromModel(ProductModel::factory()->create())];
        $this->repository
            ->expects($this->once())
            ->method('findByCategoryId')
            ->with($categoryId)
            ->willReturn($products);

        $result = $this->service->getProductsByCategoryId($categoryId);

        $this->assertSame($products, $result);
    }

    public function test_getProductById_returns_product(): void
    {
        $id = 1;
        $product = Product::fromModel(ProductModel::factory()->create());
        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($product);

        $result = $this->service->getProductById($id);

        $this->assertSame($product, $result);
    }

    public function test_createProduct_throws_if_product_exists(): void
    {
        $unpersisted = new UnpersistedProduct(
            name: 'Existing Product',
            description: 'desc',
            price: 100,
            quantity: 1,
            categoryId: 1,
        );

        $existingProduct = Product::fromModel(ProductModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('findByName')
            ->with('Existing Product')
            ->willReturn($existingProduct);

        $this->expectException(ProductAlreadyExistsException::class);

        $this->service->createProduct($unpersisted);
    }

    public function test_createProduct_persists_and_returns_product(): void
    {
        $unpersisted = new UnpersistedProduct(
            name: 'New Product',
            description: 'desc',
            price: 100,
            quantity: 1,
            categoryId: 1,
        );

        $persistedProduct = Product::fromModel(ProductModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('findByName')
            ->with('New Product')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('persist')
            ->with($unpersisted)
            ->willReturn($persistedProduct);

        $this->inventoryHistoryRepository
            ->expects($this->once())
            ->method('record')
            ->with($this->isInstanceOf(UnpersistedInventoryHistoryEntry::class))
            ->willReturn(InventoryHistoryEntry::fromModel(InventoryHistoryModel::factory()->create()));

        $result = $this->service->createProduct($unpersisted);

        $this->assertSame($persistedProduct, $result);
    }

    public function test_updateProduct_calls_repository_update_without_history_when_quantity_unchanged(): void
    {
        $id = 1;
        $quantity = 5;

        $unpersisted = new UnpersistedProduct(
            name: 'Updated Product',
            description: 'desc',
            price: 100,
            quantity: $quantity,
            categoryId: 1,
        );

        /** @var ProductModel $lockedModel */
        $lockedModel = ProductModel::factory()->create(['quantity' => $quantity]);

        $updatedProduct = Product::fromModel($lockedModel);

        $this->repository
            ->expects($this->once())
            ->method('findByIdForUpdate')
            ->with($id)
            ->willReturn($lockedModel);

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($id, $unpersisted)
            ->willReturn($updatedProduct);

        $this->inventoryHistoryRepository
            ->expects($this->never())
            ->method('record');

        $result = $this->service->updateProduct($id, $unpersisted);

        $this->assertSame($updatedProduct, $result);
    }

    public function test_updateProduct_records_inventory_history_when_quantity_changes(): void
    {
        $id = 1;

        $unpersisted = new UnpersistedProduct(
            name: 'Updated Product',
            description: 'desc',
            price: 100,
            quantity: 20,
            categoryId: 1,
        );

        /** @var ProductModel $lockedModel */
        $lockedModel = ProductModel::factory()->create(['quantity' => 10]);

        $updatedProduct = Product::fromModel(ProductModel::factory()->create(['quantity' => 20]));

        $this->repository
            ->expects($this->once())
            ->method('findByIdForUpdate')
            ->with($id)
            ->willReturn($lockedModel);

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($id, $unpersisted)
            ->willReturn($updatedProduct);

        $this->inventoryHistoryRepository
            ->expects($this->once())
            ->method('record')
            ->with($this->callback(function (UnpersistedInventoryHistoryEntry $entry) {
                return $entry->changeType === InventoryChangeType::Adjustment
                    && $entry->previousQuantity === 10
                    && $entry->newQuantity === 20
                    && $entry->quantityChanged === 10;
            }))
            ->willReturn(InventoryHistoryEntry::fromModel(InventoryHistoryModel::factory()->create()));

        $result = $this->service->updateProduct($id, $unpersisted);

        $this->assertSame($updatedProduct, $result);
    }

    public function test_updateProduct_throws_InsufficientStockException_when_quantity_is_negative(): void
    {
        $id = 1;

        $unpersisted = new UnpersistedProduct(
            name: 'Updated Product',
            description: 'desc',
            price: 100,
            quantity: -1,
            categoryId: 1,
        );

        /** @var ProductModel $lockedModel */
        $lockedModel = ProductModel::factory()->create(['quantity' => 5]);

        $this->repository
            ->expects($this->once())
            ->method('findByIdForUpdate')
            ->with($id)
            ->willReturn($lockedModel);

        $this->repository
            ->expects($this->never())
            ->method('update');

        $this->expectException(InsufficientStockException::class);

        $this->service->updateProduct($id, $unpersisted);
    }

    public function test_updateProduct_throws_ProductNotFoundException(): void
    {
        $id = 1;
        $unpersisted = new UnpersistedProduct(
            name: 'Updated Product',
            description: 'desc',
            price: 100,
            quantity: 1,
            categoryId: 1,
        );

        $this->repository
            ->expects($this->once())
            ->method('findByIdForUpdate')
            ->with($id)
            ->willReturn(null);

        $this->expectException(ProductNotFoundException::class);

        $this->service->updateProduct($id, $unpersisted);
    }

    public function test_deleteProduct_returns_true(): void
    {
        $id = 1;

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willReturn(true);

        $result = $this->service->deleteProduct($id);

        $this->assertTrue($result);
    }

    public function test_deleteProduct_throws_ProductNotFoundException(): void
    {
        $id = 1;

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willThrowException(new ProductNotFoundException($id));

        $this->expectException(ProductNotFoundException::class);

        $this->service->deleteProduct($id);
    }
}
