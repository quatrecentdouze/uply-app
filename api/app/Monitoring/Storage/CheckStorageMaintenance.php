<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Monitoring\Rules\CheckStorageRules;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Values\MaintenanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class CheckStorageMaintenance
{
    private const string BOUND_FORMAT = 'Y-m-d H:i:sP';

    public function __construct(
        private ConnectionInterface $database,
        private MonitoringSettings $settings,
    ) {}

    public function run(CarbonImmutable $now): MaintenanceReport
    {
        $retention = $this->settings->retention;
        $plan = CheckStorageRules::partitionPlan($now, $this->existingPartitions(), $retention->partitionsAheadDays, $retention->rawResultsDays);

        foreach ($plan->daysToCreate as $day) {
            if ($day instanceof CarbonImmutable) {
                $this->createPartition($day);
            }
        }
        foreach ($plan->partitionsToDrop as $name) {
            if (is_string($name)) {
                $this->database->statement("DROP TABLE IF EXISTS {$name}");
            }
        }
        $purged = $this->database->table('monitor_hourly_stats')
            ->where('hour_start', '<', CheckStorageRules::hourlyStatsCutoff($now, $retention->hourlyStatsDays))
            ->delete();

        return new MaintenanceReport(count($plan->daysToCreate), count($plan->partitionsToDrop), $purged);
    }

    public function existingPartitions(): array
    {
        $rows = $this->database->select(<<<'SQL'
            SELECT child.relname AS name
            FROM pg_inherits
            JOIN pg_class parent ON parent.oid = pg_inherits.inhparent
            JOIN pg_class child ON child.oid = pg_inherits.inhrelid
            JOIN pg_namespace schema ON schema.oid = parent.relnamespace
            WHERE parent.relname = 'check_results' AND schema.nspname = current_schema()
            ORDER BY child.relname
            SQL);

        $names = [];
        foreach ($rows as $row) {
            if (is_object($row) && isset($row->name) && is_string($row->name)) {
                $names[] = $row->name;
            }
        }

        return $names;
    }

    private function createPartition(CarbonImmutable $day): void
    {
        $name = CheckStorageRules::partitionName($day);
        $from = $day->format(self::BOUND_FORMAT);
        $to = $day->addDay()->format(self::BOUND_FORMAT);
        $this->database->statement("CREATE TABLE IF NOT EXISTS {$name} PARTITION OF check_results FOR VALUES FROM ('{$from}') TO ('{$to}')");
    }
}
