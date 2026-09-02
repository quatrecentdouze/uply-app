<?php

declare(strict_types=1);

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Enums\MonitorState;
use App\Shared\Database\EnumValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('url');
            $table->string('method', 16);
            $table->integer('interval_seconds');
            $table->integer('timeout_seconds');
            $table->jsonb('accepted_status_codes');
            $table->boolean('follow_redirects')->default(true);
            $table->boolean('is_paused')->default(false);
            $table->string('state', 16)->default(MonitorState::Pending->value);
            $table->integer('consecutive_failures')->default(0);
            $table->integer('consecutive_successes')->default(0);
            $table->timestampTz('streak_started_at')->nullable();
            $table->timestampTz('state_changed_at')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('next_check_at');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_method_check CHECK (method IN ('.EnumValues::sqlList(...HttpMethod::cases()).'))');
        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_state_check CHECK (state IN ('.EnumValues::sqlList(...MonitorState::cases()).'))');
        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_durations_check CHECK (interval_seconds > 0 AND timeout_seconds > 0)');
        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_streaks_check CHECK (consecutive_failures >= 0 AND consecutive_successes >= 0)');
        DB::statement('CREATE INDEX monitors_due_index ON monitors (next_check_at) WHERE NOT is_paused');
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
