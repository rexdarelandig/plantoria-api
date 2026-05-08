<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantRequest;
use App\Http\Requests\UpdatePlantRequest;
use App\Models\Plant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $perPage = (int) $request->input('per_page', 15);

        $allowedSorts = ['created_at', 'updated_at', 'name', 'scientific_name', 'slug'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $sortDirection = in_array(strtolower((string) $sortDirection), ['asc', 'desc'], true)
            ? strtolower((string) $sortDirection)
            : 'desc';
        $perPage = max(1, min(100, $perPage));

        $this->authorize('viewAny', Plant::class);

        $plants = Plant::query()
            ->where('user_id', $request->user()->id)
            ->when(filled($search), function ($query) use ($search) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $search).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('scientific_name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderBy($sort, $sortDirection)
            ->paginate($perPage);

        return response()->json($plants);
    }

    public function store(StorePlantRequest $request): JsonResponse
    {
        $plant = Plant::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($plant, 201);
    }

    public function show(Plant $plant): JsonResponse
    {
        $this->authorize('view', $plant);

        return response()->json($plant);
    }

    public function update(UpdatePlantRequest $request, Plant $plant): JsonResponse
    {
        $plant->update($request->validated());

        return response()->json($plant);
    }

    public function destroy(Plant $plant): JsonResponse
    {
        $this->authorize('delete', $plant);

        $plant->delete();

        return response()->noContent();
    }
}
