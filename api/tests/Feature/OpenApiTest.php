<?php

declare(strict_types=1);

use App\Organizations\Settings\OrganizationsSettings;

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
