<?php

declare(strict_types=1);

use App\Shared\Settings\InvalidSettingException;
use App\Shared\Settings\SettingsReader;

function settingsReader(array $values): SettingsReader
{
    return new SettingsReader('config/sample.php', $values);
}

it('reads nested values with their types', function (): void {
    $reader = settingsReader([
        'limits' => ['count' => 3, 'enabled' => true, 'name' => 'uply', 'steps' => [1, 2], 'tags' => ['a', 'b']],
    ]);

    expect($reader->integer('limits.count', 1))->toBe(3)
        ->and($reader->boolean('limits.enabled'))->toBeTrue()
        ->and($reader->text('limits.name'))->toBe('uply')
        ->and($reader->integerList('limits.steps', 1))->toBe([1, 2])
        ->and($reader->textList('limits.tags'))->toBe(['a', 'b']);
});

it('names the file and the key of an invalid value', function (Closure $read, string $key, string $problem): void {
    try {
        $read(settingsReader(['limits' => ['count' => 0, 'enabled' => 'yes', 'name' => ' ', 'steps' => [], 'tags' => [1]]]));
        $this->fail('no exception thrown');
    } catch (InvalidSettingException $exception) {
        expect($exception->source)->toBe('config/sample.php')
            ->and($exception->key)->toBe($key)
            ->and($exception->problem)->toBe($problem)
            ->and($exception->getMessage())->toBe("config/sample.php: {$key} {$problem}");
    }
})->with([
    'integer below minimum' => [fn (SettingsReader $reader) => $reader->integer('limits.count', 1), 'limits.count', 'must be an integer of at least 1'],
    'boolean as text' => [fn (SettingsReader $reader) => $reader->boolean('limits.enabled'), 'limits.enabled', 'must be true or false'],
    'blank text' => [fn (SettingsReader $reader) => $reader->text('limits.name'), 'limits.name', 'must be a non empty string'],
    'empty list' => [fn (SettingsReader $reader) => $reader->integerList('limits.steps', 1), 'limits.steps', 'must be a non empty list'],
    'list of wrong items' => [fn (SettingsReader $reader) => $reader->textList('limits.tags'), 'limits.tags', 'must only hold non empty strings'],
    'missing key' => [fn (SettingsReader $reader) => $reader->integer('limits.absent', 1), 'limits.absent', 'is missing'],
    'key below a scalar' => [fn (SettingsReader $reader) => $reader->integer('limits.count.deeper', 1), 'limits.count.deeper', 'is missing'],
]);
