<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LookupRequest;
use App\Http\Resources\ItemLookupResource;
use App\Services\StockService;

class LookupController extends Controller
{
    /**
     * Look up an item
     *
     * The call a barcode scanner makes: find an item by barcode (or item code) and get its stock in every warehouse.
     *
     * Answers 404 with `code: not_found` when nothing matches.
     */
    public function __invoke(LookupRequest $request, StockService $stock): ItemLookupResource
    {
        $code = $request->validated('code');
        $product = $stock->findProduct($code)->load('stockLevels.warehouse');

        return new ItemLookupResource($product, $code);
    }
}
