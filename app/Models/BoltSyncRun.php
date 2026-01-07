<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoltSyncRun extends Model
{
    /** @use HasFactory<\Database\Factories\BoltSyncRunFactory> */
    use HasFactory;

    protected $fillable = [
        'source_path',
        'status',
        'started_at',
        'finished_at',
        'row_count',
        'totals',
        'meta',
    ];

    public function earnings(): HasMany
    {
        return $this->hasMany(BoltDriverEarning::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'row_count' => 'integer',
            'totals' => 'array',
            'meta' => 'array',
        ];
    }
}
