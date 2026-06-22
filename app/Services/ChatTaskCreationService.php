<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Stage;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatTaskCreationService
{
    public function missingContactPromptForSession(ChatSession $session): ?string
    {
        $contact = $this->syncContactData($session);

        if (! $this->shouldTrackLead($session, $contact)) {
            return null;
        }

        $missingFields = $this->missingRequiredFields($contact);

        if ($missingFields === []) {
            return null;
        }

        if (in_array('phone', $missingFields, true) && in_array('contact_name', $missingFields, true)) {
            return 'Para a nossa equipa conseguir ligar de volta e dar seguimento ao pedido, pode indicar o seu nome e telefone?';
        }

        if (in_array('phone', $missingFields, true)) {
            return 'Para a nossa equipa conseguir ligar de volta, pode indicar o seu telefone?';
        }

        return 'Para registarmos corretamente o pedido, pode indicar o seu nome?';
    }

    public function createFromSessionIfReady(ChatSession $session): ?Task
    {
        $session->refresh();
        $meta = $session->meta ?? [];

        if (! empty($meta['kanban_task_id'])) {
            return null;
        }

        $contact = $this->syncContactData($session);

        if (! $this->shouldTrackLead($session, $contact) || $this->missingRequiredFields($contact) !== []) {
            return null;
        }

        $userMessages = $this->userMessages($session);

        if ($userMessages->isEmpty()) {
            return null;
        }

        $combinedText = $this->combinedText($userMessages);

        if (! $this->hasEnoughInformation($combinedText, $userMessages->count())) {
            return null;
        }

        $stage = $this->resolveLeadStage();

        if (! $stage) {
            Log::warning('chat_task:no_stage', [
                'chat_session_id' => $session->id,
                'source' => $contact['source'],
            ]);

            return null;
        }

        $externalReference = $contact['source'].'-chat-'.$session->id;
        $existingTask = Task::query()
            ->where('external_reference', $externalReference)
            ->first();

        if ($existingTask) {
            $this->markSessionTask($session, $existingTask);

            return null;
        }

        return DB::transaction(function () use ($session, $stage, $userMessages, $combinedText, $contact, $externalReference): Task {
            $nextPosition = (int) Task::query()
                ->where('board_id', $stage->board_id)
                ->where('stage_id', $stage->id)
                ->max('position');

            $summary = $this->summarizeMessage($combinedText);
            $titlePrefix = $contact['source'] === 'whatsapp' ? 'WhatsApp' : 'Chat website';
            $title = Str::limit($titlePrefix.': '.$contact['contact_name'].' - '.$summary, 160, '');
            $tag = $contact['source'] === 'whatsapp' ? $this->resolveWhatsappTag() : null;

            $task = Task::query()->create([
                'board_id' => $stage->board_id,
                'stage_id' => $stage->id,
                'title' => $title,
                'description' => $this->buildDescription($session, $userMessages, $contact),
                'priority' => 'normal',
                'position' => $nextPosition + 1,
                'external_reference' => $externalReference,
                'meta' => [
                    'source' => $contact['source'],
                    'contact_name' => $contact['contact_name'],
                    'phone' => $contact['phone'],
                    'email' => $contact['email'],
                    'whatsapp_id' => $contact['external_id'],
                    'chat_session_id' => $session->id,
                    'chat_session_token' => $session->session_token,
                    'created_from_chat' => true,
                ],
            ]);

            if ($tag) {
                $task->tags()->syncWithoutDetaching([$tag->id]);
            }

            $this->markSessionTask($session, $task);

            app(AndroidPushNotificationService::class)->sendNewContactTask($task->load(['assignedTo', 'stage']));

            Log::info('chat_task:created', [
                'chat_session_id' => $session->id,
                'task_id' => $task->id,
                'source' => $contact['source'],
            ]);

            return $task;
        });
    }

    /**
     * @return array{
     *     source: string,
     *     contact_name: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     external_id: string|null,
     *     should_request_contact: bool
     * }
     */
    private function syncContactData(ChatSession $session): array
    {
        $session->refresh();
        $meta = $session->meta ?? [];
        $source = (string) ($meta['source'] ?? 'website');
        $userMessages = $this->userMessages($session);
        $combinedText = $this->combinedText($userMessages);

        $externalId = trim((string) ($meta['external_id'] ?? ''));
        $contactName = $this->firstFilled(
            $meta['contact_name'] ?? null,
            $meta['external_name'] ?? null,
            $this->extractName($combinedText),
        );
        $phone = $this->firstValidPhone(
            $meta['phone'] ?? null,
            $meta['external_phone'] ?? null,
            $this->extractPhone($externalId),
            $this->extractPhone($combinedText),
        );
        $email = $this->firstFilled(
            $meta['email'] ?? null,
            $this->extractEmail($combinedText),
        );
        $shouldRequestContact = (bool) ($meta['should_request_contact'] ?? false)
            || in_array($source, ['whatsapp'], true)
            || $this->hasEnoughInformation($combinedText, $userMessages->count())
            || $contactName !== null
            || $phone !== null
            || $email !== null;

        $newMeta = array_replace($meta, [
            'source' => $source,
            'contact_name' => $contactName,
            'phone' => $phone,
            'email' => $email,
            'should_request_contact' => $shouldRequestContact,
        ]);

        if ($newMeta !== $meta) {
            $session->forceFill([
                'meta' => $newMeta,
            ])->save();
        }

        return [
            'source' => $source,
            'contact_name' => $contactName,
            'phone' => $phone,
            'email' => $email,
            'external_id' => $externalId ?: null,
            'should_request_contact' => $shouldRequestContact,
        ];
    }

    /**
     * @param  array<string, string|null|bool>  $contact
     * @return list<string>
     */
    private function missingRequiredFields(array $contact): array
    {
        $missing = [];

        if (trim((string) ($contact['contact_name'] ?? '')) === '') {
            $missing[] = 'contact_name';
        }

        if (! $this->isValidPhone((string) ($contact['phone'] ?? ''))) {
            $missing[] = 'phone';
        }

        return $missing;
    }

    /**
     * @param  array<string, string|null|bool>  $contact
     */
    private function shouldTrackLead(ChatSession $session, array $contact): bool
    {
        if (! in_array($contact['source'] ?? null, ['website', 'whatsapp'], true)) {
            return false;
        }

        return (bool) ($contact['should_request_contact'] ?? false)
            || $this->hasEnoughInformation($this->combinedText($this->userMessages($session)), $this->userMessages($session)->count());
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    private function userMessages(ChatSession $session): Collection
    {
        return $session->messages()
            ->where('role', 'user')
            ->orderBy('id')
            ->get(['content', 'created_at']);
    }

    /**
     * @param  Collection<int, ChatMessage>  $userMessages
     */
    private function combinedText(Collection $userMessages): string
    {
        return trim($userMessages->pluck('content')->join("\n"));
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
            'condicoes',
            'proposta',
            'comecar',
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
     * @param  Collection<int, ChatMessage>  $userMessages
     * @param  array<string, string|null|bool>  $contact
     */
    private function buildDescription(ChatSession $session, Collection $userMessages, array $contact): string
    {
        $sourceLabel = $contact['source'] === 'whatsapp' ? 'WhatsApp' : 'Chat website';

        $lines = [
            'Lead criado automaticamente a partir de conversa.',
            '',
            'Origem: '.$sourceLabel,
            'Nome: '.($contact['contact_name'] ?: '-'),
            'Telefone: '.($contact['phone'] ?: '-'),
            'Email: '.($contact['email'] ?: '-'),
            'ID externo: '.($contact['external_id'] ?: '-'),
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

    private function markSessionTask(ChatSession $session, Task $task): void
    {
        $session->refresh();

        $session->forceFill([
            'meta' => array_replace($session->meta ?? [], [
                'kanban_task_id' => $task->id,
                'kanban_task_created_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    private function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function firstValidPhone(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($this->isValidPhone($value)) {
                return $value;
            }
        }

        return null;
    }

    private function extractPhone(string $value): ?string
    {
        preg_match_all('/(?:\+351\s*)?(?:\d[\s.\-()]*){9,13}/', $value, $matches);

        foreach ($matches[0] ?? [] as $candidate) {
            $phone = trim(preg_replace('/\s+/', ' ', (string) $candidate) ?? '');

            if ($this->isValidPhone($phone)) {
                return $phone;
            }
        }

        return null;
    }

    private function isValidPhone(string $phone): bool
    {
        $phone = trim($phone);

        if ($phone === '' || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $phone)) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '351')) {
            $digits = substr($digits, 3);
        }

        return strlen($digits) === 9 && preg_match('/^[29]\d{8}$/', $digits) === 1;
    }

    private function extractEmail(string $text): ?string
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[0]);
    }

    private function extractName(string $text): ?string
    {
        $text = Str::ascii($text);
        $patterns = [
            '/(?:chamo-me|chamo me|sou|o meu nome e|nome e)\s+([A-Z][A-Z\' -]{1,80})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }

            $name = trim((string) $matches[1]);
            $name = preg_replace('/\s+(?:e|,|\.|;|telefone|telemovel|email).*$/iu', '', $name) ?? $name;
            $name = trim($name);

            if ($name !== '' && ! $this->isValidPhone($name) && ! str_contains($name, '@')) {
                return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
            }
        }

        return null;
    }
}
