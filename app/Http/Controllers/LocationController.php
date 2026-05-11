<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Location::class);

        $locations = Location::query()
            ->where('user_id', $request->user()->id);

        return response()->json($locations);
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = Location::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($location, 201);
    }

    public function show(Location $location): JsonResponse
    {
        $this->authorize('view', $location);

        return response()->json($location);
    }

    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        $location->update($request->validated());

        return response()->json($location);
    }

    public function destroy(Location $location): JsonResponse
    {
        $this->authorize('delete', $location);

        $location->delete();

        return response()->noContent();
    }
}
