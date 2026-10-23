<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employés (mécaniciens) et prestations (docs/03-modele-donnees.md §7 et §9).
 * Les salaires des employés seront gérés en phase 10.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->enum('salary_type', ['monthly', 'daily'])->default('monthly');
            $table->decimal('salary_amount', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->unsignedInteger('mileage')->nullable();
            $table->unsignedSmallInteger('immobilization_value')->nullable();
            $table->enum('immobilization_unit', ['hours', 'days'])->nullable();
            $table->text('notes')->nullable();
            $table->text('advice')->nullable();
            $table->enum('status', ['en_cours', 'terminee'])->default('en_cours');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index('date');
            $table->index(['vehicle_id', 'date']);
            $table->index(['status', 'date']);
        });

        Schema::create('service_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('labor_price', 12, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('service_order_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_order_line_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('origin', ['stock', 'client', 'external']);
            $table->foreignId('article_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('designation');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_order_parts');
        Schema::dropIfExists('service_order_lines');
        Schema::dropIfExists('service_orders');
        Schema::dropIfExists('employees');
    }
};
