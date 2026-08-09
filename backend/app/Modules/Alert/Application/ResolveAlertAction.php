<?php

namespace App\Modules\Alert\Application;

use App\Modules\Alert\Domain\AlertPolicy;
use App\Modules\Alert\Domain\Events\AlertResolved;
use App\Modules\Alert\Domain\Exceptions\AlertAlreadyResolvedException;
use App\Modules\Alert\Domain\Exceptions\AlertNotFoundException;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Alert\Infrastructure\Persistence\AlertRepository;

class ResolveAlertAction
{
    public function __construct(
        private readonly AlertRepository $alerts,
        private readonly AlertPolicy $policy,
    ) {}

    /**
     * @throws AlertNotFoundException
     * @throws AlertAlreadyResolvedException
     */
    public function handle(string $organizationId, string $alertId, string $userId): Alert
    {
        $alert = $this->alerts->findById($alertId, $organizationId);

        if (! $alert) {
            throw new AlertNotFoundException;
        }

        $this->policy->ensureCanBeResolved($alert->status);

        $alert = $this->alerts->resolve($alertId, $userId, now());

        AlertResolved::dispatch($alertId, $organizationId, $userId);

        return $alert;
    }
}
