<?php

declare(strict_types=1);

use App\Monitoring\Settings\MonitoringSettings;
use App\Organizations\Settings\OrganizationsSettings;
use Tests\Support\TwoFactorUser;

function decodedJsonFile(string $path): mixed
{
    $contents = file_get_contents($path);

    return json_decode($contents === false ? '' : $contents, true, flags: JSON_THROW_ON_ERROR);
}

function documentedOperation(string $path, string $method): array
{
    $document = decodedJsonFile(base_path('openapi.json'));
    $operation = is_array($document) ? data_get($document, ['paths', $path, $method]) : null;

    return is_array($operation) ? $operation : [];
}

function documentedJsonBody(string $path, string $method): array
{
    $schema = data_get(documentedOperation($path, $method), 'requestBody.content.application/json.schema');

    return is_array($schema) ? $schema : [];
}

it('keeps openapi.json in step with the routes, requests and resources', function (): void {
    $exported = tempnam(sys_get_temp_dir(), 'uply-openapi-');

    try {
        $this->artisan('scramble:export', ['--path' => $exported])->assertSuccessful();

        expect(decodedJsonFile($exported))->toBe(decodedJsonFile(base_path('openapi.json')));
    } finally {
        if (is_file($exported)) {
            unlink($exported);
        }
    }
});

it('documents the fortify routes next to ours', function (string $path): void {
    expect(array_keys((array) (decodedJsonFile(base_path('openapi.json'))['paths'] ?? [])))->toContain($path);
})->with([
    '/v1/auth/register',
    '/v1/auth/login',
    '/v1/auth/two-factor-challenge',
    '/v1/auth/user/two-factor-authentication',
    '/v1/me',
    '/v1/organizations/{organization}/members/{membership}',
    '/v1/invitations/{token}/accept',
    '/v1/meta',
    '/v1/organizations/{organization}/monitors/{monitor}',
    '/v1/organizations/{organization}/monitors/{monitor}/checks',
    '/v1/organizations/{organization}/monitors/{monitor}/stats',
    '/v1/organizations/{organization}/monitors/{monitor}/alert-channels',
    '/v1/organizations/{organization}/overview',
    '/v1/organizations/{organization}/incidents/{incident}',
    '/v1/organizations/{organization}/alert-channels/{channel}/test',
    '/v1/organizations/{organization}/status-pages/{statusPage}',
    '/v1/public/status-pages/{slug}',
]);

it('documents the sign up body with the rules of the account creation', function (): void {
    $body = documentedJsonBody('/v1/auth/register', 'post');

    expect($body['required'] ?? null)->toEqualCanonicalizing(['name', 'email', 'password', 'password_confirmation'])
        ->and(data_get($body, 'properties.name.maxLength'))->toBe(app(OrganizationsSettings::class)->userNameMaxLength)
        ->and(data_get($body, 'properties.email.format'))->toBe('email')
        ->and(data_get($body, 'properties.password_confirmation.type'))->toBe('string')
        ->and(data_get($body, 'properties.remember.type'))->toBe('boolean');
});

it('documents the password reset body with the confirmation the reset rules ask for', function (): void {
    $body = documentedJsonBody('/v1/auth/reset-password', 'post');

    expect($body['required'] ?? null)->toEqualCanonicalizing(['token', 'email', 'password', 'password_confirmation'])
        ->and(data_get($body, 'properties.email.format'))->toBe('email')
        ->and(data_get($body, 'properties.password_confirmation.type'))->toBe('string');
});

it('documents the channel authorization as the post that echo sends', function (): void {
    $operations = data_get(decodedJsonFile(base_path('openapi.json')), ['paths', '/broadcasting/auth']);
    $body = documentedJsonBody('/broadcasting/auth', 'post');
    $answer = data_get(documentedOperation('/broadcasting/auth', 'post'), 'responses.200.content.application/json.schema');

    expect(is_array($operations) ? array_keys($operations) : [])->toBe(['post'])
        ->and($body['required'] ?? null)->toEqualCanonicalizing(['socket_id', 'channel_name'])
        ->and(data_get($body, 'properties.socket_id.type'))->toBe('string')
        ->and(data_get($body, 'properties.channel_name.type'))->toBe('string')
        ->and(data_get($answer, 'required'))->toBe(['auth'])
        ->and(data_get($answer, 'properties.auth.type'))->toBe('string');
});

it('documents the password of a delete in the json body, never in the url', function (string $path): void {
    $parameters = data_get(documentedOperation($path, 'delete'), 'parameters');
    $body = documentedJsonBody($path, 'delete');

    expect(is_array($parameters) ? array_column($parameters, 'in') : [])->not->toContain('query')
        ->and(data_get(documentedOperation($path, 'delete'), 'requestBody.required'))->toBeTrue()
        ->and($body['required'] ?? null)->toBe(['password'])
        ->and(data_get($body, 'properties.password.type'))->toBe('string');
})->with([
    '/v1/me',
    '/v1/organizations/{organization}',
]);

it('documents the allowed intervals of a monitor as integers', function (string $schema): void {
    $interval = data_get(decodedJsonFile(base_path('openapi.json')), ['components', 'schemas', $schema, 'properties', 'interval_seconds']);

    expect(data_get($interval, 'type'))->toBe('integer')
        ->and(data_get($interval, 'enum'))->toBe(app(MonitoringSettings::class)->intervals->allowedSeconds);
})->with([
    'StoreMonitorRequest',
    'UpdateMonitorRequest',
]);

it('documents the two factor flag of the login answer', function (): void {
    $answer = data_get(documentedOperation('/v1/auth/login', 'post'), 'responses.200.content.application/json.schema');

    expect(data_get($answer, 'required'))->toBe(['two_factor'])
        ->and(data_get($answer, 'properties.two_factor.type'))->toBe('boolean');
});

function jsonTypeOf(mixed $value): string
{
    return match (true) {
        $value === null => 'null',
        is_bool($value) => 'boolean',
        is_int($value) => 'integer',
        is_float($value) => 'number',
        is_string($value) => 'string',
        is_array($value) && array_is_list($value) => 'array',
        default => 'object',
    };
}

function schemaMismatches(mixed $value, mixed $schema, string $path): array
{
    if (! is_array($schema) || $schema === []) {
        return ["{$path} has no schema"];
    }
    $type = jsonTypeOf($value);
    $documentedTypes = (array) ($schema['type'] ?? []);
    if (! in_array($type, $documentedTypes, true) && ! ($type === 'integer' && in_array('number', $documentedTypes, true))) {
        return ["{$path} is {$type}, documented as ".implode('|', array_filter($documentedTypes, 'is_string'))];
    }
    if (is_array($schema['enum'] ?? null) && ! in_array($value, $schema['enum'], true)) {
        return ["{$path} is outside its documented values"];
    }
    if (! is_array($value)) {
        return [];
    }
    if ($type === 'array') {
        return array_merge([], ...array_map(
            fn (int $index): array => schemaMismatches($value[$index], $schema['items'] ?? null, "{$path}.{$index}"),
            array_keys($value),
        ));
    }
    $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
    $missing = array_diff(is_array($schema['required'] ?? null) ? $schema['required'] : [], array_keys($value));

    return array_merge(
        array_map(fn (mixed $key): string => "{$path}.".(is_string($key) ? $key : '?').' is missing', array_values($missing)),
        ...array_map(
            fn (int|string $key): array => schemaMismatches($value[$key], $properties[$key] ?? null, "{$path}.{$key}"),
            array_keys($value),
        ),
    );
}

it('documents the two factor setup answers as fortify serves them', function (): void {
    $this->actingAs(TwoFactorUser::create()->user)->withSession(['auth.password_confirmed_at' => time()]);

    foreach (['/v1/auth/user/two-factor-qr-code', '/v1/auth/user/two-factor-secret-key', '/v1/auth/user/two-factor-recovery-codes'] as $path) {
        $served = $this->getJson("/api{$path}")->assertOk()->json();
        $documented = data_get(documentedOperation($path, 'get'), 'responses.200.content.application/json.schema');

        expect(schemaMismatches($served, $documented, $path))->toBe([])
            ->and(data_get($documented, 'type'))->not->toBeNull();
    }
    expect(data_get(documentedOperation('/v1/auth/user/two-factor-qr-code', 'get'), 'responses.200.content.application/json.schema.required'))->toBe(['svg', 'url'])
        ->and(data_get(documentedOperation('/v1/auth/user/two-factor-secret-key', 'get'), 'responses.200.content.application/json.schema.properties.secretKey.type'))->toBe('string')
        ->and(data_get(documentedOperation('/v1/auth/user/two-factor-recovery-codes', 'get'), 'responses.200.content.application/json.schema.items.type'))->toBe('string')
        ->and(documentedOperation('/v1/auth/user/two-factor-recovery-codes', 'post')['responses']['200'] ?? null)->toBe(['description' => '']);
});

it('documents every section of meta as the api serves it', function (): void {
    $served = $this->getJson('/api/v1/meta')->assertOk()->json();
    $documented = data_get(documentedOperation('/v1/meta', 'get'), 'responses.200.content.application/json.schema');

    expect(schemaMismatches($served, $documented, 'meta'))->toBe([])
        ->and(data_get($documented, 'properties.data.required'))->toBe(array_keys((array) data_get($served, 'data')));
});
