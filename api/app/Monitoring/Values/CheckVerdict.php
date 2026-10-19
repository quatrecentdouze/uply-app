<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Enums\FailureKind;

final readonly class CheckVerdict
{
    private function __construct(
        public CheckOutcome $outcome,
        public ?FailureKind $failureKind,
        public ?string $diagnostic,
    ) {}

    public static function up(): self
    {
        return new self(CheckOutcome::Up, null, null);
    }

    public static function down(FailureKind $failureKind, ?string $diagnostic = null): self
    {
        return new self(CheckOutcome::Down, $failureKind, $diagnostic);
    }
}
