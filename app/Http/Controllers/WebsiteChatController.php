<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatMessageRequest;
use App\Http\Requests\ChatSessionStartRequest;
use App\Models\ChatBotSetting;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\WebsiteChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class WebsiteChatController extends Controller
{
    public function start(ChatSessionStartRequest $request): JsonResponse
    {
        $data = $request->validated();
        $setting = ChatBotSetting::current();
        $session = $this->resolveSession(
            sessionToken: $data['session_token'] ?? null,
            requestIp: $request->ip(),
            userAgent: $request->userAgent()
        );

        if ($setting->is_enabled && ($setting->welcome_message ?? null) && $session->messages()->count() === 0) {
            $session->messages()->create([
                'role' => 'assistant',
                'content' => (string) $setting->welcome_message,
            ]);

            $session->forceFill([
                'last_message_at' => now(),
            ])->save();
        }

        return response()->json([
            'enabled' => (bool) $setting->is_enabled,
            'session_token' => $session->session_token,
            'assistant_name' => (string) ($setting->name ?: 'Zentrum Assistant'),
            'messages' => $this->formatMessages($session),
        ]);
    }

    public function message(ChatMessageRequest $request, WebsiteChatService $chatService): JsonResponse
    {
        $data = $request->validated();
        $setting = ChatBotSetting::current();

        if (! $setting->is_enabled) {
            return response()->json([
                'message' => 'Chat desativado no momento.',
            ], 403);
        }

        $session = $this->resolveSession(
            sessionToken: $data['session_token'],
            requestIp: $request->ip(),
            userAgent: $request->userAgent()
        );

        $userMessage = ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => (string) $data['message'],
        ]);

        $session->forceFill([
            'last_message_at' => now(),
        ])->save();

        try {
            $reply = $chatService->respond($session, (string) $data['message'], $setting);
        } catch (Throwable $exception) {
            report($exception);

            $reply = [
                'content' => 'Ocorreu um erro temporario. Tenta novamente dentro de momentos.',
                'model' => null,
                'prompt_tokens' => null,
                'completion_tokens' => null,
                'total_tokens' => null,
            ];
        }

        $assistantMessage = ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => (string) $reply['content'],
            'model' => $reply['model'],
            'prompt_tokens' => $reply['prompt_tokens'],
            'completion_tokens' => $reply['completion_tokens'],
            'total_tokens' => $reply['total_tokens'],
        ]);

        $session->forceFill([
            'last_message_at' => now(),
        ])->save();

        return response()->json([
            'session_token' => $session->session_token,
            'user_message' => $this->formatMessage($userMessage),
            'assistant_message' => $this->formatMessage($assistantMessage),
        ]);
    }

    private function resolveSession(?string $sessionToken, ?string $requestIp, ?string $userAgent): ChatSession
    {
        $session = null;

        if ($sessionToken) {
            $session = ChatSession::query()
                ->where('session_token', $sessionToken)
                ->first();
        }

        if (! $session) {
            $session = ChatSession::query()->create([
                'session_token' => (string) Str::uuid(),
                'ip_address' => $requestIp,
                'user_agent' => $userAgent,
                'started_at' => now(),
                'last_message_at' => now(),
            ]);
        }

        if (($session->ip_address !== $requestIp) || ($session->user_agent !== $userAgent)) {
            $session->forceFill([
                'ip_address' => $requestIp,
                'user_agent' => $userAgent,
            ])->save();
        }

        return $session;
    }

    /**
     * @return array<int, array{role: string, content: string, created_at: string}>
     */
    private function formatMessages(ChatSession $session): array
    {
        return $session->messages()
            ->orderBy('id')
            ->get(['role', 'content', 'created_at'])
            ->map(fn (ChatMessage $message): array => $this->formatMessage($message))
            ->all();
    }

    /**
     * @return array{role: string, content: string, created_at: string}
     */
    private function formatMessage(ChatMessage $message): array
    {
        return [
            'role' => (string) $message->role,
            'content' => (string) $message->content,
            'created_at' => Carbon::parse($message->created_at)->toIso8601String(),
        ];
    }
}
