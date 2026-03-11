<?php

namespace App\Services;

use App\Models\ChatBotSetting;
use App\Models\ChatSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WebsiteChatService
{
    /**
     * @return array{
     *     content: string,
     *     model: string|null,
     *     prompt_tokens: int|null,
     *     completion_tokens: int|null,
     *     total_tokens: int|null
     * }
     */
    public function respond(ChatSession $session, string $userMessage, ChatBotSetting $setting): array
    {
        $apiKey = trim((string) config('services.openai.api_key'));
        $model = trim((string) ($setting->model ?: config('services.openai.model', 'gpt-4.1-mini')));

        if ($apiKey === '') {
            return [
                'content' => 'Chat AI temporariamente indisponivel. Tenta novamente dentro de instantes.',
                'model' => null,
                'prompt_tokens' => null,
                'completion_tokens' => null,
                'total_tokens' => null,
            ];
        }

        $temperature = (float) ($setting->temperature ?? 0.30);
        $temperature = max(0.0, min(2.0, $temperature));

        $messages = $this->buildMessages($session, $setting, $userMessage);
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'temperature' => $temperature,
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao obter resposta do modelo AI.');
        }

        $payload = $response->json();
        $content = trim((string) data_get($payload, 'choices.0.message.content', ''));

        if ($content === '') {
            throw new RuntimeException('Resposta vazia do modelo AI.');
        }

        return [
            'content' => $content,
            'model' => data_get($payload, 'model'),
            'prompt_tokens' => $this->nullableInt(data_get($payload, 'usage.prompt_tokens')),
            'completion_tokens' => $this->nullableInt(data_get($payload, 'usage.completion_tokens')),
            'total_tokens' => $this->nullableInt(data_get($payload, 'usage.total_tokens')),
        ];
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(ChatSession $session, ChatBotSetting $setting, string $userMessage): array
    {
        $historyLimit = max(4, min(50, (int) ($setting->max_history_messages ?? 20)));
        $history = $session->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit($historyLimit)
            ->get(['role', 'content']);

        $systemPrompt = trim((string) $setting->system_instructions);

        if ($systemPrompt === '') {
            $systemPrompt = 'Tu es o assistente virtual da Zentrum TVDE. Responde em Portugues de Portugal, de forma clara, profissional e objetiva.';
        }

        /** @var Collection<int, array{role: string, content: string}> $historyMessages */
        $historyMessages = $history
            ->reverse()
            ->map(fn ($message): array => [
                'role' => (string) $message->role,
                'content' => (string) $message->content,
            ]);

        return [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            ...$historyMessages->all(),
            [
                'role' => 'user',
                'content' => $userMessage,
            ],
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
