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
        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
        
            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();
        
            $table->string('name');
            $table->string('region_code', 3)->nullable();
            $table->string('country_code', 2)->nullable();
        
            $table->timestamps();
            $table->softDeletes();
        
            $table->unique(['user_id', 'name', 'region_code', 'country_code']);
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
