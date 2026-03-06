<?php

use App\Http\Controllers\Api\V1\Category\CreateCategoryController;
use App\Http\Controllers\Api\V1\Category\DeleteCategoryController;
use App\Http\Controllers\Api\V1\Category\GetCategoryController;
use App\Http\Controllers\Api\V1\Category\ListCategoriesController;
use App\Http\Controllers\Api\V1\Category\ListSubcategoriesController;
use App\Http\Controllers\Api\V1\Category\UpdateCategoryController;
use App\Http\Controllers\Api\V1\Coupon\ApplyCouponController;
use App\Http\Controllers\Api\V1\Coupon\CreateCouponController;
use App\Http\Controllers\Api\V1\Coupon\DeleteCouponController;
use App\Http\Controllers\Api\V1\Coupon\GetCouponController;
use App\Http\Controllers\Api\V1\Coupon\ListCouponsController;
use App\Http\Controllers\Api\V1\Coupon\UpdateCouponController;
use App\Http\Controllers\Api\V1\InventoryHistory\ListInventoryHistoryController;
use App\Http\Controllers\Api\V1\Order\CreateOrderController;
use App\Http\Controllers\Api\V1\Order\DeleteOrderController;
use App\Http\Controllers\Api\V1\Order\GetOrderController;
use App\Http\Controllers\Api\V1\Order\ListOrdersController;
use App\Http\Controllers\Api\V1\Order\UpdateOrderController;
use App\Http\Controllers\Api\V1\Product\CreateProductController;
use App\Http\Controllers\Api\V1\Product\DeleteProductController;
use App\Http\Controllers\Api\V1\Product\GetProductController;
use App\Http\Controllers\Api\V1\Product\ListProductsController;
use App\Http\Controllers\Api\V1\Product\SearchProductsController;
use App\Http\Controllers\Api\V1\Product\UpdateProductController;
use App\Http\Controllers\Api\V1\ReturnRequest\ApproveReturnRequestController;
use App\Http\Controllers\Api\V1\ReturnRequest\CreateReturnRequestController;
use App\Http\Controllers\Api\V1\ReturnRequest\GetReturnRequestController;
use App\Http\Controllers\Api\V1\ReturnRequest\ListReturnRequestsController;
use App\Http\Controllers\Api\V1\ReturnRequest\RejectReturnRequestController;
use App\Http\Controllers\Api\V1\Webhook\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {

    // Public
    Route::get('categories', [ListCategoriesController::class, 'index'])->name('v1.categories.index');
    Route::get('categories/{id}', [GetCategoryController::class, 'show'])->name('v1.categories.show');
    Route::get('categories/{id}/subcategories', [ListSubcategoriesController::class, 'index'])->name('v1.categories.subcategories');

    Route::get('products', [ListProductsController::class, 'index'])->name('v1.products.index');
    Route::get('products/search', [SearchProductsController::class, 'search'])->name('v1.products.search');
    Route::get('products/{id}', [GetProductController::class, 'show'])->name('v1.products.show');

    // Webhooks (external provider callbacks)
    Route::post('webhooks/payments', PaymentWebhookController::class)->name('v1.webhooks.payments');

    Route::middleware('auth.required')->group(function () {
        // Orders
        Route::post('orders', [CreateOrderController::class, 'store'])->name('v1.orders.store');
        Route::get('orders/{id}', [GetOrderController::class, 'show'])->name('v1.orders.show');
        Route::get('orders', [ListOrdersController::class, 'index'])->name('v1.orders.index');
        Route::put('orders/{id}', [UpdateOrderController::class, 'update'])->name('v1.orders.update');

        // Coupons - Apply (authenticated users)
        Route::post('coupons/apply', [ApplyCouponController::class, 'apply'])->name('v1.coupons.apply');

        // Return Requests
        Route::post('return-requests', [CreateReturnRequestController::class, 'store'])->name('v1.return-requests.store');
        Route::get('return-requests', [ListReturnRequestsController::class, 'index'])->name('v1.return-requests.index');
        Route::get('return-requests/{id}', [GetReturnRequestController::class, 'show'])->name('v1.return-requests.show');
    });

    Route::middleware('admin.required')->group(function () {
        Route::post('categories', [CreateCategoryController::class, 'store'])->name('v1.categories.store');
        Route::put('categories/{id}', [UpdateCategoryController::class, 'update'])->name('v1.categories.update');
        Route::delete('categories/{id}', [DeleteCategoryController::class, 'destroy'])->name('v1.categories.destroy');

        Route::post('products', [CreateProductController::class, 'store'])->name('v1.products.store');
        Route::put('products/{id}', [UpdateProductController::class, 'update'])->name('v1.products.update');
        Route::delete('products/{id}', [DeleteProductController::class, 'destroy'])->name('v1.products.destroy');
        Route::get('products/{id}/inventory-history', [ListInventoryHistoryController::class, 'index'])->name('v1.products.inventory-history.index');

        Route::delete('orders/{id}', [DeleteOrderController::class, 'destroy'])->name('v1.orders.destroy');

        // Coupons - Admin CRUD
        Route::get('coupons', [ListCouponsController::class, 'index'])->name('v1.coupons.index');
        Route::get('coupons/{id}', [GetCouponController::class, 'show'])->name('v1.coupons.show');
        Route::post('coupons', [CreateCouponController::class, 'store'])->name('v1.coupons.store');
        Route::put('coupons/{id}', [UpdateCouponController::class, 'update'])->name('v1.coupons.update');
        Route::delete('coupons/{id}', [DeleteCouponController::class, 'destroy'])->name('v1.coupons.destroy');

        // Return Requests - Admin actions
        Route::post('return-requests/{id}/approve', [ApproveReturnRequestController::class, 'approve'])->name('v1.return-requests.approve');
        Route::post('return-requests/{id}/reject', [RejectReturnRequestController::class, 'reject'])->name('v1.return-requests.reject');
    });
});
