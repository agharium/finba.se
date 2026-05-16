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
            
            $table->decimal('amount', 12, 2);
            $table->string('type')->index();
            $table->text('description')->nullable();
            $table->uuid('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->uuid('category_id')->index()->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->uuid('person_id')->nullable();
            $table->foreign('person_id')->references('id')->on('people')->nullOnDelete();
            $table->uuid('loan_id')->nullable();
            $table->foreign('loan_id')->references('id')->on('loans')->nullOnDelete();
            $table->integer('installment')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'type']);

            $table->timestamps();
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
