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
            $table->string('type')->index();
            $table->boolean('is_titheable')->default(false);
            $table->text('description')->nullable();
        
            $table->uuid('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        
            $table->uuid('category_id')->nullable()->index();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        
            $table->uuid('person_id')->nullable()->index();
            $table->foreign('person_id')->references('id')->on('people')->nullOnDelete();
        
            $table->uuid('loan_id')->nullable()->index();
            $table->foreign('loan_id')->references('id')->on('loans')->nullOnDelete();
        
            $table->uuid('installment_group_id')->nullable()->index();
            $table->foreign('installment_group_id')->references('id')->on('installment_groups')->nullOnDelete();
        
            $table->unsignedInteger('installment_number')->nullable();
        
            $table->uuid('recurring_transaction_id')->nullable()->index();
            $table->foreign('recurring_transaction_id')->references('id')->on('recurring_transactions')->nullOnDelete();
        
            $table->date('date')->index();
        
            $table->timestamps();
            $table->softDeletes();
        
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'person_id']);
            $table->index(['user_id', 'type']);
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
