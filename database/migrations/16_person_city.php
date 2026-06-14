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
        Schema::create('person_city', function (Blueprint $table) {
            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();
        
            $table->foreignUuid('person_id')
                ->constrained('people')
                ->cascadeOnDelete();
        
            $table->foreignUuid('city_id')
                ->constrained()
                ->cascadeOnDelete();
        
            $table->primary(['person_id', 'city_id']);
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
