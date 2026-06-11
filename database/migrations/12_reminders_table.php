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
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('person_id')
                ->nullable()
                ->constrained('people')
                ->nullOnDelete();

            $table->foreignUuid('loan_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignUuid('recurring_transaction_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('type')->index(); // ANNIVERSARY / LOAN / COMMITMENT / CUSTOM

            $table->date('event_date');

            // Ex: [{"value":1,"unit":"MONTH"},{"value":2,"unit":"WEEK"},{"value":0,"unit":"DAY"}]
            $table->jsonb('notification_offsets');

            // Ex: ["EMAIL"], ["WHATSAPP"], ["EMAIL","WHATSAPP"]
            $table->jsonb('channels');

            $table->dateTime('next_run_at')->index();

            $table->dateTime('last_sent_at')->nullable();

            // null = único, YEARLY / MONTHLY / WEEKLY
            $table->string('recurrence')->nullable()->index();

            $table->boolean('is_active')
                ->default(true)
                ->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'next_run_at']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
