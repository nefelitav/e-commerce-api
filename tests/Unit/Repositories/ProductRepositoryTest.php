<?php

namespace Tests\Unit\Repositories;

use App\Dto\Product\UnpersistedProduct;
use App\Exceptions\ProductNotFoundException;
use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use App\Repositories\Product\ProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ProductRepository();
    }

    public function test_it_returns_all_products(): void
    {
        ProductModel::factory()->count(3)->create();

        $products = $this->repository->getAll();

        $this->assertCount(3, $products);
        $this->assertEquals(
            ProductModel::query()->first()->name,
            $products[0]->name
        );
    }

    public function test_it_finds_products_by_category_id(): void
    {
        $category = CategoryModel::factory()->create();
        $products = ProductModel::factory()->count(2)->create(['category_id' => $category->id]);
        ProductModel::factory()->count(2)->create(); // products in other categories

        $result = $this->repository->findByCategoryId($category->id);

        $this->assertCount(2, $result);
        $this->assertEquals($products[0]->name, $result[0]->name);
    }

    public function test_it_returns_empty_array_if_no_products_for_category(): void
    {
        $result = $this->repository->findByCategoryId(999);

        $this->assertSame([], $result);
    }

    public function test_it_finds_product_by_id(): void
    {
        $product = ProductModel::factory()->create();

        $result = $this->repository->findById($product->id);

        $this->assertNotNull($result);
        $this->assertEquals($product->name, $result->name);
    }

    public function test_it_returns_null_when_product_by_id_not_found(): void
    {
        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function test_it_finds_product_by_name(): void
    {
        $productName = 'Unique Product';
        ProductModel::factory()->create(['name' => $productName]);

        $result = $this->repository->findByName($productName);

        $this->assertNotNull($result);
        $this->assertEquals($productName, $result->name);
    }

    public function test_it_persists_a_new_product(): void
    {
        $category = CategoryModel::factory()->create();

        $dto = new UnpersistedProduct(
            name: 'New Product',
            description: 'Product description',
            price: 123.45,
            quantity: 10,
            categoryId: $category->id,
        );

        $result = $this->repository->persist($dto);

        $this->assertDatabaseHas('products', [
            'name' => 'New Product',
        ]);

        $this->assertEquals('New Product', $result->name);
    }

    public function test_it_updates_an_existing_product(): void
    {
        $product = ProductModel::factory()->create([
            'name' => 'Old Name',
        ]);

        $dto = new UnpersistedProduct(
            name: 'Updated Name',
            description: 'Updated description',
            price: 150.00,
            quantity: 20,
            categoryId: $product->category_id,
        );

        $result = $this->repository->update($product->id, $dto);

        $this->assertEquals('Updated Name', $result->name);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_it_throws_exception_when_updating_non_existing_product(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $dto = new UnpersistedProduct(
            name: 'Does Not Exist',
            description: 'Desc',
            price: 0,
            quantity: 0,
            categoryId: 1,
        );

        $this->repository->update(999, $dto);
    }

    public function test_it_deletes_an_existing_product(): void
    {
        $product = ProductModel::factory()->create();

        $result = $this->repository->delete($product->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_it_throws_exception_when_deleting_non_existing_product(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->repository->delete(999);
    }
}
