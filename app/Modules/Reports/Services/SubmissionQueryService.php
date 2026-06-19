<?php

namespace App\Modules\Reports\Services;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use Illuminate\Database\Eloquent\Builder;

class SubmissionQueryService
{
    public function __construct(
        private readonly ReportColumnRegistry $columnRegistry,
        private readonly ReportQueryBuilderService $queryBuilderService,
    ) {}

    /**
     * Build a filtered submission query ready for pagination or counting.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $formFieldTypes
     * @return Builder<FormSubmission>
     */
    public function buildFilteredSubmissionQuery(int $formId, array $filters, array $formFieldTypes = []): Builder
    {
        $query = FormSubmission::query()
            ->select([
                'id',
                'form_id',
                'account_id',
                'submission_status',
                'current_workflow_status',
                'payload_json',
                'submitted_at',
                'created_at',
            ])
            ->where('form_id', $formId);

        $this->applyRowAccessScope($query);

        if (! empty($filters['date_from'])) {
            $query->whereDate('submitted_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('submitted_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['submission_status'])) {
            $query->whereRaw('LOWER(submission_status) = ?', [strtolower((string) $filters['submission_status'])]);
        }

        if (! empty($filters['account_id'])) {
            $query->where('account_id', (int) $filters['account_id']);
        }

        if (! empty($filters['submitter'])) {
            $this->applySubmitterSearch($query, (string) $filters['submitter']);
        }

        if (! empty($filters['filters']) && is_array($filters['filters'])) {
            $this->queryBuilderService->applyNestedFilters($query, $filters['filters'], $formFieldTypes);
        }

        return $query;
    }

    /**
     * Count filtered submissions without loading any rows.
     *
     * @param  array<string, mixed>  $filters
     */
    public function countFilteredSubmissions(array $filters): int
    {
        $formId = (int) ($filters['form_id'] ?? 0);

        if ($formId <= 0) {
            return 0;
        }

        $form = Form::with(['fields' => fn ($query) => $query->orderBy('field_order')])->findOrFail($formId);
        $formFieldTypes = $this->columnRegistry->resolveFormFieldTypes($form);

        return (clone $this->buildFilteredSubmissionQuery($formId, $filters, $formFieldTypes))->count();
    }

    /**
     * Apply mandatory row-level report visibility based on permission scope.
     */
    private function applyRowAccessScope(Builder $query): void
    {
        $user = auth()->user();

        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (method_exists($user, 'hasPermission') && $user->hasPermission('submissions.override')) {
            return;
        }

        $accountId = (int) ($user->account_id ?? 0);

        if ($accountId <= 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('account_id', $accountId);
    }

    /**
     * Apply the submitter name search filter using individual first_name / last_name
     * LIKE clauses so the database can use column indexes instead of a non-sargable
     * LOWER(CONCAT(...)) expression.
     */
    private function applySubmitterSearch(Builder $query, string $rawTerm): void
    {
        $term = strtolower(trim($rawTerm));

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $outer) use ($term): void {
            if (str_contains($term, ' ')) {
                // "first last" pattern — match first_name AND last_name independently.
                [$first, $last] = explode(' ', $term, 2);
                $first = trim($first);
                $last = trim($last);

                $outer->whereHas('submitter.profile', function (Builder $q) use ($first, $last): void {
                    $q->where('first_name', 'like', "%{$first}%")
                        ->where('last_name', 'like', "%{$last}%");
                })
                    ->orWhereHas('submitter.profile', function (Builder $q) use ($first, $last): void {
                        // Also try reversed order (last first).
                        $q->where('first_name', 'like', "%{$last}%")
                            ->where('last_name', 'like', "%{$first}%");
                    });
            } else {
                // Single token — match against first_name OR last_name.
                $outer->whereHas('submitter.profile', function (Builder $q) use ($term): void {
                    $q->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%");
                });
            }

            // Also check username and email as fallback.
            $outer->orWhereHas('submitter', function (Builder $q) use ($term): void {
                $q->where('username', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        });
    }
}
