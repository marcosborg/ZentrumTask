<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatBotSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_enabled',
        'welcome_message',
        'system_instructions',
        'model',
        'temperature',
        'max_history_messages',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'temperature' => 'decimal:2',
            'max_history_messages' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderByDesc('id')->first() ?? new self([
            'name' => 'Zentrum Assistant',
            'is_enabled' => true,
            'welcome_message' => 'Ola! Como posso ajudar com TVDE hoje?',
            'system_instructions' => null,
            'model' => 'gpt-4.1-mini',
            'temperature' => 0.3,
            'max_history_messages' => 20,
        ]);
    }
}
