<?php

namespace App\Services\DemoSeeding;

class DemoSeedProfile
{
    public function __construct(
        public readonly string $name,
        public readonly int $formCount,
        public readonly int $deterministicSubmissionCount,
        public readonly int $expandedSubmissionCount,
    ) {}

    public static function fromOptions(string $name, ?int $submissionOverride = null, bool $deterministicOnly = false): self
    {
        $normalized = strtolower($name);

        $profiles = [
            'quick' => ['forms' => 4, 'deterministic' => 24, 'expanded' => 0],
            'medium' => ['forms' => 6, 'deterministic' => 180, 'expanded' => 420],
            'full' => ['forms' => 10, 'deterministic' => 360, 'expanded' => 840],
        ];

        $selected = $profiles[$normalized] ?? $profiles['medium'];

        $deterministic = (int) $selected['deterministic'];
        $expanded = (int) $selected['expanded'];

        if ($submissionOverride !== null && $submissionOverride > 0) {
            $deterministic = min($submissionOverride, $deterministic);
            $expanded = max(0, $submissionOverride - $deterministic);
        }

        if ($deterministicOnly) {
            $expanded = 0;
        }

        return new self(
            name: $normalized,
            formCount: (int) $selected['forms'],
            deterministicSubmissionCount: $deterministic,
            expandedSubmissionCount: $expanded,
        );
    }

    public function totalSubmissionCount(): int
    {
        return $this->deterministicSubmissionCount + $this->expandedSubmissionCount;
    }
}
