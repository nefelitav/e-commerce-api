<?php

namespace Tests\Security;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Symfony\Component\HttpFoundation\Response;
use Tests\DataProviders\SecurityDataProvider;
use Tests\Fixtures\CatalogFixture;
use Tests\Fixtures\UserFixture;
use Tests\TestCase;

class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // SQL Injection attempts (data-provider driven)
    // ---------------------------------------------------------------

    #[DataProviderExternal(SecurityDataProvider::class, 'sqlInjectionPayloads')]
    public function test_sql_injection_in_product_name_is_safely_stored(string $payload): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $category = CatalogFixture::category();

        $this->postJson(route('v1.products.store'), [
            'name' => $payload,
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', ['name' => $payload]);
    }

    #[DataProviderExternal(SecurityDataProvider::class, 'sqlInjectionPayloads')]
    public function test_sql_injection_in_category_name_is_safely_stored(string $payload): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $this->postJson(route('v1.categories.store'), [
            'name' => $payload,
            'description' => 'test',
            'parent_id' => null,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_sql_injection_in_product_id_parameter(): void
    {
        $response = $this->getJson('/api/v1/products/1%20OR%201=1');

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_NOT_FOUND, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_BAD_REQUEST],
            'SQL injection in URL parameter should be rejected',
        );
    }

    public function test_sql_injection_in_category_id_parameter(): void
    {
        $response = $this->getJson('/api/v1/categories/1%20OR%201=1');

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_NOT_FOUND, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_BAD_REQUEST],
            'SQL injection in URL parameter should be rejected',
        );
    }

    // ---------------------------------------------------------------
    // XSS attempts (data-provider driven)
    // ---------------------------------------------------------------

    #[DataProviderExternal(SecurityDataProvider::class, 'xssPayloads')]
    public function test_xss_in_product_name_returns_json_content_type(string $payload): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $category = CatalogFixture::category();

        $response = $this->postJson(route('v1.products.store'), [
            'name' => $payload,
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => $category->id,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertHeader('Content-Type', 'application/json');
    }

    #[DataProviderExternal(SecurityDataProvider::class, 'xssPayloads')]
    public function test_xss_in_category_name_returns_json_content_type(string $payload): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $response = $this->postJson(route('v1.categories.store'), [
            'name' => $payload,
            'description' => 'Normal description',
            'parent_id' => null,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertHeader('Content-Type', 'application/json');
    }

    // ---------------------------------------------------------------
    // Invalid product prices (data-provider driven)
    // ---------------------------------------------------------------

    #[DataProviderExternal(SecurityDataProvider::class, 'invalidProductPrices')]
    public function test_product_rejects_invalid_price(mixed $price): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $category = CatalogFixture::category();

        $this->postJson(route('v1.products.store'), [
            'name' => 'Invalid Price Product',
            'price' => $price,
            'quantity' => 5,
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('price');
    }

    // ---------------------------------------------------------------
    // Invalid product quantities (data-provider driven)
    // ---------------------------------------------------------------

    #[DataProviderExternal(SecurityDataProvider::class, 'invalidProductQuantities')]
    public function test_product_rejects_invalid_quantity(mixed $quantity): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $category = CatalogFixture::category();

        $this->postJson(route('v1.products.store'), [
            'name' => 'Invalid Quantity Product',
            'price' => 10.00,
            'quantity' => $quantity,
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('quantity');
    }

    // ---------------------------------------------------------------
    // Input boundary validation
    // ---------------------------------------------------------------

    public function test_product_name_exceeds_max_length(): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $category = CatalogFixture::category();

        $this->postJson(route('v1.products.store'), [
            'name' => str_repeat('A', 256),
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('name');
    }

    public function test_product_description_exceeds_max_length(): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $category = CatalogFixture::category();

        $this->postJson(route('v1.products.store'), [
            'name' => 'Valid Product',
            'price' => 10.00,
            'quantity' => 5,
            'description' => str_repeat('A', 5001),
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('description');
    }

    public function test_product_with_nonexistent_category_id(): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $this->postJson(route('v1.products.store'), [
            'name' => 'Orphan Product',
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => 99999,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_order_with_nonexistent_product_id(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $this->postJson(route('v1.orders.store'), [
            'status' => OrderStatus::Pending->value,
            'total_price' => 100,
            'items' => [
                ['product_id' => 99999, 'quantity' => 1, 'unit_price' => 100],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_order_with_zero_quantity(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $product = CatalogFixture::productWithStock(10);

        $this->postJson(route('v1.orders.store'), [
            'status' => OrderStatus::Pending->value,
            'total_price' => 0,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 0, 'unit_price' => 10],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_order_with_negative_quantity(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $product = CatalogFixture::productWithStock(10);

        $this->postJson(route('v1.orders.store'), [
            'status' => OrderStatus::Pending->value,
            'total_price' => 0,
            'items' => [
                ['product_id' => $product->id, 'quantity' => -5, 'unit_price' => 10],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_order_with_excessive_quantity(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $product = CatalogFixture::productWithStock(10);

        $this->postJson(route('v1.orders.store'), [
            'status' => OrderStatus::Pending->value,
            'total_price' => 100000,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10001, 'unit_price' => 10],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('items.0.quantity');
    }

    // ---------------------------------------------------------------
    // Invalid order statuses (data-provider driven)
    // ---------------------------------------------------------------

    #[DataProviderExternal(SecurityDataProvider::class, 'invalidOrderStatuses')]
    public function test_order_rejects_invalid_status(string $status): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $product = CatalogFixture::productWithStock(10);

        $this->postJson(route('v1.orders.store'), [
            'status' => $status,
            'total_price' => 10,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('status');
    }

    public function test_order_with_empty_items_array(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $this->postJson(route('v1.orders.store'), [
            'status' => OrderStatus::Pending->value,
            'total_price' => 10,
            'items' => [],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('items');
    }

    public function test_order_with_missing_required_fields(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $this->postJson(route('v1.orders.store'), [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['status', 'total_price', 'items']);
    }

    public function test_category_name_exceeds_max_length(): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $this->postJson(route('v1.categories.store'), [
            'name' => str_repeat('X', 256),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('name');
    }

    public function test_category_with_nonexistent_parent_id(): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $this->postJson(route('v1.categories.store'), [
            'name' => 'Orphan Category',
            'parent_id' => 99999,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('parent_id');
    }

    // ---------------------------------------------------------------
    // Malformed JSON & content type
    // ---------------------------------------------------------------

    public function test_malformed_json_body_returns_error(): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $response = $this->call(
            'POST',
            route('v1.products.store'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            '{invalid json',
        );

        $this->assertFalse(
            $response->isSuccessful(),
            "Server should not return a success status for malformed JSON (got {$response->getStatusCode()})",
        );
    }

    // ---------------------------------------------------------------
    // Type confusion (data-provider driven)
    // ---------------------------------------------------------------

    #[DataProviderExternal(SecurityDataProvider::class, 'typeConfusionForNumericFields')]
    public function test_type_confusion_in_product_price(mixed $value): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $this->postJson(route('v1.products.store'), [
            'name' => 'Type Confusion Product',
            'price' => $value,
            'quantity' => 5,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[DataProviderExternal(SecurityDataProvider::class, 'typeConfusionForStringFields')]
    public function test_type_confusion_in_product_name(mixed $value): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $this->postJson(route('v1.products.store'), [
            'name' => $value,
            'price' => 10,
            'quantity' => 5,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_null_required_fields(): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $this->postJson(route('v1.products.store'), [
            'name' => null,
            'price' => null,
            'quantity' => null,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
