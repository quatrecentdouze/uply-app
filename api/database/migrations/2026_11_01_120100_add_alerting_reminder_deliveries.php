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
            $table->integer('reminder_number')->nullable();
            $table->dropUnique(['alert_channel_id', 'incident_id', 'kind']);
        });

        $incidentAlerts = EnumValues::sqlList(DeliveryKind::Down, DeliveryKind::Up);
        $reminder = EnumValues::sqlList(DeliveryKind::Reminder);
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_kind_check');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_kind_check CHECK (kind IN ('.EnumValues::sqlList(DeliveryKind::Down, DeliveryKind::Up, DeliveryKind::Test, DeliveryKind::CertificateExpiring, DeliveryKind::Reminder).'))');
        DB::statement("ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_reminder_check CHECK ((kind = {$reminder}) = (reminder_number IS NOT NULL) AND (reminder_number IS NULL OR reminder_number >= 1))");
        DB::statement("CREATE UNIQUE INDEX alert_deliveries_incident_unique ON alert_deliveries (alert_channel_id, incident_id, kind) WHERE kind IN ({$incidentAlerts})");
        DB::statement("CREATE UNIQUE INDEX alert_deliveries_reminder_unique ON alert_deliveries (alert_channel_id, incident_id, reminder_number) WHERE kind = {$reminder}");
    }

    public function down(): void
    {
        DB::table('alert_deliveries')->where('kind', DeliveryKind::Reminder->value)->delete();
        DB::statement('DROP INDEX alert_deliveries_reminder_unique');
        DB::statement('DROP INDEX alert_deliveries_incident_unique');
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_reminder_check');
        DB::statement('ALTER TABLE alert_deliveries DROP CONSTRAINT alert_deliveries_kind_check');
        DB::statement('ALTER TABLE alert_deliveries ADD CONSTRAINT alert_deliveries_kind_check CHECK (kind IN ('.EnumValues::sqlList(DeliveryKind::Down, DeliveryKind::Up, DeliveryKind::Test, DeliveryKind::CertificateExpiring).'))');
        Schema::table('alert_deliveries', function (Blueprint $table): void {
            $table->dropColumn('reminder_number');
            $table->unique(['alert_channel_id', 'incident_id', 'kind']);
        });
    }
};
