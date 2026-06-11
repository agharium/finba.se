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

            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->string('type')->index(); // INCOME / EXPENSE

            $table->string('amount_mode')
                ->default('FIXED')
                ->index(); // FIXED / VARIABLE

            $table->decimal('amount', 12, 2)->nullable(); // fixo ou estimado

            $table->foreignUuid('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignUuid('person_id')
                ->nullable()
                ->constrained('people')
                ->nullOnDelete();

            $table->string('frequency')->index(); // WEEKLY / MONTHLY / YEARLY

            $table->date('next_occurrence_at');

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
