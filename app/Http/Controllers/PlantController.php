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
        $this->authorize('viewAny', Plant::class);

        $plants = Plant::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

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
