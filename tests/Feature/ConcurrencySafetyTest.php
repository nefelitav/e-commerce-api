<?php

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Exceptions\CategoryAlreadyExistsException;
use App\Models\Coupon\CouponModel;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use App\Models\ReturnRequest\ReturnRequestModel;
use App\Repositories\Category\CategoryRepositoryInterface;
use App\Services\AuditLogger;
use App\Services\Category\CategoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\CatalogFixture;
use Tests\Fixtures\OrderFixture;
use Tests\Fixtures\UserFixture;
use Tests\TestCase;
use Tests\Traits\InteractsWithShopApi;

class ConcurrencySafetyTest extends TestCase
{
    use InteractsWithShopApi;
    use RefreshDatabase;

    // ---- idempotent order creation ------------------------------------------------

    public function test_retrying_order_creation_with_same_idempotency_key_returns_original_order(): void
    {
        $customer = UserFixture::customer();
        ['product' => $product] = CatalogFixture::simpleProductInCategory(10.00, 10);
        $this->actingAs($customer);
        $payload = OrderFixture::payload($product, 2);

        $first = $this->postJson(route('v1.orders.store'), $payload, ['Idempotency-Key' => 'abc-123']);
        $second = $this->postJson(route('v1.orders.store'), $payload, ['Idempotency-Key' => 'abc-123']);

        $first->assertStatus(Response::HTTP_CREATED);
        $second->assertStatus(Response::HTTP_CREATED);
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, OrderModel::query()->count());
        $this->assertSame(8, $product->fresh()->quantity, 'stock must be taken only once');
    }

    public function test_different_idempotency_keys_create_separate_orders(): void
    {
        $customer = UserFixture::customer();
        ['product' => $product] = CatalogFixture::simpleProductInCategory(10.00, 10);
        $this->actingAs($customer);

        $this->postJson(route('v1.orders.store'), OrderFixture::payload($product), ['Idempotency-Key' => 'a'])->assertCreated();
        $this->postJson(route('v1.orders.store'), OrderFixture::payload($product), ['Idempotency-Key' => 'b'])->assertCreated();

        $this->assertSame(2, OrderModel::query()->count());
    }

    // ---- stock / deadlock avoidance -----------------------------------------------

    public function test_order_items_are_locked_in_product_id_order(): void
    {
        $customer = UserFixture::customer();
        ['product' => $low] = CatalogFixture::simpleProductInCategory(5.00, 10);
        ['product' => $high] = CatalogFixture::simpleProductInCategory(5.00, 10);
        $this->assertLessThan($high->id, $low->id);

        $locked = [];
        $repository = $this->app->make(\App\Repositories\Product\ProductRepositoryInterface::class);
        $spy = $this->createMock(\App\Repositories\Product\ProductRepositoryInterface::class);
        $spy->method('findByIdForUpdate')->willReturnCallback(
            function (int $id) use (&$locked, $repository) {
                $locked[] = $id;

                return $repository->findByIdForUpdate($id);
            },
        );
        $this->app->instance(\App\Repositories\Product\ProductRepositoryInterface::class, $spy);

        $this->actingAs($customer);
        // Request lists the higher id first.
        $this->postJson(route('v1.orders.store'), OrderFixture::multiItemPayload([
            ['product' => $high, 'quantity' => 1],
            ['product' => $low, 'quantity' => 1],
        ]))->assertCreated();

        $this->assertSame([$low->id, $high->id], $locked);
    }

    // ---- coupons ------------------------------------------------------------------

    public function test_order_with_coupon_consumes_a_use_and_records_discount(): void
    {
        $coupon = $this->coupon(['max_uses' => 5]);
        ['product' => $product] = CatalogFixture::simpleProductInCategory(100.00, 10);
        $this->actingAs(UserFixture::customer());

        $response = $this->postJson(route('v1.orders.store'), [
            ...OrderFixture::payload($product),
            'coupon_code' => $coupon->code,
        ]);

        $response->assertCreated();
        $this->assertSame(10.0, (float) $response->json('data.discount_amount'));
        $this->assertSame(1, $coupon->fresh()->times_used);
    }

    public function test_coupon_cannot_be_used_beyond_max_uses(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);
        ['product' => $product] = CatalogFixture::simpleProductInCategory(100.00, 10);
        $this->actingAs(UserFixture::customer());
        $payload = [...OrderFixture::payload($product), 'coupon_code' => $coupon->code];

        $this->postJson(route('v1.orders.store'), $payload)->assertCreated();
        $second = $this->postJson(route('v1.orders.store'), $payload);

        $second->assertStatus(Response::HTTP_BAD_REQUEST);
        $this->assertSame(1, $coupon->fresh()->times_used);
        $this->assertSame(9, $product->fresh()->quantity, 'failed order must roll back its stock change');
    }

    public function test_cancelling_an_order_releases_its_coupon_use(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);
        ['product' => $product] = CatalogFixture::simpleProductInCategory(100.00, 10);
        $this->actingAs(UserFixture::customer());

        $orderId = $this->postJson(route('v1.orders.store'), [
            ...OrderFixture::payload($product),
            'coupon_code' => $coupon->code,
        ])->json('data.id');

        $this->updateOrderStatus($orderId, OrderStatus::Cancelled->value, $product)->assertOk();

        $this->assertSame(0, $coupon->fresh()->times_used);
    }

    // ---- double stock restore -----------------------------------------------------

    public function test_cancelling_twice_restores_stock_only_once(): void
    {
        ['product' => $product, 'orderId' => $orderId] = $this->placeOrder(stock: 10, quantity: 3);
        $this->payOrderViaWebhook($orderId)->assertOk();
        $this->assertSame(7, $product->fresh()->quantity);

        $this->updateOrderStatus($orderId, OrderStatus::Cancelled->value, $product, 3)->assertOk();
        $this->updateOrderStatus($orderId, OrderStatus::Cancelled->value, $product, 3)->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_deleting_a_cancelled_order_does_not_restore_stock_again(): void
    {
        ['product' => $product, 'orderId' => $orderId] = $this->placeOrder(stock: 10, quantity: 3);
        $this->payOrderViaWebhook($orderId)->assertOk();
        $this->updateOrderStatus($orderId, OrderStatus::Cancelled->value, $product, 3)->assertOk();
        $this->assertSame(10, $product->fresh()->quantity);

        $this->actingAs(UserFixture::admin());
        $this->deleteJson(route('v1.orders.destroy', $orderId))->assertNoContent();

        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_approving_a_return_request_twice_restores_stock_only_once(): void
    {
        ['product' => $product, 'orderId' => $orderId, 'customer' => $customer] = $this->placeOrder(stock: 10, quantity: 3);
        $this->payOrderViaWebhook($orderId)->assertOk();

        $this->actingAs($customer);
        $returnId = $this->postJson(route('v1.return-requests.store'), [
            'order_id' => $orderId,
            'reason' => 'Changed my mind',
        ])->assertCreated()->json('data.id');

        $this->actingAs(UserFixture::admin());
        $this->postJson(route('v1.return-requests.approve', $returnId))->assertOk();
        $this->postJson(route('v1.return-requests.approve', $returnId))->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_approving_a_return_request_after_the_order_was_cancelled_is_rejected(): void
    {
        ['product' => $product, 'orderId' => $orderId, 'customer' => $customer] = $this->placeOrder(stock: 10, quantity: 3);
        $this->payOrderViaWebhook($orderId)->assertOk();

        $this->actingAs($customer);
        $returnId = $this->postJson(route('v1.return-requests.store'), [
            'order_id' => $orderId,
            'reason' => 'Changed my mind',
        ])->assertCreated()->json('data.id');

        // The order is cancelled (stock restored) before the admin gets to the return request.
        $this->updateOrderStatus($orderId, OrderStatus::Cancelled->value, $product, 3)->assertOk();
        $this->assertSame(10, $product->fresh()->quantity);

        $this->actingAs(UserFixture::admin());
        $this->postJson(route('v1.return-requests.approve', $returnId))->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertSame(10, $product->fresh()->quantity);
        $this->assertSame(OrderStatus::Cancelled, OrderModel::query()->findOrFail($orderId)->status);
    }

    public function test_second_active_return_request_for_an_order_is_rejected(): void
    {
        ['orderId' => $orderId, 'customer' => $customer] = $this->placeOrder();
        $this->payOrderViaWebhook($orderId)->assertOk();
        $this->actingAs($customer);
        $payload = ['order_id' => $orderId, 'reason' => 'Broken'];

        $this->postJson(route('v1.return-requests.store'), $payload)->assertCreated();
        $this->postJson(route('v1.return-requests.store'), $payload)->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertSame(1, ReturnRequestModel::query()->count());
    }

    // ---- webhooks -----------------------------------------------------------------

    public function test_duplicate_payment_webhook_is_rejected_after_the_first(): void
    {
        ['orderId' => $orderId] = $this->placeOrder();

        $this->payOrderViaWebhook($orderId)->assertOk();
        $this->payOrderViaWebhook($orderId)->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    // ---- optimistic locking on products -------------------------------------------

    public function test_product_update_with_stale_version_is_rejected(): void
    {
        ['productId' => $productId, 'categoryId' => $categoryId] = $this->createProductViaApi(quantity: 10);
        $version = ProductModel::query()->findOrFail($productId)->version;

        // A sale lands after the admin loaded the product.
        $customer = UserFixture::customer();
        $this->actingAs($customer);
        $this->postJson(route('v1.orders.store'), OrderFixture::payload(ProductModel::query()->findOrFail($productId)))->assertCreated();

        $this->actingAs(UserFixture::admin());
        $response = $this->putJson(route('v1.products.update', $productId), $this->productPayload($categoryId, 10, $version));

        $response->assertStatus(Response::HTTP_CONFLICT);
        $this->assertSame(9, ProductModel::query()->findOrFail($productId)->quantity, 'the sale must not be overwritten');
    }

    public function test_product_update_with_current_version_succeeds_and_bumps_version(): void
    {
        ['productId' => $productId, 'categoryId' => $categoryId] = $this->createProductViaApi(quantity: 10);
        $version = ProductModel::query()->findOrFail($productId)->version;

        $response = $this->putJson(route('v1.products.update', $productId), $this->productPayload($categoryId, 25, $version));

        $response->assertOk();
        $this->assertSame($version + 1, $response->json('data.version'));
        $this->assertSame(25, ProductModel::query()->findOrFail($productId)->quantity);
    }

    public function test_product_update_without_version_is_still_allowed(): void
    {
        ['productId' => $productId, 'categoryId' => $categoryId] = $this->createProductViaApi(quantity: 10);

        $this->putJson(route('v1.products.update', $productId), $this->productPayload($categoryId, 12, null))->assertOk();
    }

    // ---- lost races on unique keys ------------------------------------------------

    public function test_losing_a_category_name_race_maps_to_already_exists(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('persist')->willThrowException(
            new UniqueConstraintViolationException('pgsql', 'insert', [], new \Exception('duplicate key')),
        );
        $service = new CategoryService($repository, new AuditLogger);

        $this->expectException(CategoryAlreadyExistsException::class);
        $service->createCategory(new \App\Dto\Category\UnpersistedCategory('Dup', null, null));
    }

    public function test_unexpected_unique_violation_returns_409_without_leaking_sql(): void
    {
        // Exercises the global safety net for races no service-level mapping covers.
        \Illuminate\Support\Facades\Route::get('/__unique-violation', static function (): never {
            throw new UniqueConstraintViolationException('pgsql', 'insert into secrets', [], new \Exception('SQLSTATE[23505]'));
        });

        $response = $this->getJson('/__unique-violation');

        $response->assertStatus(Response::HTTP_CONFLICT);
        $this->assertStringNotContainsString('secrets', (string) $response->getContent());
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }

    public function test_creating_a_coupon_returns_it_with_default_usage_and_rejects_a_duplicate_code(): void
    {
        $this->actingAs(UserFixture::admin());
        $payload = ['code' => 'NEW10', 'type' => 'percentage', 'value' => 10];

        $first = $this->postJson(route('v1.coupons.store'), $payload);
        $second = $this->postJson(route('v1.coupons.store'), $payload);

        $first->assertCreated();
        $this->assertSame(0, $first->json('data.times_used'));
        $second->assertStatus(Response::HTTP_BAD_REQUEST);
        $this->assertSame(1, CouponModel::query()->where('code', 'NEW10')->count());
    }

    // ---- helpers ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function coupon(array $overrides = []): CouponModel
    {
        return CouponModel::create([
            'code' => 'SAVE10',
            'type' => CouponType::Percentage->value,
            'value' => 10,
            'times_used' => 0,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(int $categoryId, int $quantity, ?int $version): array
    {
        return array_filter([
            'name' => 'Test Product',
            'description' => 'Description for Test Product',
            'price' => 49.99,
            'quantity' => $quantity,
            'category_id' => $categoryId,
            'version' => $version,
        ], static fn ($v) => $v !== null);
    }
}
