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
        $search = $request->input('search');
        $sort = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $perPage = (int) $request->input('per_page', 15);

        $allowedSorts = ['created_at', 'updated_at', 'name'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $sortDirection = in_array(strtolower((string) $sortDirection), ['asc', 'desc'], true)
            ? strtolower((string) $sortDirection)
            : 'desc';
        $perPage = max(1, min(100, $perPage));

        $this->authorize('viewAny', Location::class);

        $locations = Location::query()
            ->where('user_id', $request->user()->id)
            ->when(filled($search), function ($query) use ($search) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $search).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderBy($sort, $sortDirection)
            ->paginate($perPage);

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
