<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_document_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_document_id')->constrained('vehicle_documents')->cascadeOnDelete();
            $table->string('level')->index();
            $table->date('triggered_on')->index();
            $table->string('message');
            $table->boolean('is_resolved')->default(false)->index();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_document_id', 'level', 'triggered_on'], 'vehicle_doc_alert_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_document_alerts');
    }
};
