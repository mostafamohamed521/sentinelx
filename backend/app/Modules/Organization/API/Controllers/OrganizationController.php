<?php

namespace App\Modules\Organization\API\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\API\Requests\UpdateOrganizationRequest;
use App\Modules\Organization\Application\UpdateOrganizationAction;
use App\Modules\Organization\Infrastructure\Persistence\OrganizationRepository;
use App\Modules\Organization\Presentation\OrganizationResource;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    // GET /api/v1/organization — Owner, Admin, Member
    public function show(Request $request, OrganizationRepository $organizations): OrganizationResource
    {
        return new OrganizationResource($organizations->findById($this->organizationId($request)));
    }

    // PATCH /api/v1/organization — Owner only (EnsureOwnerRole, route-level)
    public function update(UpdateOrganizationRequest $request, UpdateOrganizationAction $action): OrganizationResource
    {
        $organization = $action->handle(
            organizationId: $this->organizationId($request),
            data: $request->validated(),
            actorUserId: (string) $request->user('api')->id,
        );

        return new OrganizationResource($organization);
    }

    private function organizationId(Request $request): string
    {
        return (string) $request->user('api')->organization_id;
    }
}
