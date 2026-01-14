<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('source')
                ->default('tvde')
                ->after('status')
                ->index();
        });

        if (Schema::hasColumn('vehicles', 'is_tvde')) {
            DB::table('vehicles')
                ->where('is_tvde', true)
                ->update(['source' => 'tvde']);

            DB::table('vehicles')
                ->where('is_tvde', false)
                ->update(['source' => 'private']);

            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('is_tvde');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('is_tvde')
                ->default(true)
                ->after('status');
        });

        DB::table('vehicles')
            ->where('source', 'tvde')
            ->update(['is_tvde' => true]);

        DB::table('vehicles')
            ->where('source', '!=', 'tvde')
            ->update(['is_tvde' => false]);

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
