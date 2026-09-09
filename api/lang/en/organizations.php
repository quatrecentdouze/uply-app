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
    'roles' => [
        'owner' => 'owner',
        'admin' => 'admin',
        'member' => 'member',
        'viewer' => 'viewer',
    ],
];
