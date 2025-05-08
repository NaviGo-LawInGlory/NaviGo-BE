<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\Message;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use GenerativeAI\Client;
use GenerativeAI\Resources\Parts\TextPart;

class ChatController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content'    => 'required|string',
            'session_id' => 'nullable|string',
        ]);

        $user = $request->user();

        try {
            if (!empty($validated['session_id'])) {
                $chatSession = ChatSession::where('id', $validated['session_id'])
                    ->where('user_id', $user->id)
                    ->firstOrFail();
            } else {
                $chatSession = ChatSession::where('user_id', $user->id)
                    ->latest()
                    ->first();

                if (!$chatSession) {
                    $chatSession = ChatSession::create(['user_id' => $user->id]);
                    Activity::create([
                        'user_id' => $user->id,
                        'type'    => 'chat',
                        'title'   => 'New Chat Session',
                    ]);
                }
            }

            $userMessage = Message::create([
                'chat_session_id' => $chatSession->id,
                'content'         => $validated['content'],
                'is_user'         => true,
            ]);

            $apiKey = config('services.gemini.api_key');
            $client = new Client($apiKey);

            $previous = Message::where('chat_session_id', $chatSession->id)
                ->orderBy('created_at', 'desc')
                ->skip(1)
                ->take(5)
                ->get()
                ->reverse();

            $context = '';
            foreach ($previous as $msg) {
                $role = $msg->is_user ? 'User' : 'Assistant';
                $context .= "$role: {$msg->content}\n";
            }

            $prompt = <<<EOT
You are a legal assistant. Please respond to this query in a professional and helpful manner.

Context:
{$context}

User Query: {$validated['content']}
EOT;

            $response = $client
                ->GeminiPro()                 
                ->generateContent(new TextPart($prompt));

            $responseText = $response->text();

            $aiMessage = Message::create([
                'chat_session_id' => $chatSession->id,
                'content'         => $responseText,
                'is_user'         => false,
            ]);

            return response()->json([
                'id'        => $aiMessage->id,
                'content'   => $responseText,
                'isUser'    => false,
                'timestamp' => $aiMessage->created_at->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process chat message: '.$e->getMessage()
            ], 500);
        }
    }

    public function getSession(Request $request, $sessionId = null): JsonResponse
    {
        $user = $request->user();

        try {
            if ($sessionId) {
                $chatSession = ChatSession::where('id', $sessionId)
                    ->where('user_id', $user->id)
                    ->firstOrFail();
            } else {
                $chatSession = ChatSession::where('user_id', $user->id)
                    ->latest()
                    ->firstOrFail();
            }

            $messages = $chatSession->messages->map(fn($msg) => [
                'id'        => $msg->id,
                'content'   => $msg->content,
                'isUser'    => $msg->is_user,
                'timestamp' => $msg->created_at->toISOString(),
            ]);

            return response()->json([
                'id'         => $chatSession->id,
                'messages'   => $messages,
                'created_at' => $chatSession->created_at->toISOString(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            if (!$sessionId) {
                $chatSession = ChatSession::create(['user_id' => $user->id]);
                return response()->json([
                    'id'         => $chatSession->id,
                    'messages'   => [],
                    'created_at' => $chatSession->created_at->toISOString(),
                ]);
            }
            return response()->json(['message' => 'Chat session not found'], 404);
        }
    }
}
