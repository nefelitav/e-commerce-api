<?php

namespace Tests\Unit\CQRS;

use App\CQRS\Commands\Product\CreateProductCommand;
use App\CQRS\Handlers\Product\CreateProductCommandHandler;
use App\Dto\Product\Product;
use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\ProductAlreadyExistsException;
use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use App\Services\Product\ProductServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class CreateProductCommandHandlerTest extends TestCase
{
    use RefreshDatabase;

    /** @var ProductServiceInterface&MockObject */
    private ProductServiceInterface $productService;
    private CreateProductCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productService = $this->createMock(ProductServiceInterface::class);
        $this->handler        = new CreateProductCommandHandler($this->productService);
    }

    public function test_handle_delegates_to_product_service_and_returns_product(): void
    {
        $category = CategoryModel::factory()->create();
        $model    = ProductModel::factory()->create(['category_id' => $category->id]);
        $product  = Product::fromModel($model);

        $command = new CreateProductCommand(
            name:        'New Widget',
            description: 'A shiny widget',
            price:       9.99,
            quantity:    50,
            categoryId:  $category->id,
        );

        $this->productService
            ->expects($this->once())
            ->method('createProduct')
            ->with(new UnpersistedProduct(
                name:        'New Widget',
                description: 'A shiny widget',
                price:       9.99,
                quantity:    50,
                categoryId:  $category->id,
            ))
            ->willReturn($product);

        $result = $this->handler->handle($command);

        $this->assertSame($product, $result);
    }

    public function test_handle_propagates_product_already_exists_exception(): void
    {
        $command = new CreateProductCommand(
            name:        'Duplicate',
            description: null,
            price:       1.00,
            quantity:    1,
            categoryId:  1,
        );

        $this->productService
            ->expects($this->once())
            ->method('createProduct')
            ->willThrowException(new ProductAlreadyExistsException('Duplicate'));

        $this->expectException(ProductAlreadyExistsException::class);

        $this->handler->handle($command);
    }
}


