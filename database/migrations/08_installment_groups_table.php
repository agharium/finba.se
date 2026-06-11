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
        Schema::create('installment_groups', function (Blueprint $table) {
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
        
            $table->decimal('total_amount', 12, 2);
        
            $table->unsignedInteger('installments_count');
        
            $table->text('description')->nullable();
        
            // data da primeira parcela
            $table->date('first_date');
        
            // PAID / ACTIVE / CANCELED
            $table->string('status')
                ->default('ACTIVE')
                ->index();
        
            $table->timestamps();
            $table->softDeletes();
        
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'first_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installment_groups');
    }
};
