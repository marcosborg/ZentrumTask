<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->date('date_of_birth')->nullable()->after('license_number');
            $table->string('nationality')->nullable()->after('date_of_birth');
            $table->string('marital_status')->nullable()->after('nationality');
            $table->text('address')->nullable()->after('marital_status');
            $table->string('identity_document_type')->nullable()->after('address');
            $table->string('identity_document_number')->nullable()->after('identity_document_type');
            $table->date('identity_document_expires_at')->nullable()->after('identity_document_number');
            $table->string('emergency_contact_name')->nullable()->after('identity_document_expires_at');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->date('license_issued_at')->nullable()->after('emergency_contact_phone');
            $table->date('license_expires_at')->nullable()->after('license_issued_at');
            $table->string('license_category')->nullable()->after('license_expires_at');
            $table->string('tvde_certificate_number')->nullable()->after('license_category');
            $table->date('tvde_certificate_expires_at')->nullable()->after('tvde_certificate_number');
            $table->json('tvde_platforms')->nullable()->after('tvde_certificate_expires_at');
            $table->string('bank_account_holder')->nullable()->after('tvde_platforms');
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('bank_account_holder');
            $table->date('deposit_paid_at')->nullable()->after('deposit_amount');
            $table->string('deposit_payment_method')->nullable()->after('deposit_paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropColumn([
                'date_of_birth',
                'nationality',
                'marital_status',
                'address',
                'identity_document_type',
                'identity_document_number',
                'identity_document_expires_at',
                'emergency_contact_name',
                'emergency_contact_phone',
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
            ]);
        });
    }
};
