<?php

namespace App\Modules\Analysis\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown by MLClient when a required piece of ML Engine configuration is
 * missing — currently, only ML_SERVICE_TOKEN. Deliberately distinct from
 * MLCommunicationException: this is a deployment misconfiguration, not a
 * transient transport failure, so it fails loudly and clearly at the point
 * of first use rather than silently sending a request with no
 * Authorization header. See integration audit SECURITY-004.
 */
class MLConfigurationException extends RuntimeException {}
