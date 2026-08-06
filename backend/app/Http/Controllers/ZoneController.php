<?php

namespace App\Http\Controllers;

use App\Models\Locality;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $zones = Zone::withCount('localities')
            ->withCount('contacts')
            ->orderBy('name')
            ->get();

        return response()->json($zones);
    }

    public function localities(Request $request): JsonResponse
    {
        $query = Locality::with('zone');

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('partido', 'like', "%{$search}%");
            });
        }

        if ($request->has('zone_id') && $request->zone_id) {
            $query->where('zone_id', $request->zone_id);
        }

        $limit = min((int) $request->get('limit', 50), 200);

        return response()->json($query->orderBy('name')->limit($limit)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:zones,name',
            'color' => 'nullable|string|max:20',
        ]);

        $zone = Zone::create($validated);

        return response()->json($zone, 201);
    }

    public function update(Request $request, Zone $zone): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:zones,name,' . $zone->id,
            'color' => 'nullable|string|max:20',
        ]);

        $zone->update($validated);

        return response()->json($zone);
    }

    public function destroy(Request $request, Zone $zone): JsonResponse
    {
        $this->authorizeAdmin($request);

        if ($zone->contacts()->exists()) {
            return response()->json(['message' => 'No se puede eliminar la zona porque tiene contactos asociados'], 422);
        }

        $zone->delete();

        return response()->json(['message' => 'Zone deleted']);
    }

    public function storeLocality(Request $request, Zone $zone): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'partido' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:20',
        ]);

        $locality = $zone->localities()->create($validated);

        return response()->json($locality, 201);
    }

    public function updateLocality(Request $request, Locality $locality): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'partido' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:20',
            'zone_id' => 'nullable|exists:zones,id',
        ]);

        $locality->update($validated);

        return response()->json($locality->load('zone'));
    }

    public function destroyLocality(Request $request, Locality $locality): JsonResponse
    {
        $this->authorizeAdmin($request);

        $locality->delete();

        return response()->json(['message' => 'Locality deleted']);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Solo el administrador puede modificar zonas');
    }
}
