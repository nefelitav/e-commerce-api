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
});
