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
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
        
            $table->uuid('user_id')->index();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        
            $table->uuid('category_id')->nullable()->index();
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        
            $table->uuid('person_id')->nullable()->index();
            $table->foreign('person_id')
                ->references('id')
                ->on('people')
                ->nullOnDelete();
        
            $table->decimal('amount', 12, 2)->nullable();
        
            // FIXED / VARIABLE
            $table->string('amount_mode')
                ->default('FIXED');
        
            // INCOME / EXPENSE
            $table->string('type')->index();
        
            $table->text('description')->nullable();
        
            // DAILY / WEEKLY / MONTHLY / YEARLY
            $table->string('frequency')->index();
        
            // ex: a cada 2 meses
            $table->unsignedInteger('interval')
                ->default(1);
        
            $table->date('starts_at');
        
            $table->date('ends_at')->nullable();
        
            $table->date('next_occurrence_at')->nullable();
        
            $table->boolean('is_active')
                ->default(true)
                ->index();
        
            $table->timestamps();
            $table->softDeletes();
        
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'next_occurrence_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
