<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rendez-vous : docs/03-modele-donnees.md §10. Les prestations prévues sont des types de prestation
 * (table de liaison) pour pré-remplir les lignes au démarrage et protéger les types utilisés.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('scheduled_at')->index();
            $table->text('notes')->nullable();
            $table->enum('status', ['prevu', 'effectue', 'annule'])->default('prevu');
            $table->foreignId('service_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('appointment_service_type', function (Blueprint $table) {
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->restrictOnDelete();
            $table->primary(['appointment_id', 'service_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_service_type');
        Schema::dropIfExists('appointments');
    }
};
