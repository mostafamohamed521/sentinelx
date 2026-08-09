<?php

use App\Modules\Agent\Domain\AgentPolicy;
use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Agent\Domain\Exceptions\AgentAlreadyArchivedException;
use App\Modules\Agent\Domain\Exceptions\AgentNameConflictException;

beforeEach(function () {
    $this->policy = new AgentPolicy;
});

test('an active agent can be archived', function () {
    expect(fn () => $this->policy->ensureCanBeArchived(AgentStatus::Active))->not->toThrow(AgentAlreadyArchivedException::class);
});

test('an already archived agent cannot be archived again', function () {
    expect(fn () => $this->policy->ensureCanBeArchived(AgentStatus::Archived))->toThrow(AgentAlreadyArchivedException::class);
});

test('a name that is not taken is available', function () {
    expect(fn () => $this->policy->ensureNameIsAvailable(nameAlreadyTaken: false))->not->toThrow(AgentNameConflictException::class);
});

test('a name that is already taken is rejected', function () {
    expect(fn () => $this->policy->ensureNameIsAvailable(nameAlreadyTaken: true))->toThrow(AgentNameConflictException::class);
});
