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
        Schema::table('alert_channels', function (Blueprint $table): void {
            $table->integer('reminder_interval_minutes')->nullable();
        });

        DB::statement('ALTER TABLE alert_channels ADD CONSTRAINT alert_channels_reminder_interval_check CHECK (reminder_interval_minutes IS NULL OR reminder_interval_minutes >= 1)');
    }

    public function down(): void
    {
        Schema::table('alert_channels', function (Blueprint $table): void {
            $table->dropColumn('reminder_interval_minutes');
        });
    }
};
