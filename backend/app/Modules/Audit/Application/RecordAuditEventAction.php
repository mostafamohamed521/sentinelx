<?php

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Domain\ActorType;
use App\Modules\Audit\Infrastructure\Persistence\AuditLog;
use App\Modules\Audit\Infrastructure\Persistence\AuditRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generic — takes an already-normalized {action, resource_type,
 * resource_id, metadata} plus the actor. Every one of this Sprint's ~14
 * listeners funnels through this single Action, which is deliberately the
 * ONE place in the whole module responsible for the Golden Rule
 * (01-overview.md §2 / 03-audit-logging.md §6): a failure while recording
 * an audit event must NEVER cause the triggering action (e.g. Agent
 * creation) to fail or roll back. Centralizing the try/catch here means
 * every listener is safe automatically, rather than needing to duplicate
 * this discipline 14 times.
 */
class RecordAuditEventAction
{
    public function __construct(
        private readonly AuditRepository $audits,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        string $organizationId,
        ActorType $actorType,
        ?string $actorId,
        string $action,
        string $resourceType,
        ?string $resourceId,
        array $metadata = [],
    ): ?AuditLog {
        try {
            return $this->audits->create([
                'organization_id' => $organizationId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to record audit log entry.', [
                'action' => $action,
                'organization_id' => $organizationId,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
