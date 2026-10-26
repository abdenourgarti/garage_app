<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recherche avancée (phase 7) : le filtre « types de prestation » lit cet index sans toucher à la table (vu avec EXPLAIN).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_order_lines', function (Blueprint $table) {
            $table->index(['service_type_id', 'service_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_order_lines', function (Blueprint $table) {
            $table->dropIndex(['service_type_id', 'service_order_id']);
        });
    }
};
