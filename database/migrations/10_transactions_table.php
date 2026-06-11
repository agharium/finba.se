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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
        
            $table->string('status')->default('PAID')->index();
        
            $table->decimal('amount', 12, 2);
            $table->string('type')->index(); // INCOME / EXPENSE
            $table->string('purpose')->nullable()->index(); // TITHE / OFFERING / null
            $table->text('description')->nullable();
        
            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();
        
            $table->foreignUuid('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        
            $table->foreignUuid('person_id')
                ->nullable()
                ->constrained('people')
                ->nullOnDelete();
        
            $table->foreignUuid('loan_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        
            $table->foreignUuid('installment_group_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        
            $table->unsignedInteger('installment_number')->nullable();
        
            $table->foreignUuid('recurring_transaction_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        
            $table->foreignUuid('tithe_calculation_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        
            $table->date('date')->index();
        
            $table->timestamps();
            $table->softDeletes();
        
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'person_id']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
