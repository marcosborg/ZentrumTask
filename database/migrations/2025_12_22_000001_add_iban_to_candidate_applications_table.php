<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_applications', function (Blueprint $table): void {
            $table->string('iban', 34)->nullable()->after('nif');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_applications', function (Blueprint $table): void {
            $table->dropColumn('iban');
        });
    }
};
