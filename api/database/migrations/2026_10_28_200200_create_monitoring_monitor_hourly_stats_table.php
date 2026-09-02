<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_hourly_stats', function (Blueprint $table): void {
            $table->foreignUlid('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('hour_start');
            $table->integer('checks_total');
            $table->integer('checks_up');
            $table->bigInteger('response_ms_sum');
            $table->integer('response_ms_count');
            $table->integer('response_ms_min')->nullable();
            $table->integer('response_ms_max')->nullable();
            $table->primary(['monitor_id', 'hour_start']);
            $table->index('hour_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_hourly_stats');
    }
};
