<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The catalogue. Anyone signed in can read it; only admins can change it.
 */
class ProductController extends Controller
{
    /**
     * List products
     *
     * 15 a page by default (`per_page` up to 100).
     *
     * @queryParam search Part of the item code, name or barcode.
     * @queryParam barcode An exact barcode.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->search($request->query('search'))
            ->when($request->query('barcode'), fn ($q, $barcode) => $q->where('barcode', $barcode))
            ->orderBy('item_code')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /**
     * Add a product
     *
     * For admins.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::query()->create($request->validated());

        return (new ProductResource($product->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Show a product
     *
     * Addressed by its item code, like `BOND-A4`.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    /**
     * Update a product
     *
     * For admins. Changes the name, barcode or unit; the item code can't change.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    /**
     * Delete a product
     *
     * For admins. Refused (409) once it has stock or stock history, which would be lost.
     */
    public function destroy(Product $product): Response
    {
        if ($product->stockLevels()->exists() || $product->movements()->exists()) {
            throw ApiException::conflict("{$product->item_code} has stock or stock history and can't be deleted.");
        }

        $product->delete();

        return response()->noContent();
    }
}
