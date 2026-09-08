<?php

declare(strict_types=1);

use App\Shared\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FrozenClock;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');

function freezeClock(string $moment): FrozenClock
{
    $clock = FrozenClock::at($moment, freezesLaravelTime: true);
    app()->instance(Clock::class, $clock);

    return $clock;
}
