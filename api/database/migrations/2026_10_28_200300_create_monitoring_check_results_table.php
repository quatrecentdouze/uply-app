<?php

declare(strict_types=1);

use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Values\ProbeResult;
use App\Shared\Database\EnumValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $outcomes = EnumValues::sqlList(...CheckOutcome::cases());
        $failureKinds = EnumValues::sqlList(...FailureKind::cases());
        $up = EnumValues::sqlList(CheckOutcome::Up);
        $maxMessage = ProbeResult::MAX_ERROR_MESSAGE_LENGTH;

        DB::statement(<<<SQL
            CREATE TABLE check_results (
                id bigint GENERATED ALWAYS AS IDENTITY,
                monitor_id char(26) NOT NULL REFERENCES monitors (id) ON DELETE CASCADE,
                checked_at timestamptz NOT NULL,
                outcome varchar(16) NOT NULL CHECK (outcome IN ({$outcomes})),
                failure_kind varchar(32) NULL CHECK (failure_kind IN ({$failureKinds})),
                http_status smallint NULL,
                response_ms integer NULL,
                dns_ms integer NULL,
                connect_ms integer NULL,
                tls_ms integer NULL,
                ttfb_ms integer NULL,
                error_message varchar({$maxMessage}) NULL,
                PRIMARY KEY (monitor_id, checked_at, id),
                CHECK ((outcome = {$up}) = (failure_kind IS NULL))
            ) PARTITION BY RANGE (checked_at)
            SQL);

        Artisan::call('monitoring:maintain');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS check_results');
    }
};
