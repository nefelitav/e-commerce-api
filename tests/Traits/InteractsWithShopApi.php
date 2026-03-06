<?php

namespace Tests\Traits;

use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\CatalogFixture;
use Tests\Fixtures\OrderFixture;
use Tests\Fixtures\UserFixture;

/**
 * Reusable API interaction helpers for placing orders, creating products,
 * sending webhooks, etc. Use in any test class that extends TestCase.
 *
 * @mixin \Tests\TestCase
 */
trait InteractsWithShopApi
{
    /**
     * Create a customer, create a product with stock, and place an order via the API.
     *
     * @return array{customer: UserModel, product: ProductModel, orderId: int, response: TestResponse<Response>}
     */
    protected function placeOrder(
        int $stock = 10,
        float $price = 100.00,
        int $quantity = 1,
        ?UserModel $customer = null,
    ): array {
        $customer ??= UserFixture::customer();
        ['product' => $product] = CatalogFixture::simpleProductInCategory($price, $stock);

        $this->actingAs($customer);

        $response = $this->postJson(
            route('v1.orders.store'),
            OrderFixture::payload($product, $quantity),
        );

        $response->assertStatus(Response::HTTP_CREATED);

        return [
            'customer' => $customer,
            'product' => $product,
            'orderId' => $response->json('data.id'),
            'response' => $response,
        ];
    }

    /**
     * Create an admin and a product via the API (not factory — full E2E through controller).
     *
     * @return array{admin: UserModel, productId: int, categoryId: int, response: TestResponse<Response>}
     */
    protected function createProductViaApi(
        string $name = 'Test Product',
        float $price = 49.99,
        int $quantity = 100,
        ?int $categoryId = null,
        ?UserModel $admin = null,
    ): array {
        $admin ??= UserFixture::admin();
        $this->actingAs($admin);

        if ($categoryId === null) {
            $catResponse = $this->postJson(route('v1.categories.store'), [
                'name' => 'Test Category',
                'description' => 'Auto-created category',
                'parent_id' => null,
            ]);
            $catResponse->assertStatus(Response::HTTP_CREATED);
            $categoryId = $catResponse->json('data.id');
        }

        $response = $this->postJson(route('v1.products.store'), [
            'name' => $name,
            'description' => "Description for {$name}",
            'price' => $price,
            'quantity' => $quantity,
            'category_id' => $categoryId,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        return [
            'admin' => $admin,
            'productId' => $response->json('data.id'),
            'categoryId' => $categoryId,
            'response' => $response,
        ];
    }

    /**
     * Send a payment webhook for an order.
     *
     * @return TestResponse<Response>
     */
    protected function payOrderViaWebhook(int $orderId, string $reference = 'pay_test'): TestResponse
    {
        return $this->postJson(
            route('v1.webhooks.payments'),
            OrderFixture::webhookPayload($orderId, $reference),
        );
    }

    /**
     * Send a signed payment webhook for an order.
     *
     * @return TestResponse<Response>
     */
    protected function payOrderViaSignedWebhook(int $orderId, string $secret, string $reference = 'pay_signed'): TestResponse
    {
        $signed = OrderFixture::signedWebhookPayload($orderId, $secret, $reference);

        return $this->postJson(
            route('v1.webhooks.payments'),
            $signed['payload'],
            $signed['headers'],
        );
    }

    /**
     * Transition an order to a new status via the update API.
     *
     * @return TestResponse<Response>
     */
    protected function updateOrderStatus(int $orderId, string $status, ProductModel $product, int $quantity = 1): TestResponse
    {
        return $this->putJson(route('v1.orders.update', $orderId), [
            'status' => $status,
            'total_price' => $product->price * $quantity,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                ],
            ],
        ]);
    }
}
