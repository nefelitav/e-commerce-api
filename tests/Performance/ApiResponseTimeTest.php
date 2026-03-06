<?php

namespace Tests\Performance;

use App\Models\Order\OrderItemModel;
use App\Models\Order\OrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\CatalogFixture;
use Tests\Fixtures\OrderFixture;
use Tests\Fixtures\UserFixture;
use Tests\TestCase;
use Tests\Traits\InteractsWithShopApi;
use Tests\Traits\MeasuresPerformance;

class ApiResponseTimeTest extends TestCase
{
    use InteractsWithShopApi;
    use MeasuresPerformance;
    use RefreshDatabase;

    private const MAX_READ_MS = 500;

    private const MAX_WRITE_MS = 1000;

    public function test_list_products_responds_within_threshold(): void
    {
        CatalogFixture::productsInCategory(50);

        $response = $this->assertGetRespondsWithinMs(
            route('v1.products.index'),
            self::MAX_READ_MS,
            'List products',
        );
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_get_single_product_responds_within_threshold(): void
    {
        $product = CatalogFixture::product();

        $response = $this->assertGetRespondsWithinMs(
            route('v1.products.show', $product->id),
            self::MAX_READ_MS,
            'Get product',
        );
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_list_categories_responds_within_threshold(): void
    {
        CatalogFixture::category(); // seed at least one
        for ($i = 0; $i < 29; $i++) {
            CatalogFixture::category();
        }

        $response = $this->assertGetRespondsWithinMs(
            route('v1.categories.index'),
            self::MAX_READ_MS,
            'List categories',
        );
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_get_single_category_responds_within_threshold(): void
    {
        $category = CatalogFixture::category();

        $response = $this->assertGetRespondsWithinMs(
            route('v1.categories.show', $category->id),
            self::MAX_READ_MS,
            'Get category',
        );
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_list_subcategories_responds_within_threshold(): void
    {
        ['parent' => $parent] = CatalogFixture::categoryHierarchy(20);

        $response = $this->assertGetRespondsWithinMs(
            route('v1.categories.subcategories', $parent->id),
            self::MAX_READ_MS,
            'List subcategories',
        );
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_list_orders_responds_within_threshold(): void
    {
        $user = UserFixture::customer();
        OrderModel::factory()->count(30)->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $response = $this->assertGetRespondsWithinMs(
            route('v1.orders.index'),
            self::MAX_READ_MS,
            'List orders',
        );
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_get_single_order_responds_within_threshold(): void
    {
        $user = UserFixture::customer();
        $order = OrderModel::factory()->create(['user_id' => $user->id]);
        OrderItemModel::factory()->count(5)->create(['order_id' => $order->id]);
        $this->actingAs($user);

        $response = $this->assertGetRespondsWithinMs(
            route('v1.orders.show', $order->id),
            self::MAX_READ_MS,
            'Get order',
        );
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_create_order_responds_within_threshold(): void
    {
        $user = UserFixture::customer();
        $product = CatalogFixture::productWithStock(100, 25.00);
        $this->actingAs($user);

        $response = $this->assertPostRespondsWithinMs(
            route('v1.orders.store'),
            OrderFixture::payload($product, 2),
            self::MAX_WRITE_MS,
            'Create order',
        );
        $response->assertStatus(Response::HTTP_CREATED);
    }

    public function test_create_product_responds_within_threshold(): void
    {
        $admin = UserFixture::admin();
        $category = CatalogFixture::category();
        $this->actingAs($admin);

        $response = $this->assertPostRespondsWithinMs(
            route('v1.products.store'),
            [
                'name' => 'Performance Test Product',
                'description' => 'Testing response time',
                'price' => 49.99,
                'quantity' => 100,
                'category_id' => $category->id,
            ],
            self::MAX_WRITE_MS,
            'Create product',
        );
        $response->assertStatus(Response::HTTP_CREATED);
    }

    public function test_payment_webhook_responds_within_threshold(): void
    {
        $order = OrderModel::factory()->create(['status' => 'pending']);

        $response = $this->assertPostRespondsWithinMs(
            route('v1.webhooks.payments'),
            OrderFixture::webhookPayload($order->id, 'pay_perf_test'),
            self::MAX_WRITE_MS,
            'Payment webhook',
        );
        $response->assertStatus(Response::HTTP_OK);
    }
}
