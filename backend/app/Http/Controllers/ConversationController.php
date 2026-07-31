<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Conversation::with(['contact', 'assignedTo', 'messages' => function ($q) {
            $q->latest()->limit(1);
        }]);

        if ($request->user()->isSeller()) {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($request->user()->isSupervisor()) {
            $query->whereHas('assignedTo', function ($q) use ($request) {
                $q->where('supervisor_id', $request->user()->id)
                  ->orWhere('id', $request->user()->id);
            });
        }

        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $query->orderBy('last_message_at', 'desc');

        $perPage = $request->get('per_page', 20);
        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'channel' => 'required|in:whatsapp,email,facebook,instagram,messenger,call,visit,other',
            'subject' => 'nullable|string|max:255',
        ]);

        $validated['assigned_to'] = $request->user()->id;
        $validated['status'] = 'open';

        $conversation = Conversation::create($validated);

        return response()->json($conversation->load(['contact', 'assignedTo']), 201);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        return response()->json($conversation->load([
            'contact',
            'assignedTo',
            'messages.sender',
        ]));
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'type' => 'nullable|string|in:text,image,file,audio',
            'attachments' => 'nullable|array',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'direction' => 'outgoing',
            'content' => $validated['content'],
            'type' => $validated['type'] ?? 'text',
            'attachments' => $validated['attachments'] ?? [],
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json($message->load('sender'), 201);
    }

    public function close(Conversation $conversation): JsonResponse
    {
        $conversation->update(['status' => 'closed']);
        return response()->json($conversation);
    }
}
