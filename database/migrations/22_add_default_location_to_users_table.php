<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('default_country_code', 2)->nullable()->after('settings');
            $table->string('default_region_code', 3)->nullable()->after('default_country_code');
            $table->foreignUuid('default_city_id')
                ->nullable()
                ->after('default_region_code')
                ->constrained('cities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_city_id');
            $table->dropColumn(['default_country_code', 'default_region_code']);
        });
    }
};
