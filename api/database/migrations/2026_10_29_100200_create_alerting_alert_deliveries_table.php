<?php

declare(strict_types=1);

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Enums\DeliveryStatus;
use App\Alerting\Values\DeliveryDecision;
use App\Shared\Database\EnumValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alert_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('incident_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('status', 16)->default(DeliveryStatus::Pending->value);
            $table->integer('attempts')->default(0);
            $table->string('last_error', DeliveryDecision::MAX_LAST_ERROR_LENGTH)->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
            $table->unique(['alert_channel_id', 'incident_id', 'kind']);
        });

        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_kind_check CHECK (kind IN ('.EnumValues::sqlList(...DeliveryKind::cases()).'))');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_status_check CHECK (status IN ('.EnumValues::sqlList(...DeliveryStatus::cases()).'))');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_test_check CHECK ((kind = '.EnumValues::sqlList(DeliveryKind::Test).') = (incident_id IS NULL))');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_attempts_check CHECK (attempts >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_deliveries');
    }
};
