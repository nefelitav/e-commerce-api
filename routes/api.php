<?php

use App\Http\Controllers\Category\CreateCategoryController;
use App\Http\Controllers\Category\DeleteCategoryController;
use App\Http\Controllers\Category\GetCategoryController;
use App\Http\Controllers\Category\ListCategoriesController;
use App\Http\Controllers\Category\ListSubcategoriesController;
use App\Http\Controllers\Category\UpdateCategoryController;
use App\Http\Controllers\Product\CreateProductController;
use App\Http\Controllers\Product\DeleteProductController;
use App\Http\Controllers\Product\GetProductController;
use App\Http\Controllers\Product\ListCategoryProductsController;
use App\Http\Controllers\Product\ListProductsController;
use App\Http\Controllers\Product\UpdateProductController;
use App\Http\Controllers\InventoryHistory\ListInventoryHistoryController;
use App\Http\Controllers\Order\CreateOrderController;
use App\Http\Controllers\Order\DeleteOrderController;
use App\Http\Controllers\Order\GetOrderController;
use App\Http\Controllers\Order\ListOrdersController;
use App\Http\Controllers\Order\UpdateOrderController;
use App\Http\Controllers\Cart\CreateCartController;
use App\Http\Controllers\Cart\DeleteCartController;
use App\Http\Controllers\Cart\GetCartController;
use App\Http\Controllers\Cart\ListCartsController;
use App\Http\Controllers\Cart\UpdateCartController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('categories', [ListCategoriesController::class, 'index'])->name('categories.index');
    Route::get('categories/{id}', [GetCategoryController::class, 'show'])->name('categories.show');
    Route::post('categories', [CreateCategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{id}', [UpdateCategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{id}', [DeleteCategoryController::class, 'destroy'])->name('categories.destroy');
    Route::get('categories/{id}/subcategories', [ListSubcategoriesController::class, 'index'])->name('categories.subcategories');

    Route::get('products', [ListProductsController::class, 'index'])->name('products.index');
    Route::get('products/{id}', [GetProductController::class, 'show'])->name('products.show');
    Route::post('products', [CreateProductController::class, 'store'])->name('products.store');
    Route::put('products/{id}', [UpdateProductController::class, 'update'])->name('products.update');
    Route::delete('products/{id}', [DeleteProductController::class, 'destroy'])->name('products.destroy');
    Route::get('categories/{id}/products', [ListCategoryProductsController::class, 'index'])->name('categories.products.index');
    Route::get('products/{id}/inventory-history', [ListInventoryHistoryController::class, 'index'])->name('products.inventory-history.index');

    Route::get('orders', [ListOrdersController::class, 'index'])->name('orders.index');
    Route::get('orders/{id}', [GetOrderController::class, 'show'])->name('orders.show');
    Route::post('orders', [CreateOrderController::class, 'store'])->name('orders.store');
    Route::put('orders/{id}', [UpdateOrderController::class, 'update'])->name('orders.update');
    Route::delete('orders/{id}', [DeleteOrderController::class, 'destroy'])->name('orders.destroy');

    Route::get('carts', [ListCartsController::class, 'index'])->name('carts.index');
    Route::get('carts/{id}', [GetCartController::class, 'show'])->name('carts.show');
    Route::post('carts', [CreateCartController::class, 'store'])->name('carts.store');
    Route::put('carts/{id}', [UpdateCartController::class, 'update'])->name('carts.update');
    Route::delete('carts/{id}', [DeleteCartController::class, 'destroy'])->name('carts.destroy');
});
