<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock, huiles, achats et dépenses (docs/03-modele-donnees.md §5, §6 et §9).
 * expenses.employee_id sera ajouté avec la table employees (phase 10).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oil_products', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 100);
            $table->string('name', 100)->nullable();
            $table->string('viscosity', 20);
            $table->enum('usage', ['moteur', 'boite']);
            $table->string('approval')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('oil_product_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('oil_format', ['can_5l', 'can_4l', 'can_1l', 'bulk'])->nullable();
            $table->decimal('volume_l', 4, 2)->nullable();
            $table->enum('stock_unit', ['unit', 'liter'])->default('unit');
            $table->string('name');
            $table->string('brand', 100)->nullable();
            $table->string('reference', 100)->nullable();
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('stock_qty', 10, 2)->default(0);
            $table->decimal('alert_threshold', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['oil_product_id', 'oil_format']);
            $table->index('name');
            $table->index('reference');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['purchase_in', 'service_out', 'adjustment', 'can_opened_out', 'can_opened_in', 'cancellation_in']);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->nullableMorphs('source');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['article_id', 'created_at']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['draft', 'ordered', 'received', 'cancelled'])->default('draft');
            $table->date('purchased_at');
            $table->date('received_at')->nullable();
            $table->string('supplier_invoice_ref', 100)->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('document_path')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('proof_path')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->date('spent_at');
            $table->foreignId('payment_method_id')->nullable()->constrained()->restrictOnDelete();
            $table->nullableMorphs('source');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index('spent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('oil_products');
    }
};
