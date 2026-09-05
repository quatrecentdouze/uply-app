<?php

declare(strict_types=1);

use App\Monitoring\Enums\FailureKind;
use App\Shared\Database\EnumValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->string('cause_failure_kind', 32)->nullable();
            $table->smallInteger('cause_http_status')->nullable();
            $table->text('cause_message')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE incidents ADD CONSTRAINT incidents_cause_failure_kind_check CHECK (cause_failure_kind IN ('.EnumValues::sqlList(...FailureKind::cases()).'))');
        DB::statement('ALTER TABLE incidents ADD CONSTRAINT incidents_resolution_check CHECK (resolved_at IS NULL OR resolved_at >= started_at)');
        DB::statement('CREATE UNIQUE INDEX incidents_open_monitor_unique ON incidents (monitor_id) WHERE resolved_at IS NULL');
        DB::statement('CREATE INDEX incidents_organization_started_index ON incidents (organization_id, started_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
