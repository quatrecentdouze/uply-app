<?php

declare(strict_types=1);

return [
    'mail' => [
        'greeting' => 'Hello :name,',
        'greeting_anonymous' => 'Hello,',
        'salutation' => 'The Uply team',
        'verify' => [
            'subject' => 'Verify your email address',
            'intro' => 'Confirm this address to start monitoring with Uply.',
            'action' => 'Verify email address',
            'outro' => 'The link is valid for :minutes minutes. If you did not create an Uply account, you can ignore this mail.',
        ],
        'reset' => [
            'subject' => 'Reset your Uply password',
            'intro' => 'Someone asked to reset the password of your Uply account.',
            'action' => 'Choose a new password',
            'outro' => 'The link is valid for :minutes minutes. If you did not ask for it, your password stays the same.',
        ],
        'invitation' => [
            'subject' => ':inviter invited you to :organization on Uply',
            'intro' => ':inviter invited you to join :organization as :role.',
            'action' => 'Open the invitation',
            'outro' => 'The invitation expires on :expires UTC.',
        ],
    ],
    'errors' => [
        'sole_owner' => 'You are the only owner of an organization that has other members. Make another member owner or delete the organization first.',
        'last_owner' => 'An organization always keeps at least one owner.',
        'organization_limit_reached' => 'You already own the most organizations allowed (:max).',
        'role_not_allowed' => 'Your role cannot give or change this role.',
        'invitation_email_mismatch' => 'This invitation was sent to another email address.',
        'invitation_expired' => 'This invitation has expired.',
        'invitation_pending' => 'An invitation is already pending for this email address.',
        'already_member' => 'This person is already a member of the organization.',
        'invitation_limit_reached' => 'This organization already has the most pending invitations allowed (:max).',
    ],
    'roles' => [
        'owner' => 'owner',
        'admin' => 'admin',
        'member' => 'member',
        'viewer' => 'viewer',
    ],
];
