<?php

use App\Http\Controllers\Api\V1\Cart\CreateCartController;
use App\Http\Controllers\Api\V1\Cart\DeleteCartController;
use App\Http\Controllers\Api\V1\Cart\GetCartController;
use App\Http\Controllers\Api\V1\Cart\ListCartsController;
use App\Http\Controllers\Api\V1\Cart\UpdateCartController;
use App\Http\Controllers\Api\V1\Category\CreateCategoryController;
use App\Http\Controllers\Api\V1\Category\DeleteCategoryController;
use App\Http\Controllers\Api\V1\Category\GetCategoryController;
use App\Http\Controllers\Api\V1\Category\ListCategoriesController;
use App\Http\Controllers\Api\V1\Category\ListSubcategoriesController;
use App\Http\Controllers\Api\V1\Category\UpdateCategoryController;
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
use App\Http\Controllers\Api\V1\Product\UpdateProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {

    // Public
    Route::get('categories', [ListCategoriesController::class, 'index'])->name('v1.categories.index');
    Route::get('categories/{id}', [GetCategoryController::class, 'show'])->name('v1.categories.show');
    Route::get('categories/{id}/subcategories', [ListSubcategoriesController::class, 'index'])->name('v1.categories.subcategories');

    Route::get('products', [ListProductsController::class, 'index'])->name('v1.products.index');
    Route::get('products/{id}', [GetProductController::class, 'show'])->name('v1.products.show');

    Route::get('carts/{id}', [GetCartController::class, 'show'])->name('v1.carts.show');
    Route::post('carts', [CreateCartController::class, 'store'])->name('v1.carts.store');
    Route::put('carts/{id}', [UpdateCartController::class, 'update'])->name('v1.carts.update');
    Route::delete('carts/{id}', [DeleteCartController::class, 'destroy'])->name('v1.carts.destroy');

    Route::middleware('auth.required')->group(function () {
        Route::post('orders', [CreateOrderController::class, 'store'])->name('v1.orders.store');
        Route::get('orders/{id}', [GetOrderController::class, 'show'])->name('v1.orders.show');
        Route::get('orders', [ListOrdersController::class, 'index'])->name('v1.orders.index');
        Route::put('orders/{id}', [UpdateOrderController::class, 'update'])->name('v1.orders.update');
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

        Route::get('carts', [ListCartsController::class, 'index'])->name('v1.carts.index');
    });
});

