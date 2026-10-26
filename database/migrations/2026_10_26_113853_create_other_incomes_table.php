<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrées hors factures (vente d'occasion, remboursement…) : docs/03-modele-donnees.md §9.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('other_incomes', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->date('received_at')->index();
            $table->foreignId('payment_method_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('other_incomes');
    }
};
