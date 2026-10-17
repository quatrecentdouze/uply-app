<?php

declare(strict_types=1);

use App\Monitoring\Settings\MonitoringSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = app(MonitoringSettings::class)->confirmation->thresholds;

        Schema::table('monitors', function (Blueprint $table): void {
            $table->integer('failure_threshold')->nullable();
            $table->integer('recovery_threshold')->nullable();
        });
        DB::table('monitors')->update([
            'failure_threshold' => $defaults->failuresToConfirmDown,
            'recovery_threshold' => $defaults->successesToConfirmUp,
        ]);
        Schema::table('monitors', function (Blueprint $table): void {
            $table->integer('failure_threshold')->nullable(false)->change();
            $table->integer('recovery_threshold')->nullable(false)->change();
        });

        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_thresholds_check CHECK (failure_threshold >= 1 AND recovery_threshold >= 1)');
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->dropColumn(['failure_threshold', 'recovery_threshold']);
        });
    }
};
