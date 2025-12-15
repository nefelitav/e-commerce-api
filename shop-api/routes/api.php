<?php

use App\Http\Controllers\Category\CreateCategoryController;
use App\Http\Controllers\Category\DeleteCategoryController;
use App\Http\Controllers\Category\GetCategoryController;
use App\Http\Controllers\Category\ListCategoriesController;
use App\Http\Controllers\Category\ListSubcategoriesController;
use App\Http\Controllers\Category\UpdateCategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('categories', [ListCategoriesController::class, 'index'])->name('categories.index');
    Route::get('categories/{id}', [GetCategoryController::class, 'show'])->name('categories.show');
    Route::post('categories', [CreateCategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{id}', [UpdateCategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{id}', [DeleteCategoryController::class, 'destroy'])->name('categories.destroy');
    Route::get('categories/{id}/subcategories', [ListSubcategoriesController::class, 'index'])->name('categories.subcategories');
});
