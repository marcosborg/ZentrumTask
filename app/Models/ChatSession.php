<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_token',
        'ip_address',
        'user_agent',
        'started_at',
        'last_message_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_message_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function getSourceAttribute(): string
    {
        return (string) data_get($this->meta, 'source', 'website');
    }
}
