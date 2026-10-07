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
        if (! Schema::hasColumn('vehicle_document_alerts', 'document_expires_at')) {
            Schema::table('vehicle_document_alerts', function (Blueprint $table) {
                $table->date('document_expires_at')->nullable();
            });
        }

        if (! Schema::hasIndex('vehicle_document_alerts', 'vehicle_doc_alert_document_index')) {
            Schema::table('vehicle_document_alerts', function (Blueprint $table) {
                $table->index('vehicle_document_id', 'vehicle_doc_alert_document_index');
            });
        }

        if (Schema::hasIndex('vehicle_document_alerts', 'vehicle_doc_alert_unique')) {
            Schema::table('vehicle_document_alerts', function (Blueprint $table) {
                $table->dropUnique('vehicle_doc_alert_unique');
            });
        }

        DB::table('vehicle_document_alerts')->whereNull('document_expires_at')->update([
            'document_expires_at' => DB::raw('(SELECT expires_at FROM vehicle_documents WHERE vehicle_documents.id = vehicle_document_alerts.vehicle_document_id)'),
        ]);

        if (! Schema::hasIndex('vehicle_document_alerts', 'vehicle_doc_alert_expiry_unique')) {
            Schema::table('vehicle_document_alerts', function (Blueprint $table) {
                $table->unique(['vehicle_document_id', 'document_expires_at', 'level', 'triggered_on'], 'vehicle_doc_alert_expiry_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_document_alerts', function (Blueprint $table) {
            $table->unique(['vehicle_document_id', 'level', 'triggered_on'], 'vehicle_doc_alert_unique');
            $table->dropUnique('vehicle_doc_alert_expiry_unique');
            $table->dropColumn('document_expires_at');
        });
    }
};
