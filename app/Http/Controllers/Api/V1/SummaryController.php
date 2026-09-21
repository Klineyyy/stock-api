<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Http\JsonResponse;

class SummaryController extends Controller
{
    /**
     * Dashboard numbers
     *
     * Headline figures: items, units on hand and how many rows are low.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'items' => Product::query()->count(),
            'units_on_hand' => (float) StockLevel::query()->sum('qty'),
            'low_stock' => StockLevel::query()->low()->count(),
        ]]);
    }
}
