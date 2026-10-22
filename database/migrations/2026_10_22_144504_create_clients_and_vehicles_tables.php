<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clients, véhicules et historique des propriétaires (docs/03-modele-donnees.md §4).
 * Toutes les FK en restrictOnDelete : l'historique ne disparaît jamais (RG-04).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name');
            $table->string('phone', 30);
            $table->string('phone_normalized', 20)->unique();
            $table->foreignId('wilaya_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('last_name');
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('plate', 30);
            $table->string('plate_normalized', 30)->unique();
            $table->string('vin', 30)->nullable()->unique();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_model_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->foreignId('energy_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('last_mileage')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_ownerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_ownerships');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('clients');
    }
};
