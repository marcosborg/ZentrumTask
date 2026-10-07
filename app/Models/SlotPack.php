<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlotPack extends Model
{
    /** @use HasFactory<\Database\Factories\SlotPackFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weekly_price' => 'decimal:2', 'valid_from' => 'date', 'valid_to' => 'date'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $pack): void {
            if ($pack->assignments()->exists() && $pack->isDirty(['name', 'code', 'weekly_price', 'valid_from', 'benefits'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['weekly_price' => 'Crie uma nova versão do pack para preservar o histórico.']);
            }
        });
    }

    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SlotPackAssignment::class);
    }
}
