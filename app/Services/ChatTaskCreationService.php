<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Stage;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatTaskCreationService
{
    public function createFromSessionIfReady(ChatSession $session): ?Task
    {
        $session->refresh();
        $meta = $session->meta ?? [];

        if (($meta['source'] ?? null) !== 'whatsapp') {
            return null;
        }

        if (! empty($meta['kanban_task_id'])) {
            return null;
        }

        $externalId = trim((string) ($meta['external_id'] ?? ''));
        $contactName = trim((string) ($meta['external_name'] ?? ''));
        $phone = trim((string) ($meta['phone'] ?? $meta['external_phone'] ?? ''));

        if ($externalId === '' && $phone === '') {
            return null;
        }

        $userMessages = $session->messages()
            ->where('role', 'user')
            ->orderBy('id')
            ->get(['content', 'created_at']);

        if ($userMessages->isEmpty()) {
            return null;
        }

        $combinedText = trim($userMessages->pluck('content')->join("\n"));

        if (! $this->hasEnoughInformation($combinedText, $userMessages->count())) {
            return null;
        }

        $stage = $this->resolveLeadStage();

        if (! $stage) {
            Log::warning('whatsapp_chat_task:no_stage', [
                'chat_session_id' => $session->id,
            ]);

            return null;
        }

        $externalReference = 'whatsapp-chat-'.$session->id;
        $existingTask = Task::query()
            ->where('external_reference', $externalReference)
            ->first();

        if ($existingTask) {
            $session->forceFill([
                'meta' => array_replace($meta, [
                    'kanban_task_id' => $existingTask->id,
                ]),
            ])->save();

            return null;
        }

        return DB::transaction(function () use ($session, $meta, $stage, $userMessages, $combinedText, $externalId, $contactName, $phone, $externalReference): Task {
            $nextPosition = (int) Task::query()
                ->where('board_id', $stage->board_id)
                ->where('stage_id', $stage->id)
                ->max('position');

            $contactLabel = $contactName ?: ($phone ?: $externalId);
            $summary = $this->summarizeMessage($combinedText);
            $title = Str::limit('WhatsApp: '.$contactLabel.' - '.$summary, 160, '');
            $tag = $this->resolveWhatsappTag();

            $task = Task::query()->create([
                'board_id' => $stage->board_id,
                'stage_id' => $stage->id,
                'title' => $title,
                'description' => $this->buildDescription($session, $userMessages, $contactName, $phone, $externalId),
                'priority' => 'normal',
                'position' => $nextPosition + 1,
                'external_reference' => $externalReference,
                'meta' => [
                    'source' => 'whatsapp',
                    'contact_name' => $contactName ?: null,
                    'phone' => $phone ?: null,
                    'whatsapp_id' => $externalId ?: null,
                    'chat_session_id' => $session->id,
                    'chat_session_token' => $session->session_token,
                    'created_from_chat' => true,
                ],
            ]);

            $task->tags()->syncWithoutDetaching([$tag->id]);

            $session->forceFill([
                'meta' => array_replace($meta, [
                    'kanban_task_id' => $task->id,
                    'kanban_task_created_at' => now()->toIso8601String(),
                ]),
            ])->save();

            app(AndroidPushNotificationService::class)->sendNewContactTask($task->load(['assignedTo', 'stage']));

            Log::info('whatsapp_chat_task:created', [
                'chat_session_id' => $session->id,
                'task_id' => $task->id,
            ]);

            return $task;
        });
    }

    private function hasEnoughInformation(string $text, int $messageCount): bool
    {
        $normalized = Str::lower(Str::ascii($text));

        if (mb_strlen(trim($normalized)) < 10) {
            return false;
        }

        $intentKeywords = [
            'alugar',
            'aluguer',
            'carro',
            'viatura',
            'motorista',
            'tvde',
            'uber',
            'bolt',
            'preco',
            'valor',
            'caucao',
            'disponivel',
            'disponibilidade',
            'quero',
            'preciso',
            'interessado',
            'contacto',
            'documentos',
            'candidatura',
            'trabalhar',
            'marcar',
            'visita',
            'informacao',
            'orcamento',
        ];

        foreach ($intentKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return $messageCount >= 2 && mb_strlen($normalized) >= 25;
    }

    private function resolveLeadStage(): ?Stage
    {
        return Stage::query()
            ->where('board_id', 1)
            ->where(function ($query): void {
                $query
                    ->where('is_initial', true)
                    ->orWhereRaw('LOWER(name) = ?', ['entrada'])
                    ->orWhereRaw('LOWER(slug) = ?', ['entrada']);
            })
            ->orderByRaw("CASE WHEN LOWER(name) = 'entrada' OR LOWER(slug) = 'entrada' THEN 0 ELSE 1 END")
            ->orderByDesc('is_initial')
            ->orderBy('position')
            ->first()
            ?? Stage::query()
                ->where('board_id', 1)
                ->orderBy('position')
                ->first();
    }

    private function resolveWhatsappTag(): Tag
    {
        $tag = Tag::withTrashed()->where('slug', 'whatsapp')->first();

        if ($tag) {
            if ($tag->trashed()) {
                $tag->restore();
            }

            return $tag;
        }

        return Tag::query()->create([
            'name' => 'WhatsApp',
            'slug' => 'whatsapp',
            'color' => '#25d366',
        ]);
    }

    private function summarizeMessage(string $text): string
    {
        $singleLine = trim(preg_replace('/\s+/', ' ', $text) ?: $text);

        return Str::limit($singleLine, 72, '');
    }

    /**
     * @param \Illuminate\Support\Collection<int, ChatMessage> $userMessages
     */
    private function buildDescription(ChatSession $session, $userMessages, string $contactName, string $phone, string $externalId): string
    {
        $lines = [
            'Lead criado automaticamente a partir de conversa WhatsApp.',
            '',
            'Origem: WhatsApp',
            'Nome: '.($contactName ?: '-'),
            'Telefone: '.($phone ?: '-'),
            'WhatsApp ID: '.($externalId ?: '-'),
            'Sessao chat: '.$session->session_token,
            '',
            'Mensagens do utilizador:',
        ];

        foreach ($userMessages as $message) {
            $timestamp = $message->created_at?->format('d/m/Y H:i') ?? '-';
            $lines[] = '['.$timestamp.'] '.$message->content;
        }

        return implode("\n", $lines);
    }
}
