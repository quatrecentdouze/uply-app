<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_window_monitor', function (Blueprint $table): void {
            $table->foreignUlid('maintenance_window_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('monitor_id')->index()->constrained()->cascadeOnDelete();
            $table->primary(['maintenance_window_id', 'monitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_window_monitor');
    }
};
