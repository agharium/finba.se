<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->dropForeign(['reminder_id']);
        });

        Schema::rename('reminders', 'commitments');
        Schema::rename('reminder_logs', 'commitment_logs');

        Schema::table('commitments', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->after('description');
            $table->unsignedInteger('sequence')->nullable()->after('amount');
        });

        Schema::table('commitment_logs', function (Blueprint $table) {
            $table->renameColumn('reminder_id', 'commitment_id');
        });

        Schema::table('commitment_logs', function (Blueprint $table) {
            $table->foreign('commitment_id')
                ->references('id')
                ->on('commitments')
                ->cascadeOnDelete();
        });

        // Birthday reminders are out of this domain; deactivate any legacy rows.
        DB::table('commitments')
            ->where('type', 'ANNIVERSARY')
            ->update([
                'type' => 'CUSTOM',
                'is_active' => false,
                'updated_at' => now(),
            ]);

        Schema::create('commitment_transaction', function (Blueprint $table) {
            $table->foreignUuid('commitment_id')
                ->constrained('commitments')
                ->cascadeOnDelete();

            $table->foreignUuid('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);

            $table->primary(['commitment_id', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commitment_transaction');

        Schema::table('commitment_logs', function (Blueprint $table) {
            $table->dropForeign(['commitment_id']);
        });

        Schema::table('commitment_logs', function (Blueprint $table) {
            $table->renameColumn('commitment_id', 'reminder_id');
        });

        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn(['amount', 'sequence']);
        });

        Schema::rename('commitment_logs', 'reminder_logs');
        Schema::rename('commitments', 'reminders');

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->foreign('reminder_id')
                ->references('id')
                ->on('reminders')
                ->cascadeOnDelete();
        });
    }
};
