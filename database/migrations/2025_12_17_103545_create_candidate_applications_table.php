<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('candidate_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();

            $table->string('status')->default('draft'); // draft, incomplete, submitted
            $table->string('current_step')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->ipAddress('last_ip')->nullable();

            // Fase 1 e 2
            $table->boolean('accepts_model')->default(false);
            $table->boolean('independent_driver')->default(false);
            $table->boolean('rental_terms_read')->default(false);
            $table->boolean('rental_terms_accept')->default(false);
            $table->timestamp('rental_terms_accepted_at')->nullable();
            $table->ipAddress('rental_terms_ip')->nullable();

            // Fase 3 – Elegibilidade
            $table->boolean('has_tvde_course')->default(false);
            $table->boolean('certificate_valid')->default(false);
            $table->string('experience')->nullable();
            $table->json('platforms')->nullable();

            // Fase 4 – Dados pessoais
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('nif', 30)->nullable();

            // Fase 5 – Uploads
            $table->json('documents')->nullable(); // guarda caminhos/nomes dos uploads

            // Fase 6 – Legais
            $table->boolean('rgpd')->default(false);
            $table->boolean('truth_declaration')->default(false);
            $table->boolean('contact_authorization')->default(false);
            $table->timestamp('legal_confirmed_at')->nullable();
            $table->ipAddress('legal_ip')->nullable();
            $table->string('legal_version')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_applications');
    }
};
