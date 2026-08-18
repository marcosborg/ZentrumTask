<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('vehicle_document_alerts')
            ->where('level', 'expiring_30')
            ->update(['level' => 'expiring_60']);

        DB::table('vehicle_document_alerts')
            ->where('message', 'like', 'Documento a expirar em 30 dias:%')
            ->update([
                'message' => DB::raw("replace(message, 'Documento a expirar em 30 dias:', 'Documento a expirar em 60 dias:')"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('vehicle_document_alerts')
            ->where('level', 'expiring_60')
            ->update(['level' => 'expiring_30']);

        DB::table('vehicle_document_alerts')
            ->where('message', 'like', 'Documento a expirar em 60 dias:%')
            ->update([
                'message' => DB::raw("replace(message, 'Documento a expirar em 60 dias:', 'Documento a expirar em 30 dias:')"),
            ]);
    }
};
