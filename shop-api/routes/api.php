<?php

use App\Http\Controllers\Category\ListCategoriesController;
use Illuminate\Support\Facades\Route;

Route::apiResource('categories', ListCategoriesController::class);
