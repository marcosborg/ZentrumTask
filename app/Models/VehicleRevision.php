<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VehicleRevision extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleRevisionFactory> */
    use HasFactory;

    protected $fillable = ['vehicle_id', 'scheduled_at', 'completed_at', 'odometer', 'next_odometer', 'interval_years', 'notes'];

    protected static function booted(): void
    {
        static::saving(function (self $revision): void {
            Validator::make($revision->getAttributes(), [
                'scheduled_at' => ['nullable', 'date', 'required_without:completed_at'],
                'completed_at' => ['nullable', 'date', 'before_or_equal:today', 'required_without:scheduled_at'],
                'odometer' => ['nullable', 'integer', 'min:0', 'max:4294967295', Rule::requiredIf($revision->completed_at !== null)],
                'next_odometer' => ['nullable', 'integer', 'min:1', 'max:4294967295', ...($revision->odometer !== null ? ['gt:odometer'] : []), Rule::requiredIf($revision->completed_at !== null && $revision->interval_years === null)],
                'interval_years' => ['nullable', 'integer', 'min:1', 'max:100', Rule::requiredIf($revision->completed_at !== null && $revision->next_odometer === null)],
                'notes' => ['nullable', 'string'],
            ], [
                'required_without' => 'Indique uma data agendada ou realizada.',
                'required' => 'Preencha este campo para definir a próxima revisão.',
                'before_or_equal' => 'A data de realização não pode ser futura.',
                'gt' => 'A próxima revisão deve ter mais quilómetros do que a revisão realizada.',
                'integer' => 'Indique um número inteiro.',
                'min' => 'O valor deve ser pelo menos :min.',
                'max' => 'O valor não pode exceder :max.',
            ])->validate();
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    protected function casts(): array
    {
        return ['scheduled_at' => 'date', 'completed_at' => 'date', 'odometer' => 'integer', 'next_odometer' => 'integer', 'interval_years' => 'integer'];
    }
}
