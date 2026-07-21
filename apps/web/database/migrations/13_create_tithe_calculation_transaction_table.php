<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tithe_calculation_transaction', function (Blueprint $table) {
            $table->foreignUuid('tithe_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->constrained()->cascadeOnDelete();

            $table->primary(['tithe_calculation_id', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tithe_calculation_transaction');
    }
};
