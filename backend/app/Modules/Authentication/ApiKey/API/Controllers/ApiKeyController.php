<?php

namespace App\Modules\Authentication\ApiKey\API\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authentication\ApiKey\Application\RotateApiKeyAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    // POST /api/v1/agents/{agentId}/rotate-api-key
    public function rotate(Request $request, string $agentId, RotateApiKeyAction $action): JsonResponse
    {
        $organizationId = (string) $request->user('api')->organization_id;
        $actorUserId = (string) $request->user('api')->id;

        $result = $action->handle($organizationId, $agentId, $actorUserId);

        return response()->json([
            'data' => [
                'key_prefix' => $result['api_key']->key_prefix,
                'raw_key' => $result['raw_key'],
                'status' => $result['api_key']->status,
                'created_at' => $result['api_key']->created_at,
            ],
        ], 201);
    }
}
