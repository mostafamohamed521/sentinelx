<?php

namespace App\Modules\Alert\Application;

use App\Modules\Alert\Domain\AlertPolicy;
use App\Modules\Alert\Domain\Events\AlertAcknowledged;
use App\Modules\Alert\Domain\Exceptions\AlertAlreadyAcknowledgedException;
use App\Modules\Alert\Domain\Exceptions\AlertNotFoundException;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Alert\Infrastructure\Persistence\AlertRepository;

class AcknowledgeAlertAction
{
    public function __construct(
        private readonly AlertRepository $alerts,
        private readonly AlertPolicy $policy,
    ) {}

    /**
     * @throws AlertNotFoundException
     * @throws AlertAlreadyAcknowledgedException
     */
    public function handle(string $organizationId, string $alertId, string $userId): Alert
    {
        $alert = $this->alerts->findById($alertId, $organizationId);

        if (! $alert) {
            throw new AlertNotFoundException;
        }

        $this->policy->ensureCanBeAcknowledged($alert->status);

        $alert = $this->alerts->acknowledge($alertId, $userId, now());

        AlertAcknowledged::dispatch($alertId, $organizationId, $userId);

        return $alert;
    }
}
