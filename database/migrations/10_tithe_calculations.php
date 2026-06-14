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
        Schema::create('tithe_calculations', function (Blueprint $table) {
            $table->uuid('id')->primary();
        
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
        
            $table->date('period_start');
            $table->date('period_end');
        
            $table->decimal('base_amount', 12, 2);
            $table->decimal('tithe_amount', 12, 2);
            $table->decimal('offering_target_amount', 12, 2);
            $table->decimal('offering_paid_amount', 12, 2);
            $table->decimal('firstfruits_amount', 12, 2);
        
            $table->timestamps();
            $table->softDeletes();
        
            $table->index(['user_id', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
