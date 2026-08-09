<?php

use App\Modules\Authentication\Identity\Domain\UserRole;

test('UserRole has exactly Owner, Admin, and Member', function () {
    expect(UserRole::values())->toBe(['OWNER', 'ADMIN', 'MEMBER']);
});

test('UserRole cases map to the expected string values', function () {
    expect(UserRole::Owner->value)->toBe('OWNER')
        ->and(UserRole::Admin->value)->toBe('ADMIN')
        ->and(UserRole::Member->value)->toBe('MEMBER');
});
