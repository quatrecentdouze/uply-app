<?php

declare(strict_types=1);

use App\Incidents\Enums\IncidentEventKind;
use App\Shared\Database\EnumValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('incident_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->timestampTz('occurred_at');
            $table->jsonb('data');
            $table->index(['incident_id', 'occurred_at']);
        });

        DB::statement('ALTER TABLE incident_events ADD CONSTRAINT incident_events_kind_check CHECK (kind IN ('.EnumValues::sqlList(...IncidentEventKind::cases()).'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_events');
    }
};
