<?php

declare(strict_types=1);

use App\Alerting\Enums\DeliveryKind;
use App\Shared\Database\EnumValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_deliveries', function (Blueprint $table): void {
            $table->timestampTz('flapping_since')->nullable();
        });

        $flapping = EnumValues::sqlList(DeliveryKind::FlappingStarted, DeliveryKind::FlappingEnded);
        $withoutIncident = EnumValues::sqlList(DeliveryKind::Test, DeliveryKind::CertificateExpiring, DeliveryKind::FlappingStarted, DeliveryKind::FlappingEnded);
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_kind_check');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_kind_check CHECK (kind IN ('.EnumValues::sqlList(...DeliveryKind::cases()).'))');
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_incident_check');
        DB::statement("ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_incident_check CHECK ((kind IN ({$withoutIncident})) = (incident_id IS NULL))");
        DB::statement("ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_flapping_check CHECK ((kind IN ({$flapping})) = (flapping_since IS NOT NULL) AND (kind NOT IN ({$flapping}) OR monitor_id IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX alert_deliveries_flapping_unique ON alert_deliveries (alert_channel_id, monitor_id, kind, flapping_since) WHERE kind IN ({$flapping})");
    }

    public function down(): void
    {
        DB::table('alert_deliveries')->whereIn('kind', [DeliveryKind::FlappingStarted->value, DeliveryKind::FlappingEnded->value])->delete();
        DB::statement('DROP INDEX alert_deliveries_flapping_unique');
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_flapping_check');
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_incident_check');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_incident_check CHECK ((kind IN ('.EnumValues::sqlList(DeliveryKind::Test, DeliveryKind::CertificateExpiring).')) = (incident_id IS NULL))');
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_kind_check');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_kind_check CHECK (kind IN ('.EnumValues::sqlList(DeliveryKind::Down, DeliveryKind::Up, DeliveryKind::Test, DeliveryKind::CertificateExpiring, DeliveryKind::Reminder).'))');
        Schema::table('alert_deliveries', function (Blueprint $table): void {
            $table->dropColumn('flapping_since');
        });
    }
};
