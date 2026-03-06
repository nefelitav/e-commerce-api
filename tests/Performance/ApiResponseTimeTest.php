<?php

namespace Tests\Performance;

use App\Models\Category\CategoryModel;
use App\Models\Order\OrderItemModel;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiResponseTimeTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_RESPONSE_TIME_MS = 500;

    private const MAX_WRITE_RESPONSE_TIME_MS = 1000;

    public function test_list_products_responds_within_threshold(): void
    {
        $category = CategoryModel::factory()->create();
        ProductModel::factory()->count(50)->create(['category_id' => $category->id]);

        $start = microtime(true);

        $response = $this->getJson(route('v1.products.index'));

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThan(
            self::MAX_RESPONSE_TIME_MS,
            $elapsed,
            "List products took {$elapsed}ms, exceeding threshold of ".self::MAX_RESPONSE_TIME_MS.'ms',
        );
    }

    public function test_get_single_product_responds_within_threshold(): void
    {
        $product = ProductModel::factory()->create();

        $start = microtime(true);

        $response = $this->getJson(route('v1.products.show', $product->id));

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThan(
            self::MAX_RESPONSE_TIME_MS,
            $elapsed,
            "Get product took {$elapsed}ms, exceeding threshold of ".self::MAX_RESPONSE_TIME_MS.'ms',
        );
    }

    public function test_list_categories_responds_within_threshold(): void
    {
        CategoryModel::factory()->count(30)->create();

        $start = microtime(true);

        $response = $this->getJson(route('v1.categories.index'));

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThan(
            self::MAX_RESPONSE_TIME_MS,
            $elapsed,
            "List categories took {$elapsed}ms, exceeding threshold of ".self::MAX_RESPONSE_TIME_MS.'ms',
        );
    }

    public function test_get_single_category_responds_within_threshold(): void
    {
        $category = CategoryModel::factory()->create();

        $start = microtime(true);

        $response = $this->getJson(route('v1.categories.show', $category->id));

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThan(
            self::MAX_RESPONSE_TIME_MS,
            $elapsed,
            "Get category took {$elapsed}ms, exceeding threshold of ".self::MAX_RESPONSE_TIME_MS.'ms',
        );
    }

    public function test_list_subcategories_responds_within_threshold(): void
    {
        $parent = CategoryModel::factory()->create();
        CategoryModel::factory()->count(20)->create(['parent_id' => $parent->id]);

        $start = microtime(true);

        $response = $this->getJson(route('v1.categories.subcategories', $parent->id));

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThan(
            self::MAX_RESPONSE_TIME_MS,
            $elapsed,
            "List subcategories took {$elapsed}ms, exceeding threshold of ".self::MAX_RESPONSE_TIME_MS.'ms',
        );
    }

    public function test_list_orders_responds_within_threshold(): void
    {
        $user = UserModel::factory()->create();
        OrderModel::factory()->count(30)->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $start = microtime(true);

        $response = $this->getJson(route('v1.orders.index'));

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThan(
            self::MAX_RESPONSE_TIME_MS,
            $elapsed,
            "List orders took {$elapsed}ms, exceeding threshold of ".self::MAX_RESPONSE_TIME_MS.'ms',
        );
    }

    public function test_get_single_order_responds_within_threshold(): void
    {
        $user = UserModel::factory()->create();
        $order = OrderModel::factory()->create(['user_id' => $user->id]);
        OrderItemModel::factory()->count(5)->create(['order_id' => $order->id]);

        $this->actingAs($user);

        $start = microtime(true);

        $response = $this->getJson(route('v1.orders.show', $order->id));

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThan(
            self::MAX_RESPONSE_TIME_MS,
            $elapsed,
            "Get order took {$elapsed}ms, exceeding threshold of ".self::MAX_RESPONSE_TIME_MS.'ms',
        );
    }

    public function test_create_order_responds_within_threshold(): void
    {
        $user = UserModel::factory()->create();
        $product = ProductModel::factory()->create(['quantity' => 100, 'price' => 25.00]);

        $this->actingAs($user);

        $start = microtime(true);

        $response = $this->postJson(route('v1.orders.store'), [
            'status' => 'pending',
            'total_price' => 50.00,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 25.00],
            ],
        ]);

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertLessThan(
            self::MAX_WRITE_RESPONSE_TIME_MS,
            $elapsed,
            "Create order took {$elapsed}ms, exceeding threshold of ".self::MAX_WRITE_RESPONSE_TIME_MS.'ms',
        );
    }

    public function test_create_product_responds_within_threshold(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $category = CategoryModel::factory()->create();

        $this->actingAs($admin);

        $start = microtime(true);

        $response = $this->postJson(route('v1.products.store'), [
            'name' => 'Performance Test Product',
            'description' => 'Testing response time',
            'price' => 49.99,
            'quantity' => 100,
            'category_id' => $category->id,
        ]);

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertLessThan(
            self::MAX_WRITE_RESPONSE_TIME_MS,
            $elapsed,
            "Create product took {$elapsed}ms, exceeding threshold of ".self::MAX_WRITE_RESPONSE_TIME_MS.'ms',
        );
    }

    public function test_payment_webhook_responds_within_threshold(): void
    {
        $order = OrderModel::factory()->create(['status' => 'pending']);

        $start = microtime(true);

        $response = $this->postJson(route('v1.webhooks.payments'), [
            'order_id' => $order->id,
            'payment_reference' => 'pay_perf_test',
            'status' => 'paid',
        ]);

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThan(
            self::MAX_WRITE_RESPONSE_TIME_MS,
            $elapsed,
            "Payment webhook took {$elapsed}ms, exceeding threshold of ".self::MAX_WRITE_RESPONSE_TIME_MS.'ms',
        );
    }
}
