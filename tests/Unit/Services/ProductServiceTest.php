<?php

namespace Tests\Unit\Services;

use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\ProductAlreadyExistsException;
use App\Exceptions\ProductNotFoundException;
use App\Models\Product\ProductModel;
use App\Repositories\Product\ProductRepository;
use App\Services\Product\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var ProductRepository&MockObject */
    private ProductRepository $repository;
    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(ProductRepository::class);
        $this->service = new ProductService($this->repository);
    }

    public function test_listProducts_returns_array_of_products(): void
    {
        $products = [Product::fromModel(ProductModel::factory()->create())];
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn($products);

        $result = $this->service->listProducts();

        $this->assertSame($products, $result);
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

        $result = $this->service->createProduct($unpersisted);

        $this->assertSame($persistedProduct, $result);
    }

    public function test_updateProduct_calls_repository_update(): void
    {
        $id = 1;
        $unpersisted = new UnpersistedProduct(
            name: 'Updated Product',
            description: 'desc',
            price: 100,
            quantity: 1,
            categoryId: 1,
        );

        $updatedProduct = Product::fromModel(ProductModel::factory()->create());

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($id, $unpersisted)
            ->willReturn($updatedProduct);

        $result = $this->service->updateProduct($id, $unpersisted);

        $this->assertSame($updatedProduct, $result);
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
            ->method('update')
            ->with($id, $unpersisted)
            ->willThrowException(new ProductNotFoundException($id));

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
