<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\MovementController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    // Public
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // Signed in
    Route::middleware('auth:api')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('lookup', LookupController::class);
        Route::get('summary', SummaryController::class);
        Route::get('warehouses', [WarehouseController::class, 'index']);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::get('stock', [StockController::class, 'index']);
        Route::get('stock/low', [StockController::class, 'low']);
        Route::get('movements', [MovementController::class, 'index']);

        // Staff and admins: change stock
        Route::post('stock/adjust', [StockController::class, 'adjust'])->middleware('can:adjust-stock');

        // Admins: change the catalogue
        Route::middleware('can:manage-catalog')->group(function () {
            Route::post('products', [ProductController::class, 'store']);
            Route::patch('products/{product}', [ProductController::class, 'update']);
            Route::delete('products/{product}', [ProductController::class, 'destroy']);
            Route::post('warehouses', [WarehouseController::class, 'store']);
            Route::put('stock/reorder', [StockController::class, 'reorder']);
        });
    });
});
