<?php

declare(strict_types=1);

it('gives the roles with their abilities', function (): void {
    $response = $this->getJson('/api/v1/meta')->assertOk();

    expect($response->json('data.organizations.roles'))->toBe([
        ['id' => 'owner', 'abilities' => ['monitors.view', 'incidents.view', 'status-pages.view', 'alert-channels.view', 'monitors.manage', 'status-pages.manage', 'alert-channels.manage', 'members.manage', 'organization.manage', 'organization.delete']],
        ['id' => 'admin', 'abilities' => ['monitors.view', 'incidents.view', 'status-pages.view', 'alert-channels.view', 'monitors.manage', 'status-pages.manage', 'alert-channels.manage', 'members.manage', 'organization.manage']],
        ['id' => 'member', 'abilities' => ['monitors.view', 'incidents.view', 'status-pages.view', 'alert-channels.view', 'monitors.manage', 'status-pages.manage']],
        ['id' => 'viewer', 'abilities' => ['monitors.view', 'incidents.view', 'status-pages.view', 'alert-channels.view']],
    ])->and($response->json('data.organizations.abilities'))->toHaveCount(10);
});

it('gives the organization limits and lengths', function (): void {
    $this->getJson('/api/v1/meta')
        ->assertJsonPath('data.organizations.limits', [
            'max_owned_organizations' => 10,
            'max_pending_invitations' => 50,
            'invitation_valid_days' => 7,
        ])
        ->assertJsonPath('data.organizations.lengths', [
            'organization_name' => ['min' => 2, 'max' => 80],
            'slug' => ['min' => 3, 'max' => 48],
            'user_name' => ['min' => 1, 'max' => 80],
            'password' => ['min' => 12, 'max' => 255],
        ]);
});
