<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_channel_monitor', function (Blueprint $table): void {
            $table->foreignUlid('alert_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('monitor_id')->index()->constrained()->cascadeOnDelete();
            $table->primary(['alert_channel_id', 'monitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_channel_monitor');
    }
};
