<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    /**
     * List warehouses
     *
     * Every warehouse, by name (handy for a picker in a scanner app).
     */
    public function index(): AnonymousResourceCollection
    {
        return WarehouseResource::collection(Warehouse::query()->orderBy('name')->get());
    }

    /**
     * Add a warehouse
     *
     * For admins.
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::query()->create($request->validated());

        return (new WarehouseResource($warehouse))->response()->setStatusCode(201);
    }
}
