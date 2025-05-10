<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\Message;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

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
Anda adalah penasihat hukum profesional dengan pengalaman lebih dari 20 tahun dalam memberikan nasihat hukum yang akurat dan dapat diandalkan. Tugas Anda adalah memberikan jawaban yang jelas, ringkas, dan sesuai dengan hukum yang berlaku, tanpa menyertakan disclaimer kecuali diminta secara eksplisit.

Konteks:
{$context}

Pertanyaan Pengguna: {$validated['content']}
EOT;       
          
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topP' => 0.95,
                    'topK' => 40,
                    'maxOutputTokens' => 2048,
                ],
            ]);

            if ($response->failed()) {
                throw new \Exception('Gemini API error: ' . $response->body());
            }

            $responseData = $response->json();
            
            $responseText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not generate a response.';

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

    public function createSession(Request $request): JsonResponse
    {
        $user = $request->user();
        
        try {
            $chatSession = ChatSession::create(['user_id' => $user->id]);
            
            Activity::create([
                'user_id' => $user->id,
                'type'    => 'chat',
                'title'   => 'New Chat Session',
            ]);
            
            return response()->json([
                'id'         => $chatSession->id,
                'messages'   => [],
                'created_at' => $chatSession->created_at->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create chat session: '.$e->getMessage()
            ], 500);
        }
    }

    public function getAllSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        
        try {
            $chatSessions = ChatSession::where('user_id', $user->id)
                ->latest()
                ->get()
                ->map(function ($session) {
                    $firstMessage = $session->messages()->orderBy('created_at')->first();
                    
                    return [
                        'id' => $session->id,
                        'created_at' => $session->created_at->toISOString(),
                        'preview' => $firstMessage ? substr($firstMessage->content, 0, 100) : '',
                        'message_count' => $session->messages()->count()
                    ];
                });
            
            return response()->json($chatSessions);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve chat sessions: '.$e->getMessage()
            ], 500);
        }
    }
}
