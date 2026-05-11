<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantRequest;
use App\Http\Requests\UpdatePlantRequest;
use App\Models\Plant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $sortInput = $request->input('sort', 'created_at');
        $directionInput = $request->input('direction', 'desc');
        $perPage = (int) $request->input('per_page', 15);

        $allowedSorts = ['created_at', 'updated_at', 'name', 'scientific_name'];

        $sortColumns = is_array($sortInput)
            ? $sortInput
            : explode(',', (string) $sortInput);
        $sortColumns = array_values(array_filter(
            array_map(fn ($s) => trim((string) $s), $sortColumns),
            fn (string $s): bool => $s !== ''
        ));

        $sortColumns = array_values(array_filter(
            $sortColumns,
            fn (string $column): bool => in_array($column, $allowedSorts, true)
        ));

        if ($sortColumns === []) {
            $sortColumns = ['created_at'];
        }

        $directionParts = is_array($directionInput)
            ? $directionInput
            : explode(',', (string) $directionInput);
        $directionParts = array_values(array_map(
            fn ($d) => trim((string) $d),
            $directionParts
        ));

        $directions = [];
        foreach ($sortColumns as $i => $_) {
            $raw = $directionParts[$i] ?? $directionParts[0] ?? 'desc';
            $dir = strtolower((string) $raw);
            $directions[] = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';
        }

        $perPage = max(1, min(100, $perPage));

        $this->authorize('viewAny', Plant::class);

        $query = Plant::query()
            ->where('user_id', $request->user()->id)
            ->when(filled($search), function ($query) use ($search) {
                $escaped = str_replace('\\', '\\\\', (string) $search);
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $escaped);
                $pattern = '%'.$escaped.'%';
                $grammar = $query->getGrammar();
                $query->where(function ($q) use ($pattern, $grammar) {
                    foreach (['name', 'scientific_name', 'description'] as $column) {
                        $wrapped = $grammar->wrap($column);
                        $q->orWhereRaw("{$wrapped} LIKE ? ESCAPE ?", [$pattern, '\\']);
                    }
                });
            });

        foreach ($sortColumns as $i => $column) {
            $query->orderBy($column, $directions[$i]);
        }

        $plants = $query->paginate($perPage);

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

    public function destroy(Plant $plant): Response
    {
        $this->authorize('delete', $plant);

        $plant->delete();

        return response()->noContent();
    }
}
