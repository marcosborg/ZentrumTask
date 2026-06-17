<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_handover_procedures', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicle_handover_procedures', 'video_items')) {
                $table->json('video_items')->nullable()->after('guided_photo_items');
            }

            if (! Schema::hasColumn('vehicle_handover_procedures', 'exchange_group_uuid')) {
                $table->uuid('exchange_group_uuid')->nullable()->after('created_allocation_id')->index('vhp_exchange_group_idx');
            }

            if (! Schema::hasColumn('vehicle_handover_procedures', 'exchange_related_procedure_id')) {
                $table->foreignId('exchange_related_procedure_id')->nullable()->after('exchange_group_uuid');
            }

            if (! Schema::hasColumn('vehicle_handover_procedures', 'email_sent_at')) {
                $table->timestamp('email_sent_at')->nullable()->after('pdf_path');
            }

            if (! Schema::hasColumn('vehicle_handover_procedures', 'email_recipients')) {
                $table->json('email_recipients')->nullable()->after('email_sent_at');
            }
        });

        Schema::table('vehicle_handover_procedures', function (Blueprint $table): void {
            $table->foreign('exchange_related_procedure_id', 'vhp_exchange_related_fk')
                ->references('id')
                ->on('vehicle_handover_procedures')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_handover_procedures', function (Blueprint $table): void {
            $table->dropForeign('vhp_exchange_related_fk');
            $table->dropColumn([
                'video_items',
                'exchange_group_uuid',
                'exchange_related_procedure_id',
                'email_sent_at',
                'email_recipients',
            ]);
        });
    }
};
