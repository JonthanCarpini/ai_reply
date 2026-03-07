<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminConversationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Conversation::with('user:id,name,email')
            ->withCount('messages');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', "%{$search}%")
                  ->orWhere('contact_phone', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $conversations = $query->orderByDesc('last_message_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($conversations);
    }

    public function show(int $id): JsonResponse
    {
        $conversation = Conversation::with('user:id,name,email')
            ->withCount('messages')
            ->findOrFail($id);

        return response()->json(['data' => $conversation]);
    }

    public function messages(int $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $messages]);
    }
}
