<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->foreignId('candidate_application_id')
                ->nullable()
                ->after('id')
                ->constrained('candidate_applications')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('candidate_application_id');
        });
    }
};
