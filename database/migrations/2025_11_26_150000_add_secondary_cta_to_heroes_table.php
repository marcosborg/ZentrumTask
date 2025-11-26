<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heroes', function (Blueprint $table) {
            $table->string('cta_secondary_text')->nullable()->after('cta_link');
            $table->string('cta_secondary_link')->nullable()->after('cta_secondary_text');
        });
    }

    public function down(): void
    {
        Schema::table('heroes', function (Blueprint $table) {
            $table->dropColumn(['cta_secondary_text', 'cta_secondary_link']);
        });
    }
};
