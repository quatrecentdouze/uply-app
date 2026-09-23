<?php

declare(strict_types=1);

namespace Database\Factories\StatusPages\Models;

use App\StatusPages\Models\StatusPage;
use Database\Factories\Organizations\Models\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

final class StatusPageFactory extends Factory
{
    protected $model = StatusPage::class;

    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new(),
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->words(2, true),
            'description' => null,
            'is_published' => false,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => ['is_published' => true]);
    }
}
