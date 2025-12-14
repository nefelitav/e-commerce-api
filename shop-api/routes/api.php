<?php

use App\Http\Controllers\Category\CreateCategoryController;
use App\Http\Controllers\Category\DeleteCategoryController;
use App\Http\Controllers\Category\GetCategoryController;
use App\Http\Controllers\Category\ListCategoriesController;
use App\Http\Controllers\Category\ListSubcategoriesController;
use App\Http\Controllers\Category\UpdateCategoryController;
use Illuminate\Support\Facades\Route;

Route::apiResource('list categories', ListCategoriesController::class);
Route::apiResource('list subcategories', ListSubcategoriesController::class);
Route::apiResource('get category', GetCategoryController::class);
Route::apiResource('create category', CreateCategoryController::class);
Route::apiResource('update category', UpdateCategoryController::class);
Route::apiResource('delete category', DeleteCategoryController::class);
