<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function index(Request $request, Contact $contact): JsonResponse
    {
        $activities = collect();

        $activities = $activities->merge(
            $contact->callLogs()->with('user')->get()->map(fn($l) => [
                'id' => 'call_' . $l->id,
                'type' => 'call',
                'title' => "Llamada {$l->direction}",
                'description' => $l->notes,
                'user' => $l->user->name,
                'status' => $l->status,
                'duration' => $l->duration,
                'created_at' => $l->called_at->toISOString(),
            ])
        );

        $activities = $activities->merge(
            $contact->tasks()->with('assignedTo')->get()->map(fn($t) => [
                'id' => 'task_' . $t->id,
                'type' => 'task',
                'title' => $t->title,
                'description' => $t->description,
                'user' => $t->assignedTo?->name,
                'status' => $t->status,
                'priority' => $t->priority,
                'created_at' => $t->created_at->toISOString(),
            ])
        );

        $activities = $activities->merge(
            $contact->notes()->with('user')->get()->map(fn($n) => [
                'id' => 'note_' . $n->id,
                'type' => 'note',
                'title' => 'Nota',
                'description' => $n->content,
                'user' => $n->user->name,
                'is_private' => $n->is_private,
                'created_at' => $n->created_at->toISOString(),
            ])
        );

        $activities = $activities->merge(
            $contact->visits()->with('user')->get()->map(fn($v) => [
                'id' => 'visit_' . $v->id,
                'type' => 'visit',
                'title' => $v->title ?? 'Visita',
                'description' => $v->summary,
                'user' => $v->user->name,
                'status' => $v->status,
                'created_at' => $v->created_at->toISOString(),
            ])
        );

        $activities = $activities->merge(
            $contact->conversations()->with('messages.sender')->get()->flatMap(fn($c) =>
                $c->messages->map(fn($m) => [
                    'id' => 'msg_' . $m->id,
                    'type' => 'message',
                    'title' => "Mensaje {$m->direction}",
                    'description' => $m->content,
                    'user' => $m->sender?->name ?? 'Contacto',
                    'channel' => $c->channel,
                    'is_read' => $m->is_read,
                    'created_at' => $m->created_at->toISOString(),
                ])
            )
        );

        $activities = $activities->merge(
            ActivityLog::where('contact_id', $contact->id)->with('user')->get()->map(fn($a) => [
                'id' => 'log_' . $a->id,
                'type' => 'activity',
                'title' => $a->action,
                'description' => $a->description,
                'user' => $a->user?->name,
                'created_at' => $a->created_at->toISOString(),
            ])
        );

        return response()->json(
            $activities->sortByDesc('created_at')->values()
        );
    }
}
