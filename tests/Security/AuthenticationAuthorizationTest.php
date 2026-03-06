<?php

namespace Tests\Security;

use App\Models\Category\CategoryModel;
use App\Models\Order\OrderItemModel;
use App\Models\Order\OrderModel;
use App\Models\Product\ProductModel;
use App\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthenticationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Authentication: unauthenticated access to protected endpoints
    // ---------------------------------------------------------------

    public function test_unauthenticated_user_cannot_create_order(): void
    {
        $response = $this->postJson(route('v1.orders.store'), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_unauthenticated_user_cannot_view_order(): void
    {
        $order = OrderModel::factory()->create();
        $response = $this->getJson(route('v1.orders.show', $order->id));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_unauthenticated_user_cannot_list_orders(): void
    {
        $response = $this->getJson(route('v1.orders.index'));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_unauthenticated_user_cannot_update_order(): void
    {
        $order = OrderModel::factory()->create();
        $response = $this->putJson(route('v1.orders.update', $order->id), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_unauthenticated_user_cannot_create_product(): void
    {
        $response = $this->postJson(route('v1.products.store'), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_unauthenticated_user_cannot_update_product(): void
    {
        $product = ProductModel::factory()->create();
        $response = $this->putJson(route('v1.products.update', $product->id), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_unauthenticated_user_cannot_delete_product(): void
    {
        $product = ProductModel::factory()->create();
        $response = $this->deleteJson(route('v1.products.destroy', $product->id));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_unauthenticated_user_cannot_create_category(): void
    {
        $response = $this->postJson(route('v1.categories.store'), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_unauthenticated_user_cannot_update_category(): void
    {
        $category = CategoryModel::factory()->create();
        $response = $this->putJson(route('v1.categories.update', $category->id), []);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_unauthenticated_user_cannot_delete_category(): void
    {
        $category = CategoryModel::factory()->create();
        $response = $this->deleteJson(route('v1.categories.destroy', $category->id));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_unauthenticated_user_cannot_delete_order(): void
    {
        $order = OrderModel::factory()->create();
        $response = $this->deleteJson(route('v1.orders.destroy', $order->id));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_unauthenticated_user_cannot_view_inventory_history(): void
    {
        $product = ProductModel::factory()->create();
        $response = $this->getJson(route('v1.products.inventory-history.index', $product->id));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    // ---------------------------------------------------------------
    // Public endpoints remain accessible without authentication
    // ---------------------------------------------------------------

    public function test_unauthenticated_user_can_list_products(): void
    {
        $this->getJson(route('v1.products.index'))
            ->assertStatus(Response::HTTP_OK);
    }

    public function test_unauthenticated_user_can_view_product(): void
    {
        $product = ProductModel::factory()->create();
        $this->getJson(route('v1.products.show', $product->id))
            ->assertStatus(Response::HTTP_OK);
    }

    public function test_unauthenticated_user_can_list_categories(): void
    {
        $this->getJson(route('v1.categories.index'))
            ->assertStatus(Response::HTTP_OK);
    }

    public function test_unauthenticated_user_can_view_category(): void
    {
        $category = CategoryModel::factory()->create();
        $this->getJson(route('v1.categories.show', $category->id))
            ->assertStatus(Response::HTTP_OK);
    }

    // ---------------------------------------------------------------
    // Authorization: regular users cannot access admin endpoints
    // ---------------------------------------------------------------

    public function test_regular_user_cannot_create_product(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('v1.products.store'), [
            'name' => 'Test',
            'price' => 10,
            'quantity' => 5,
        ])->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_regular_user_cannot_update_product(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create();

        $this->putJson(route('v1.products.update', $product->id), [
            'name' => 'Updated',
            'price' => 20,
            'quantity' => 10,
        ])->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_regular_user_cannot_delete_product(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create();

        $this->deleteJson(route('v1.products.destroy', $product->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_regular_user_cannot_create_category(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('v1.categories.store'), [
            'name' => 'Test Category',
        ])->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_regular_user_cannot_update_category(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $category = CategoryModel::factory()->create();

        $this->putJson(route('v1.categories.update', $category->id), [
            'name' => 'Updated',
        ])->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_regular_user_cannot_delete_category(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $category = CategoryModel::factory()->create();

        $this->deleteJson(route('v1.categories.destroy', $category->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_regular_user_cannot_delete_order(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $order = OrderModel::factory()->create(['user_id' => $user->id]);

        $this->deleteJson(route('v1.orders.destroy', $order->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_regular_user_cannot_view_inventory_history(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $product = ProductModel::factory()->create();

        $this->getJson(route('v1.products.inventory-history.index', $product->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    // ---------------------------------------------------------------
    // Cross-user access: users cannot access other users' orders
    // ---------------------------------------------------------------

    public function test_user_cannot_view_another_users_order(): void
    {
        $userA = UserModel::factory()->create();
        $userB = UserModel::factory()->create();

        $order = OrderModel::factory()->create(['user_id' => $userA->id]);

        $this->actingAs($userB);

        $this->getJson(route('v1.orders.show', $order->id))
            ->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function test_user_cannot_update_another_users_order(): void
    {
        $userA = UserModel::factory()->create();
        $userB = UserModel::factory()->create();

        $product = ProductModel::factory()->create();
        $order = OrderModel::factory()->create([
            'user_id' => $userA->id,
            'status' => 'pending',
        ]);
        OrderItemModel::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($userB);

        $this->putJson(route('v1.orders.update', $order->id), [
            'status' => 'cancelled',
            'total_price' => $order->total_price,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price],
            ],
        ])->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    // ---------------------------------------------------------------
    // Admin can access any user's resources
    // ---------------------------------------------------------------

    public function test_admin_can_view_any_users_order(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $user = UserModel::factory()->create();

        $order = OrderModel::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin);

        $this->getJson(route('v1.orders.show', $order->id))
            ->assertStatus(Response::HTTP_OK);
    }

    public function test_admin_can_delete_any_order(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $user = UserModel::factory()->create();

        $order = OrderModel::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin);

        $this->deleteJson(route('v1.orders.destroy', $order->id))
            ->assertStatus(Response::HTTP_NO_CONTENT);
    }
}
