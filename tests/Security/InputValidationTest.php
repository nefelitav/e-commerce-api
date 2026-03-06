<?php

namespace Tests\Security;

use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // SQL Injection attempts
    // ---------------------------------------------------------------

    public function test_sql_injection_in_product_name(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $this->postJson(route('v1.products.store'), [
            'name' => "'; DROP TABLE products; --",
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', [
            'name' => "'; DROP TABLE products; --",
        ]);
    }

    public function test_sql_injection_in_category_name(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $this->postJson(route('v1.categories.store'), [
            'name' => "1'; DROP TABLE categories; --",
            'description' => 'test',
            'parent_id' => null,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_sql_injection_in_product_description(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $this->postJson(route('v1.products.store'), [
            'name' => 'Normal Product',
            'description' => "'; SELECT * FROM users WHERE '1'='1",
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('products', ['name' => 'Normal Product']);
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
    // XSS (Cross-Site Scripting) attempts
    // ---------------------------------------------------------------

    public function test_xss_in_product_name(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $response = $this->postJson(route('v1.products.store'), [
            'name' => '<script>alert("xss")</script>',
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => $category->id,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        // JSON API responses use application/json content type, which prevents
        // browsers from interpreting inline scripts. The value is safely JSON-encoded.
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_xss_in_product_description(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $response = $this->postJson(route('v1.products.store'), [
            'name' => 'Safe Product',
            'description' => '<img src=x onerror=alert("xss")>',
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => $category->id,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    public function test_xss_in_category_name(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->postJson(route('v1.categories.store'), [
            'name' => '<script>document.cookie</script>',
            'description' => 'Normal description',
            'parent_id' => null,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        // JSON API responses use application/json content type, which prevents
        // browsers from interpreting inline scripts.
        $response->assertHeader('Content-Type', 'application/json');
    }

    // ---------------------------------------------------------------
    // Input boundary validation
    // ---------------------------------------------------------------

    public function test_product_name_exceeds_max_length(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

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
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $this->postJson(route('v1.products.store'), [
            'name' => 'Valid Product',
            'price' => 10.00,
            'quantity' => 5,
            'description' => str_repeat('A', 5001),
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('description');
    }

    public function test_product_price_negative_value(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $this->postJson(route('v1.products.store'), [
            'name' => 'Negative Price Product',
            'price' => -10.00,
            'quantity' => 5,
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('price');
    }

    public function test_product_price_zero_value(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $this->postJson(route('v1.products.store'), [
            'name' => 'Zero Price Product',
            'price' => 0,
            'quantity' => 5,
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('price');
    }

    public function test_product_quantity_negative_value(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $this->postJson(route('v1.products.store'), [
            'name' => 'Negative Quantity Product',
            'price' => 10.00,
            'quantity' => -1,
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('quantity');
    }

    public function test_product_price_non_numeric(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $this->postJson(route('v1.products.store'), [
            'name' => 'Bad Price Product',
            'price' => 'not-a-number',
            'quantity' => 5,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('price');
    }

    public function test_product_quantity_non_integer(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $this->postJson(route('v1.products.store'), [
            'name' => 'Bad Quantity Product',
            'price' => 10.00,
            'quantity' => 'five',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('quantity');
    }

    public function test_product_with_nonexistent_category_id(): void
    {
        $admin = UserModel::factory()->admin()->create();
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
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 100,
            'items' => [
                ['product_id' => 99999, 'quantity' => 1, 'unit_price' => 100],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_order_with_zero_quantity(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 0,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 0, 'unit_price' => 10],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_order_with_negative_quantity(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 0,
            'items' => [
                ['product_id' => $product->id, 'quantity' => -5, 'unit_price' => 10],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_order_with_excessive_quantity(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 100000,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10001, 'unit_price' => 10],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_order_with_invalid_status(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $this->postJson(route('v1.orders.store'), [
            'status' => 'invalid_status',
            'total_price' => 10,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('status');
    }

    public function test_order_with_empty_items_array(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 10,
            'items' => [],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('items');
    }

    public function test_order_with_missing_required_fields(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('v1.orders.store'), [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['status', 'total_price', 'items']);
    }

    public function test_category_name_exceeds_max_length(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $this->postJson(route('v1.categories.store'), [
            'name' => str_repeat('X', 256),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('name');
    }

    public function test_category_with_nonexistent_parent_id(): void
    {
        $admin = UserModel::factory()->admin()->create();
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
        $admin = UserModel::factory()->admin()->create();
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

        // The server should not return a 2xx success for malformed JSON
        $this->assertFalse(
            $response->isSuccessful(),
            "Server should not return a success status for malformed JSON (got {$response->getStatusCode()})",
        );
    }

    // ---------------------------------------------------------------
    // Type confusion / type coercion attacks
    // ---------------------------------------------------------------

    public function test_product_id_as_string_in_order(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 100,
            'items' => [
                ['product_id' => 'abc', 'quantity' => 1, 'unit_price' => 100],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_boolean_values_in_numeric_fields(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $this->postJson(route('v1.products.store'), [
            'name' => 'Boolean Product',
            'price' => true,
            'quantity' => false,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_array_value_in_string_field(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $this->postJson(route('v1.products.store'), [
            'name' => ['an', 'array'],
            'price' => 10,
            'quantity' => 5,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('name');
    }

    public function test_null_required_fields(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $this->postJson(route('v1.products.store'), [
            'name' => null,
            'price' => null,
            'quantity' => null,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
