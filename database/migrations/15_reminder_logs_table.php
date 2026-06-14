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
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('reminder_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('channel')->index(); // EMAIL / WHATSAPP / PUSH
            $table->string('status')->index(); // SENT / FAILED / SKIPPED

            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['reminder_id', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
