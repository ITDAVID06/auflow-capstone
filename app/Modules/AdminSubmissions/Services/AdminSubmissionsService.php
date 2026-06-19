<?php

namespace App\Modules\AdminSubmissions\Services;

class AdminSubmissionsService
{
    public function __construct(
        protected AdminSubmissionsQueryService $queryService,
        protected AdminSubmissionsOverrideService $overrideService
    ) {}

    public function getAllPendingSystemWide(?string $q = null): array
    {
        return $this->queryService->getAllPendingSystemWide($q);
    }

    public function getSubmissionDetailsForAdmin(int $progressId): array
    {
        return $this->queryService->getSubmissionDetailsForAdmin($progressId);
    }

    public function getAdminSubmissionDetails(int $formId, int $submissionId): ?array
    {
        return $this->queryService->getAdminSubmissionDetails($formId, $submissionId);
    }

    public function adminOverride(
        int $progressId,
        int $adminId,
        string $status,
        ?string $comment = null,
        bool $forceAssignment = true,
        bool $forceReadiness = false
    ): void {
        $this->overrideService->adminOverride(
            $progressId,
            $adminId,
            $status,
            $comment,
            $forceAssignment,
            $forceReadiness
        );
    }

    public function getSystemMetrics(): array
    {
        return $this->queryService->getSystemMetrics();
    }

    public function getSystemSubmissions(?string $statusFilter = null, ?string $q = null, ?int $limit = null): array
    {
        return $this->queryService->getSystemSubmissions($statusFilter, $q, $limit);
    }

    public function getSystemSubmissionsPaginated(?string $statusFilter = null, ?string $q = null, int $perPage = 9, ?string $sort = null, ?string $direction = null): array
    {
        return $this->queryService->getSystemSubmissionsPaginated($statusFilter, $q, $perPage, $sort, $direction);
    }

    public function getSystemSubmissionsForUser(int $accountId, ?string $q = null): array
    {
        return $this->queryService->getSystemSubmissionsForUser($accountId, $q);
    }

    public function getSystemSubmissionsForUserPaginated(
        int $accountId,
        ?string $statusFilter = null,
        ?string $q = null,
        int $perPage = 9
    ): array {
        return $this->queryService->getSystemSubmissionsForUserPaginated($accountId, $statusFilter, $q, $perPage);
    }

    public function getUserMetrics(int $accountId): array
    {
        return $this->queryService->getUserMetrics($accountId);
    }
}
