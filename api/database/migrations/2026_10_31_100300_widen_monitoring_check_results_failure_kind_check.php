<?php

declare(strict_types=1);

use App\Monitoring\Enums\FailureKind;
use App\Shared\Database\EnumValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->allow(...FailureKind::cases());
    }

    public function down(): void
    {
        $this->allow(...array_filter(FailureKind::cases(), fn (FailureKind $kind): bool => ! in_array($kind, [FailureKind::KeywordMissing, FailureKind::KeywordFound], true)));
    }

    private function allow(FailureKind ...$kinds): void
    {
        DB::statement('ALTER TABLE check_results DROP CONSTRAINT check_results_failure_kind_check');
        DB::statement('ALTER TABLE check_results ADD CONSTRAINT check_results_failure_kind_check CHECK (failure_kind IN ('.EnumValues::sqlList(...$kinds).'))');
    }
};
