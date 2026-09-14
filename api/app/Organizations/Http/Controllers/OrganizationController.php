<?php

declare(strict_types=1);

namespace App\Organizations\Http\Controllers;

use App\Organizations\Actions\CreateOrganization;
use App\Organizations\Actions\DeleteOrganization;
use App\Organizations\Http\Requests\DeleteOrganizationRequest;
use App\Organizations\Http\Requests\OrganizationNameRequest;
use App\Organizations\Http\Resources\OrganizationResource;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Shared\Http\PageRequest;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class OrganizationController
{
    public function index(PageRequest $request, PaginationSettings $pagination, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        return OrganizationResource::collection(
            Membership::query()
                ->where('user_id', $user->id)
                ->with('organization')
                ->oldest()
                ->orderBy('id')
                ->paginate($request->perPage($pagination)),
        );
    }

    public function store(OrganizationNameRequest $request, CreateOrganization $createOrganization): JsonResponse
    {
        $user = $request->currentUser();
        $organization = $createOrganization($user, $request->name());

        return $this->resource($user, $organization)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Organization $organization, #[CurrentUser] User $user): OrganizationResource
    {
        return $this->resource($user, $organization);
    }

    public function update(OrganizationNameRequest $request, Organization $organization): OrganizationResource
    {
        $organization->update(['name' => $request->name()]);

        return $this->resource($request->currentUser(), $organization);
    }

    public function destroy(DeleteOrganizationRequest $request, Organization $organization, DeleteOrganization $deleteOrganization): Response
    {
        $deleteOrganization($organization);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function resource(User $user, Organization $organization): OrganizationResource
    {
        return new OrganizationResource(
            Membership::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->with('organization')
                ->sole(),
        );
    }
}
