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
            $table->foreignUlid('monitor_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->timestampTz('certificate_expires_at')->nullable();
            $table->integer('certificate_days')->nullable();
            $table->string('kind', 32)->change();
        });

        $withoutIncident = array_filter(DeliveryKind::cases(), fn (DeliveryKind $kind): bool => ! $kind->hasIncident());
        $certificate = EnumValues::sqlList(DeliveryKind::CertificateExpiring);
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_kind_check');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_kind_check CHECK (kind IN ('.EnumValues::sqlList(...DeliveryKind::cases()).'))');
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_test_check');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_incident_check CHECK ((kind IN ('.EnumValues::sqlList(...$withoutIncident).')) = (incident_id IS NULL))');
        DB::statement("ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_certificate_check CHECK ((kind = {$certificate}) = (monitor_id IS NOT NULL AND certificate_expires_at IS NOT NULL AND certificate_days IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX alert_deliveries_certificate_unique ON alert_deliveries (alert_channel_id, monitor_id, certificate_expires_at, certificate_days) WHERE kind = {$certificate}");
    }

    public function down(): void
    {
        DB::table('alert_deliveries')->where('kind', DeliveryKind::CertificateExpiring->value)->delete();
        DB::statement('DROP INDEX alert_deliveries_certificate_unique');
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_certificate_check');
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_incident_check');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_test_check CHECK ((kind = '.EnumValues::sqlList(DeliveryKind::Test).') = (incident_id IS NULL))');
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_kind_check');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_kind_check CHECK (kind IN ('.EnumValues::sqlList(DeliveryKind::Down, DeliveryKind::Up, DeliveryKind::Test).'))');
        Schema::table('alert_deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('monitor_id');
            $table->dropColumn(['certificate_expires_at', 'certificate_days']);
            $table->string('kind', 16)->change();
        });
    }
};
