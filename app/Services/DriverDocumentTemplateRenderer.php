<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DocumentTemplate;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\VehicleAllocation;
use BackedEnum;
use Illuminate\Support\Carbon;
use NumberFormatter;
use Throwable;

class DriverDocumentTemplateRenderer
{
    /**
     * @return list<string>
     */
    public static function availableTokens(): array
    {
        return [
            'date',
            'date_numeric',
            'name',
            'email',
            'phone',
            'nif',
            'iban',
            'license_number',
            'notes',
            'date_of_birth',
            'nationality',
            'marital_status',
            'address',
            'identity_document_type',
            'identity_document_number',
            'identity_document_expires_at',
            'emergency_contact_name',
            'emergency_contact_phone',
            'sns_number',
            'niss_number',
            'license_issued_at',
            'license_expires_at',
            'license_category',
            'tvde_certificate_number',
            'tvde_certificate_expires_at',
            'tvde_platforms',
            'bank_account_holder',
            'deposit_amount',
            'deposit_paid_at',
            'deposit_payment_method',
            'candidate_application_id',
            'company.name',
            'company.email',
            'company.phone',
            'company.nif',
            'company.address',
            'company.city',
            'company.postal_code',
            'company.country',
            'company.iban',
            'id',
            'candidate_application.full_name',
            'candidate_application.email',
            'candidate_application.phone',
            'candidate_application.nif',
            'candidate_application.iban',
            'candidate_application.experience',
            'candidate_application.platforms',
            'billing_profile.vehicle_rent_type',
            'billing_profile.vehicle_rent_value',
            'billing_profile.vehicle_rent_value_in_words',
            'billing_profile.extra_km_limit',
            'billing_profile.extra_km_rate',
            'billing_profile.valid_from',
            'billing_profile.valid_to',
            'vehicle.license_plate',
            'vehicle.vin',
            'vehicle.make',
            'vehicle.model',
            'vehicle.trim',
            'vehicle.year',
            'vehicle.fuel_type',
            'vehicle.transmission',
            'vehicle.color',
            'vehicle.seats',
            'vehicle.engine_cc',
            'vehicle.power_kw',
            'vehicle.current_odometer',
            'vehicle.status',
            'vehicle.acquisition_date',
            'vehicle.acquisition_cost',
            'vehicle.notes',
            'vehicle.insurance_expires_at',
            'vehicle.inspection_expires_at',
            'vehicle.ipo_expires_at',
            'vehicle_allocation.starts_at',
            'vehicle_allocation.ends_at',
            'vehicle_allocation.start_odometer',
            'vehicle_allocation.end_odometer',
            'vehicle_allocation.status',
            'vehicle_allocation.handover_location',
            'vehicle_allocation.notes',
        ];
    }

    public function render(DocumentTemplate $template, Driver $driver, ?Carbon $documentDate = null): string
    {
        $documentDate ??= now();
        $data = $this->templateData($driver, $documentDate);

        $rendered = preg_replace_callback('/{{\s*(.+?)\s*}}/s', function (array $matches) use ($data): string {
            $key = str_replace(["\u{00A0}", "\n", "\r"], ' ', $matches[1]);
            $key = strip_tags($key);
            $key = html_entity_decode($key, ENT_QUOTES | ENT_HTML5);
            $key = trim((string) preg_replace('/\s+/', ' ', $key));
            $value = data_get($data, $key, '');

            return e($this->stringValue($value));
        }, $template->content) ?? $template->content;

        $title = e($template->name);

        return <<<HTML
<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Times New Roman", serif; color: #000; margin: 20px; line-height: 1.35; font-size: 13px; }
        h1 { margin: 0 0 14px; font-weight: bold; font-size: 16px; }
        h2 { margin: 0 0 12px; font-weight: bold; font-size: 14px; }
        h3 { margin: 0 0 12px; font-weight: bold; font-size: 13px; }
        p { margin: 0 0 14px; }
        ul, ol { margin: 0 0 14px 20px; }
        li { margin: 0 0 8px; }
        .header { margin-bottom: 12px; }
        .header img { height: 42px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="https://zentrum-tvde.com/website/assets/logo.svg" alt="Zentrum TVDE">
    </div>
    <h1>{$title}</h1>
    {$rendered}
</body>
</html>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private function templateData(Driver $driver, Carbon $documentDate): array
    {
        $driver->loadMissing('company', 'candidateApplication');
        $data = $driver->toArray();

        foreach ([
            'date_of_birth',
            'identity_document_expires_at',
            'license_issued_at',
            'license_expires_at',
            'tvde_certificate_expires_at',
            'deposit_paid_at',
            'created_at',
            'updated_at',
        ] as $field) {
            $data[$field] = $this->formatDate($driver->{$field});
        }

        $candidateData = $driver->candidateApplication?->toArray() ?? [];

        foreach (['submitted_at', 'last_saved_at', 'rental_terms_accepted_at', 'legal_confirmed_at'] as $field) {
            $candidateData[$field] = $this->formatDate($driver->candidateApplication?->{$field});
        }

        $allocation = VehicleAllocation::query()
            ->with('vehicle')
            ->where('driver_id', $driver->id)
            ->active()
            ->latest('starts_at')
            ->first();

        $vehicle = $allocation?->vehicle;
        $vehicleData = $vehicle?->toArray() ?? [];

        foreach (['acquisition_date', 'created_at', 'updated_at'] as $field) {
            $vehicleData[$field] = $this->formatDate($vehicle?->{$field});
        }

        if ($vehicle) {
            $documents = $vehicle->documents()
                ->whereIn('type', ['INSURANCE', 'INSPECTION'])
                ->orderByDesc('expires_at')
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->get()
                ->groupBy('type');

            $vehicleData['insurance_expires_at'] = $this->formatDate($documents->get('INSURANCE')?->first()?->expires_at);
            $vehicleData['inspection_expires_at'] = $this->formatDate($documents->get('INSPECTION')?->first()?->expires_at);
            $vehicleData['ipo_expires_at'] = $vehicleData['inspection_expires_at'];
        }

        $allocationData = $allocation?->toArray() ?? [];

        foreach (['starts_at', 'ends_at', 'created_at', 'updated_at'] as $field) {
            $allocationData[$field] = $this->formatDate($allocation?->{$field});
        }

        $billingProfile = $this->currentBillingProfile($driver, $documentDate);
        $billingData = $billingProfile?->toArray() ?? [];

        if ($billingProfile) {
            $billingData['vehicle_rent_type'] = $billingProfile->vehicle_rent_type?->value ?? '';
            $billingData['vehicle_rent_value'] = $this->formatDecimal($billingProfile->vehicle_rent_value, 2);
            $billingData['vehicle_rent_value_in_words'] = $this->amountInWords($billingProfile->vehicle_rent_value);
            $billingData['extra_km_limit'] = $this->formatDecimal($billingProfile->extra_km_limit, 0);
            $billingData['extra_km_rate'] = $this->formatDecimal($billingProfile->extra_km_rate, 2);
            $billingData['valid_from'] = $this->formatDate($billingProfile->valid_from);
            $billingData['valid_to'] = $this->formatDate($billingProfile->valid_to);
        }

        $data['date'] = $documentDate->copy()->locale('pt_PT')->translatedFormat('j \\d\\e F \\d\\e Y');
        $data['date_numeric'] = $documentDate->format('d-m-Y');
        $data['company'] = ($driver->company ?: Company::query()->first())?->toArray() ?? [];
        $data['candidate_application'] = $candidateData;
        $data['candidateApplication'] = $candidateData;
        $data['billing_profile'] = $billingData;
        $data['billingProfile'] = $billingData;
        $data['vehicle'] = $vehicleData;
        $data['vehicle_allocation'] = $allocationData;
        $data['vehicleAllocation'] = $allocationData;

        return $data;
    }

    private function currentBillingProfile(Driver $driver, Carbon $documentDate): ?DriverBillingProfile
    {
        return $driver->billingProfiles()
            ->active()
            ->where(function ($query) use ($documentDate): void {
                $query->whereNull('valid_from')
                    ->orWhereDate('valid_from', '<=', $documentDate->toDateString());
            })
            ->where(function ($query) use ($documentDate): void {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $documentDate->toDateString());
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d-m-Y');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function formatDecimal(mixed $value, int $decimals): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, $decimals, ',', '.');
    }

    private function amountInWords(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $amount = round((float) $value, 2);
        $euros = (int) floor($amount);
        $cents = (int) round(($amount - $euros) * 100);
        $formatter = new NumberFormatter('pt_PT', NumberFormatter::SPELLOUT);
        $result = $formatter->format($euros).' '.($euros === 1 ? 'euro' : 'euros');

        if ($cents > 0) {
            $result .= ' e '.$formatter->format($cents).' '.($cents === 1 ? 'cêntimo' : 'cêntimos');
        }

        return mb_strtolower($result);
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if (is_array($value)) {
            return collect($value)
                ->filter(fn (mixed $item): bool => is_scalar($item))
                ->map(fn (mixed $item): string => (string) $item)
                ->implode(', ');
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
