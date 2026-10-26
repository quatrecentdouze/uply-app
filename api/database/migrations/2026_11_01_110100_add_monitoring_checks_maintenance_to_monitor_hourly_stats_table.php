<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitor_hourly_stats', function (Blueprint $table): void {
            $table->integer('checks_maintenance')->default(0);
        });

        DB::statement('ALTER TABLE monitor_hourly_stats ADD CONSTRAINT monitor_hourly_stats_checks_maintenance_check CHECK (checks_maintenance >= 0)');
    }

    public function down(): void
    {
        Schema::table('monitor_hourly_stats', function (Blueprint $table): void {
            $table->dropColumn('checks_maintenance');
        });
    }
};
