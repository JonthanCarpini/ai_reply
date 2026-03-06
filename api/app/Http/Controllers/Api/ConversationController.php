<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()->conversations()
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return response()->json($conversations);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = $request->user()->conversations()->findOrFail($id);

        return response()->json(['data' => $conversation]);
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        $conversation = $request->user()->conversations()->findOrFail($id);

        $messages = $conversation->messages()
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($messages);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $conversation = $request->user()->conversations()->findOrFail($id);
        $conversation->update(['status' => 'archived']);

        return response()->json(['message' => 'Conversa arquivada.']);
    }

    public function block(Request $request, int $id): JsonResponse
    {
        $conversation = $request->user()->conversations()->findOrFail($id);
        $conversation->update(['status' => 'blocked']);

        return response()->json(['message' => 'Contato bloqueado.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $conversation = $request->user()->conversations()->findOrFail($id);
        $conversation->delete();

        return response()->json(['message' => 'Conversa excluída.']);
    }
}
