<?php

use MobileStock\Gatekeeper\Traits\HasUserInfo;

it('returns the correct user info', function () {
    $class = new class {
        use HasUserInfo;
        public array $userInfo = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];
    };

    $expected = [
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ];

    expect($class->userInfo())->toBe($expected);
});

it('should return the correct auth identifier', function () {
    $class = new class {
        use HasUserInfo;
        public array $userInfo = ['id' => 69];
    };

    expect($class->getAuthIdentifier())->toBe(69);
});
