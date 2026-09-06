<?php

declare(strict_types=1);

use App\Alerting\Enums\ChannelType;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Settings\AlertingSettings;
use App\Shared\Settings\InvalidSettingException;
use Illuminate\Support\Facades\DB;

it('stores the channel settings encrypted', function (): void {
    $channel = AlertChannel::factory()->discord('https://discord.com/api/webhooks/42/secret-token')->create();
    $settings = app(AlertingSettings::class);

    $raw = (string) DB::table('alert_channels')->where('id', $channel->id)->value('settings');

    expect($raw)->not->toContain('secret-token')
        ->and($raw)->not->toContain('discord.com')
        ->and($channel->fresh()?->webhookUrl($settings->webhooks->discord)->value)->toBe('https://discord.com/api/webhooks/42/secret-token')
        ->and($channel->toArray())->not->toHaveKey('settings');
});

it('reads the recipients of an email channel', function (): void {
    $channel = AlertChannel::factory()->create(['settings' => [AlertChannel::RECIPIENTS_SETTING => ['Ops@Example.com']]]);

    expect($channel->type)->toBe(ChannelType::Email)
        ->and($channel->recipients(1, 10)->addresses)->toBe(['ops@example.com']);
});

it('resolves the parsed alerting settings and names a broken key', function (): void {
    expect(app(AlertingSettings::class)->delivery->maxAttempts)->toBe(5);

    config(['alerting.delivery.backoff_seconds' => 'soon']);
    app()->forgetInstance(AlertingSettings::class);

    expect(fn () => app(AlertingSettings::class))->toThrow(InvalidSettingException::class, 'config/alerting.php: delivery.backoff_seconds');
});

it('ships every alert text as a string', function (): void {
    $texts = trans('alerting');

    expect($texts)->toBeArray();
    array_walk_recursive($texts, fn (mixed $text) => expect($text)->toBeString()->not->toBeEmpty());
    expect(trans('alerting.down.title', ['name' => 'Billing API']))->toBe('Billing API is down');
});
