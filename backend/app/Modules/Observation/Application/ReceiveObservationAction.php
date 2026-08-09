<?php

namespace App\Modules\Observation\Application;

use App\Modules\Agent\Infrastructure\Persistence\AgentRepository;
use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Domain\Exceptions\MalformedObservationPayloadException;
use App\Modules\Observation\Domain\Exceptions\ObservationValidationFailedException;
use App\Modules\Observation\Domain\ObservationValidator;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Observation\Infrastructure\Persistence\ObservationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;

class ReceiveObservationAction
{
    public function __construct(
        private readonly ObservationRepository $observations,
        private readonly ObservationValidator $validator,
        // Direct use of Agent's concrete Infrastructure repository, not an
        // interface — this is the one narrow, explicitly-documented write
        // this module ever makes outside its own table. See
        // 05-cross-module-boundaries.md §1 and 02-domain.md §3.
        private readonly AgentRepository $agents,
    ) {}

    /**
     * @throws MalformedObservationPayloadException
     * @throws ObservationValidationFailedException
     */
    public function handle(string $agentId, string $organizationId, string $rawBody): Observation
    {
        $payload = $this->decode($rawBody);

        $this->validator->validate($payload);

        return DB::transaction(function () use ($agentId, $organizationId, $payload) {
            $receivedAt = now();

            $observation = $this->observations->create([
                'organization_id' => $organizationId,
                'agent_id' => $agentId,
                'raw_ases_json' => $payload,
                'analysis_status' => AnalysisStatus::Pending,
                'received_at' => $receivedAt,
            ]);

            // The one legitimate write this module makes outside its own
            // table — same transaction, so a rolled-back Observation insert
            // never leaves last_seen_at moved. See 05-cross-module-boundaries.md §1.
            $this->agents->touchLastSeen($agentId, $receivedAt);

            Log::info('Observation received.', [
                'observation_id' => $observation->id,
                'agent_id' => $agentId,
            ]);

            return $observation;
        });
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MalformedObservationPayloadException
     */
    private function decode(string $rawBody): array
    {
        try {
            $payload = json_decode($rawBody, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MalformedObservationPayloadException;
        }

        if (! is_array($payload)) {
            throw new MalformedObservationPayloadException;
        }

        return $payload;
    }
}
