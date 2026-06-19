<?php

namespace App\Modules\WorkflowBuilder\Services;

use App\Modules\WorkflowBuilder\Models\Workflow;

class WorkflowVersioningService
{
    /**
     * Normalize to a base name by removing trailing " v<number>" or " (Copy[ N])".
     * Examples:
     *  - "Clearance Flow v3"     -> "Clearance Flow"
     *  - "Clearance Flow (Copy)" -> "Clearance Flow"
     *  - "Clearance Flow (Copy 2) v4" -> "Clearance Flow"
     */
    public function extractBaseName(string $name): string
    {
        // strip "(Copy)" or "(Copy N)" at the end
        $name = preg_replace('/\s*\(Copy(?:\s+\d+)?\)\s*$/i', '', $name) ?? $name;
        // strip " v<number>" at the end
        $name = preg_replace('/\s+v\d+\s*$/i', '', $name) ?? $name;

        return trim($name);
    }

    /**
     * Compute next version by scanning workflow_name values that match the base.
     * We DO NOT require a version column; we infer it from the name.
     * "<base>" counts as v1; "<base> vN" counts as vN.
     */
    public function nextVersionForBase(string $baseName): int
    {
        $names = Workflow::query()
            ->where(function ($q) use ($baseName) {
                $q->where('workflow_name', $baseName)
                    ->orWhere('workflow_name', 'like', $baseName.' v%');
            })
            ->pluck('workflow_name');

        $max = 0;
        foreach ($names as $n) {
            if (preg_match('/\s+v(\d+)\s*$/i', $n, $m)) {
                $v = (int) $m[1];
                if ($v > $max) {
                    $max = $v;
                }
            } else {
                // a plain "<base>" is v1
                $max = max($max, 1);
            }
        }

        return ($max > 0 ? $max : 1) + 1; // next
    }
}
