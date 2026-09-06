<?php

declare(strict_types=1);

namespace Database\Factories\Alerting\Models;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Models\AlertChannel;
use Database\Factories\Organizations\Models\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

final class AlertChannelFactory extends Factory
{
    protected $model = AlertChannel::class;

    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new(),
            'type' => ChannelType::Email,
            'name' => fake()->words(2, true),
            'settings' => [AlertChannel::RECIPIENTS_SETTING => [fake()->unique()->safeEmail()]],
            'is_enabled' => true,
        ];
    }

    public function discord(string $url = 'https://discord.com/api/webhooks/1234567890/discord-test-token'): self
    {
        return $this->state(fn (): array => ['type' => ChannelType::Discord, 'settings' => [AlertChannel::WEBHOOK_URL_SETTING => $url]]);
    }

    public function slack(string $url = 'https://hooks.slack.com/services/T000/B000/slack-test-token'): self
    {
        return $this->state(fn (): array => ['type' => ChannelType::Slack, 'settings' => [AlertChannel::WEBHOOK_URL_SETTING => $url]]);
    }

    public function disabled(): self
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }
}
