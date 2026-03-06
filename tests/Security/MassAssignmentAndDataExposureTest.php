<?php

namespace Tests\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\CatalogFixture;
use Tests\Fixtures\OrderFixture;
use Tests\Fixtures\UserFixture;
use Tests\TestCase;

class MassAssignmentAndDataExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_creation_ignores_unexpected_fields(): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $category = CatalogFixture::category();

        $response = $this->postJson(route('v1.products.store'), [
            'name' => 'Safe Product',
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => $category->id,
            'id' => 999,
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertNotEquals(999, $response->json('data.id'));
    }

    public function test_order_creation_ignores_unexpected_fields(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $product = CatalogFixture::productWithStock(10);

        $payload = OrderFixture::payload($product, 1);
        $payload['id'] = 999;
        $payload['created_at'] = '2000-01-01 00:00:00';

        $response = $this->postJson(route('v1.orders.store'), $payload);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertNotEquals(999, $response->json('data.id'));
    }

    public function test_category_creation_ignores_unexpected_fields(): void
    {
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $response = $this->postJson(route('v1.categories.store'), [
            'name' => 'Safe Category',
            'description' => 'Normal category',
            'parent_id' => null,
            'id' => 999,
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertNotEquals(999, $response->json('data.id'));
    }

    public function test_product_response_does_not_expose_sensitive_data(): void
    {
        $product = CatalogFixture::product();

        $response = $this->getJson(route('v1.products.show', $product->id));
        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json('data');

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
        $this->assertArrayNotHasKey('email_verified_at', $data);
    }

    public function test_order_response_does_not_expose_user_password(): void
    {
        $user = UserFixture::customer();
        $this->actingAs($user);

        $product = CatalogFixture::productWithStock(10);

        $orderResponse = $this->postJson(
            route('v1.orders.store'),
            OrderFixture::payload($product, 1),
        );
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
        $admin = UserFixture::admin();
        $this->actingAs($admin);

        $category = CatalogFixture::category();

        $this->postJson(route('v1.products.store'), [
            'name' => 'Oversized',
            'description' => str_repeat('A', 5001),
            'price' => 10.00,
            'quantity' => 5,
            'category_id' => $category->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_order_user_id_cannot_be_spoofed_by_regular_user(): void
    {
        $userA = UserFixture::customer();
        $userB = UserFixture::customer();

        $this->actingAs($userA);

        $product = CatalogFixture::productWithStock(10);

        $payload = OrderFixture::payload($product, 1);
        $payload['user_id'] = $userB->id;

        $response = $this->postJson(route('v1.orders.store'), $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'user_id' => $userA->id,
        ]);
    }
}
