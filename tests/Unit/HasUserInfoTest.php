<?php

use MobileStock\Gatekeeper\Traits\HasUserInfo;

beforeEach(function () {
    $this->class = new class {
        use HasUserInfo;

        public array $userInfo = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];
    };
});

it('returns the correct user info', function () {
    $expected = [
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ];

    expect($this->class->userInfo())->toBe($expected);
});
