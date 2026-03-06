<?php

namespace Tests\Security;

use App\Models\Category\CategoryModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class MassAssignmentAndDataExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_creation_ignores_unexpected_fields(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $response = $this->postJson(route('v1.products.store'), [
            'name' => 'Safe Product',
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => $category->id,
            'id' => 999,
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $productId = $response->json('data.id');
        $this->assertNotEquals(999, $productId);
    }

    public function test_order_creation_ignores_unexpected_fields(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $response = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
            'id' => 999,
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $orderId = $response->json('data.id');
        $this->assertNotEquals(999, $orderId);
    }

    public function test_category_creation_ignores_unexpected_fields(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->postJson(route('v1.categories.store'), [
            'name' => 'Safe Category',
            'description' => 'Normal category',
            'parent_id' => null,
            'id' => 999,
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $categoryId = $response->json('data.id');
        $this->assertNotEquals(999, $categoryId);
    }

    public function test_product_response_does_not_expose_sensitive_data(): void
    {
        $product = ProductModel::factory()->create();

        $response = $this->getJson(route('v1.products.show', $product->id));
        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json('data');

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
        $this->assertArrayNotHasKey('email_verified_at', $data);
    }

    public function test_order_response_does_not_expose_user_password(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $orderResponse = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);

        $orderResponse->assertStatus(Response::HTTP_CREATED);
        $orderId = $orderResponse->json('data.id');

        $showResponse = $this->getJson(route('v1.orders.show', $orderId));
        $showResponse->assertStatus(Response::HTTP_OK);

        $responseBody = $showResponse->getContent();
        $this->assertStringNotContainsString('password', $responseBody ?: '');
        $this->assertStringNotContainsString('remember_token', $responseBody ?: '');
    }

    public function test_error_response_does_not_expose_stack_traces_in_production(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/products/99999');

        $responseBody = $response->getContent() ?: '';

        $this->assertStringNotContainsString('vendor/', $responseBody);
        $this->assertStringNotContainsString('#0 ', $responseBody);
        $this->assertStringNotContainsString('Stack trace', $responseBody);

        $responseData = $response->json();
        $this->assertArrayNotHasKey('trace', $responseData);
        $this->assertArrayNotHasKey('file', $responseData);
        $this->assertArrayNotHasKey('line', $responseData);
    }

    public function test_nonexistent_route_returns_proper_error(): void
    {
        $response = $this->getJson('/api/v1/nonexistent-endpoint');

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_NOT_FOUND, Response::HTTP_METHOD_NOT_ALLOWED],
        );
    }

    public function test_oversized_payload_is_handled(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $category = CategoryModel::factory()->create();

        $response = $this->postJson(route('v1.products.store'), [
            'name' => 'Oversized',
            'description' => str_repeat('A', 5001),
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => $category->id,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_order_user_id_cannot_be_spoofed_by_regular_user(): void
    {
        $userA = UserModel::factory()->create();
        $userB = UserModel::factory()->create();

        $this->actingAs($userA);

        $product = ProductModel::factory()->create(['quantity' => 10]);

        $response = $this->postJson(route('v1.orders.store'), [
            'user_id' => $userB->id,
            'status' => 'pending',
            'total_price' => $product->price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'user_id' => $userA->id,
        ]);
    }
}
