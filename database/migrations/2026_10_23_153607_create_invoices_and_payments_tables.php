<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Factures et paiements clients (docs/03-modele-donnees.md §8).
 * Une prestation peut avoir plusieurs factures (une annulée puis une nouvelle) : une seule active, garantie par CompleteServiceOrder.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('service_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->date('issued_at');
            $table->decimal('labor_total', 12, 2)->default(0);
            $table->decimal('parts_total', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->enum('status', ['impayee', 'partielle', 'payee', 'annulee'])->default('impayee');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'issued_at']);
            $table->index(['client_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->dateTime('paid_at');
            $table->string('proof_path')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index('paid_at');
        });

        // Les dépenses des pièces extérieures sont désormais rattachées à la prestation (pas de double dépense après une annulation).
        DB::table('expenses')
            ->where('source_type', 'service_order_part')
            ->update([
                'source_type' => 'service_order',
                'source_id' => DB::raw('(select service_order_id from service_order_parts where service_order_parts.id = expenses.source_id)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
    }
};
