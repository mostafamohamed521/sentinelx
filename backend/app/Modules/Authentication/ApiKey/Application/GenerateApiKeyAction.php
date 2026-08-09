<?php

namespace App\Modules\Authentication\ApiKey\Application;

use App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;
use Illuminate\Support\Str;

class GenerateApiKeyAction
{
    /**
     * Generates a new ACTIVE API Key for an Agent. Any prior ACTIVE key for
     * the same Agent is auto-revoked by ApiKey::booted() — see
     * contracts/api-key-format.md §5. The raw key is returned exactly once
     * and is never persisted or retrievable again.
     *
     * @return array{api_key: ApiKey, raw_key: string}
     */
    public function handle(string $agentId): array
    {
        $secret = Str::random(32);
        $rawKey = "sk_live_{$secret}";

        $apiKey = ApiKey::create([
            'agent_id' => $agentId,
            'key_prefix' => 'sk_live_'.substr($secret, 0, 4),
            'key_hash' => hash('sha256', $rawKey),
            'status' => ApiKeyStatus::Active,
        ]);

        return ['api_key' => $apiKey, 'raw_key' => $rawKey];
    }
}
