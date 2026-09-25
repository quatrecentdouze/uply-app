<?php

declare(strict_types=1);

function decodedJsonFile(string $path): mixed
{
    $contents = file_get_contents($path);

    return json_decode($contents === false ? '' : $contents, true, flags: JSON_THROW_ON_ERROR);
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
