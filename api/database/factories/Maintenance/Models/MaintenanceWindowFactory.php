<?php

declare(strict_types=1);

namespace Database\Factories\Maintenance\Models;

use App\Maintenance\Models\MaintenanceWindow;
use Carbon\CarbonImmutable;
use Database\Factories\Organizations\Models\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

final class MaintenanceWindowFactory extends Factory
{
    protected $model = MaintenanceWindow::class;

    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->addHour()->startOfSecond();

        return [
            'organization_id' => OrganizationFactory::new(),
            'name' => fake()->words(3, true),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(2),
            'created_by_user_id' => null,
        ];
    }

    public function between(string $startsAt, string $endsAt): self
    {
        return $this->state(fn (): array => [
            'starts_at' => CarbonImmutable::parse($startsAt, 'UTC'),
            'ends_at' => CarbonImmutable::parse($endsAt, 'UTC'),
        ]);
    }
}
