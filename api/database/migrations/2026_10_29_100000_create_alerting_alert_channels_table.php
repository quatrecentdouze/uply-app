<?php

declare(strict_types=1);

use App\Alerting\Enums\ChannelType;
use App\Alerting\Enums\DisabledReason;
use App\Shared\Database\EnumValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_channels', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->index()->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('name');
            $table->text('settings');
            $table->boolean('is_enabled')->default(true);
            $table->string('disabled_reason', 32)->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE alert_channels ADD CONSTRAINT alert_channels_type_check CHECK (type IN ('.EnumValues::sqlList(...ChannelType::cases()).'))');
        DB::statement('ALTER TABLE alert_channels ADD CONSTRAINT alert_channels_disabled_reason_check CHECK (disabled_reason IN ('.EnumValues::sqlList(...DisabledReason::cases()).'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_channels');
    }
};
