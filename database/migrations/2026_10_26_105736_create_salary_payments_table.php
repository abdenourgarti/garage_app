<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paies (docs/03-modele-donnees.md §9) et rattachement des avances à l'employé.
 * carried_in : avances du mois précédent qui dépassaient le salaire, reprises sur ce mois.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('payment_method_id')->constrained()->restrictOnDelete();
        });

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->char('period', 7);
            $table->unsignedSmallInteger('days_worked')->nullable();
            $table->decimal('base_amount', 12, 2);
            $table->decimal('advances_total', 12, 2)->default(0);
            $table->decimal('carried_in', 12, 2)->default(0);
            $table->decimal('net_paid', 12, 2);
            $table->date('paid_at');
            $table->foreignId('payment_method_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_payments');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
