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
        Schema::table('monitors', function (Blueprint $table): void {
            $table->timestampTz('tls_expires_at')->nullable();
            $table->integer('tls_alerted_days')->nullable();
        });

        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_tls_alerted_days_check CHECK (tls_alerted_days IS NULL OR (tls_alerted_days >= 1 AND tls_expires_at IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->dropColumn(['tls_expires_at', 'tls_alerted_days']);
        });
    }
};
