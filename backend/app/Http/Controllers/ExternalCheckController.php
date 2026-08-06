<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ExternalCheck;
use App\Services\ExternalSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalCheckController extends Controller
{
    /**
     * KPI para admin/supervisor: estado de la verificación contra GECROS.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSupervisor(), 403);

        $query = Contact::query()
            ->with(['externalCheck', 'plan', 'assignedTo'])
            ->whereNotNull('dni')
            ->where('is_archived', false);

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('dni', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($user->isSupervisor()) {
            $query->whereHas('assignedTo', function ($q) use ($user) {
                $q->where('supervisor_id', $user->id)
                  ->orWhere('id', $user->id);
            });
        }

        $total = (clone $query)->count();
        $found = (clone $query)->whereHas('externalCheck', fn ($q) => $q->where('status', 'found'))->count();
        $notFound = (clone $query)->whereHas('externalCheck', fn ($q) => $q->where('status', 'not_found'))->count();
        $errors = (clone $query)->whereHas('externalCheck', fn ($q) => $q->where('status', 'error'))->count();
        $unchecked = $total - $found - $notFound - $errors;

        $lastCheck = ExternalCheck::latest('checked_at')->value('checked_at');

        $rows = $query
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'summary' => [
                'total_contacts_with_dni' => $total,
                'found_in_gecros' => $found,
                'not_found' => $notFound,
                'errors' => $errors,
                'unchecked' => $unchecked,
                'last_check_at' => $lastCheck,
                'bridge_configured' => !empty(config('services.gecros.base_url')) && !empty(config('services.gecros.api_key')),
            ],
            'contacts' => $rows,
        ]);
    }

    /**
     * Refresca la verificación de un contacto contra GECROS.
     */
    public function refresh(Request $request, Contact $contact): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSupervisor(), 403);

        $result = app(ExternalSystemService::class)->checkAndRecord($contact);

        if ($result === null) {
            return response()->json([
                'status' => 'unconfigured',
                'message' => 'El puente GECROS no está configurado. Definí GECROS_BRIDGE_URL y GECROS_BRIDGE_KEY.',
            ]);
        }

        return response()->json($result);
    }
}
