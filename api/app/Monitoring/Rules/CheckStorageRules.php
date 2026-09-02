<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Values\PartitionPlan;
use Carbon\CarbonImmutable;

final class CheckStorageRules
{
    private const string PARTITION_PREFIX = 'check_results_';

    private const string PARTITION_DAY_FORMAT = 'Ymd';

    public static function partitionName(CarbonImmutable $day): string
    {
        return self::PARTITION_PREFIX.$day->utc()->format(self::PARTITION_DAY_FORMAT);
    }

    public static function partitionDay(string $partitionName): ?CarbonImmutable
    {
        if (preg_match('/^'.self::PARTITION_PREFIX.'(\d{8})$/', $partitionName, $matches) !== 1) {
            return null;
        }
        $day = CarbonImmutable::createFromFormat('!'.self::PARTITION_DAY_FORMAT, $matches[1], 'UTC');

        return $day instanceof CarbonImmutable && $day->format(self::PARTITION_DAY_FORMAT) === $matches[1] ? $day : null;
    }

    public static function partitionPlan(CarbonImmutable $now, array $existingPartitionNames, int $daysAhead, int $retentionDays): PartitionPlan
    {
        $today = $now->utc()->startOfDay();
        $oldestKept = $today->subDays($retentionDays);

        $daysToCreate = [];
        for ($offset = 0; $offset <= $daysAhead; $offset++) {
            $day = $today->addDays($offset);
            if (! in_array(self::partitionName($day), $existingPartitionNames, true)) {
                $daysToCreate[] = $day;
            }
        }

        $partitionsToDrop = [];
        foreach ($existingPartitionNames as $name) {
            $day = is_string($name) ? self::partitionDay($name) : null;
            if ($day !== null && $day->lessThan($oldestKept)) {
                $partitionsToDrop[] = $name;
            }
        }
        sort($partitionsToDrop);

        return new PartitionPlan($daysToCreate, $partitionsToDrop);
    }

    public static function hourlyStatsCutoff(CarbonImmutable $now, int $retentionDays): CarbonImmutable
    {
        return $now->utc()->startOfHour()->subDays($retentionDays);
    }
}
