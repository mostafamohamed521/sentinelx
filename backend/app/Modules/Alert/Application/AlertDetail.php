<?php

namespace App\Modules\Alert\Application;

use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Observation\Infrastructure\Persistence\Observation;

/**
 * The composed result GetAlertAction hands to AlertDetailResource — see
 * 06-api-contract.md §2. A plain read-only carrier, not a new entity: Alert
 * still owns none of Prediction's or Observation's data, it just bundles
 * the three already-fetched objects for the Presentation layer.
 */
final readonly class AlertDetail
{
    public function __construct(
        public Alert $alert,
        public Prediction $prediction,
        public Observation $observation,
    ) {}
}
