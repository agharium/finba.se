<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('settings')->default('{}');
            } else {
                $table->json('settings')->default('{}');
            }
        });

        DB::table('users')->orderBy('created_at')->lazyById()->each(function (object $user): void {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'settings' => json_encode([
                        'advanced' => (bool) ($user->is_advanced ?? false),
                        'tither' => (bool) ($user->is_tither ?? false),
                        'accounts_receivable' => (bool) ($user->uses_accounts_receivable ?? false),
                    ]),
                ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_advanced', 'is_tither', 'uses_accounts_receivable']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_advanced')->default(false);
            $table->boolean('is_tither')->default(false);
            $table->boolean('uses_accounts_receivable')->default(false);
        });

        DB::table('users')->orderBy('created_at')->lazyById()->each(function (object $user): void {
            $settings = json_decode($user->settings ?? '{}', true) ?? [];

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'is_advanced' => (bool) ($settings['advanced'] ?? false),
                    'is_tither' => (bool) ($settings['tither'] ?? false),
                    'uses_accounts_receivable' => (bool) ($settings['accounts_receivable'] ?? false),
                ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
