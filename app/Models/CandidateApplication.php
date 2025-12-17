<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CandidateApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'status',
        'current_step',
        'submitted_at',
        'last_saved_at',
        'last_ip',
        'accepts_model',
        'independent_driver',
        'rental_terms_read',
        'rental_terms_accept',
        'rental_terms_accepted_at',
        'rental_terms_ip',
        'has_tvde_course',
        'certificate_valid',
        'experience',
        'platforms',
        'full_name',
        'email',
        'phone',
        'nif',
        'documents',
        'rgpd',
        'truth_declaration',
        'contact_authorization',
        'legal_confirmed_at',
        'legal_ip',
        'legal_version',
        'vehicle_type_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'rental_terms_accepted_at' => 'datetime',
            'legal_confirmed_at' => 'datetime',
            'accepts_model' => 'boolean',
            'independent_driver' => 'boolean',
            'rental_terms_read' => 'boolean',
            'rental_terms_accept' => 'boolean',
            'has_tvde_course' => 'boolean',
            'certificate_valid' => 'boolean',
            'rgpd' => 'boolean',
            'truth_declaration' => 'boolean',
            'contact_authorization' => 'boolean',
            'platforms' => 'array',
            'documents' => 'array',
            'vehicle_type_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CandidateApplication $application): void {
            if ($application->token === null) {
                $application->token = (string) Str::uuid();
            }
        });
    }
}
