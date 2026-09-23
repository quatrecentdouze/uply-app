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
        Schema::create('status_page_monitors', function (Blueprint $table): void {
            $table->foreignUlid('status_page_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('monitor_id')->index()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->integer('position');
            $table->primary(['status_page_id', 'monitor_id']);
        });

        DB::statement('ALTER TABLE status_page_monitors ADD CONSTRAINT status_page_monitors_position_check CHECK (position >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('status_page_monitors');
    }
};
