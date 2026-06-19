<?php

namespace App\Modules\WorkflowBuilder\Services;

class WorkflowPersistenceService
{
    /**
     * Normalize workflow_settings payload from frontend/editor into a stable backend shape.
     *
     * @param  array<string, mixed>  $workflowSettings
     * @return array<string, mixed>
     */
    public function normalizeWorkflowSettings(array $workflowSettings): array
    {
        $workflowSettings['nodes'] = is_array($workflowSettings['nodes'] ?? null)
            ? $workflowSettings['nodes']
            : [];

        $workflowSettings['edges'] = is_array($workflowSettings['edges'] ?? null)
            ? $workflowSettings['edges']
            : [];

        foreach ($workflowSettings['nodes'] as &$node) {
            if (! is_array($node)) {
                continue;
            }

            $node['data'] = is_array($node['data'] ?? null) ? $node['data'] : [];

            if (isset($node['data']['assigned_user']) && ! isset($node['data']['assigned_account_id'])) {
                $node['data']['assigned_account_id'] = $node['data']['assigned_user'];
                unset($node['data']['assigned_user']);
            }

            if (! array_key_exists('assigned_account_id', $node['data'])) {
                $node['data']['assigned_account_id'] = null;
            }

            if (! isset($node['data']['step_name']) && isset($node['data']['label'])) {
                $node['data']['step_name'] = $node['data']['label'];
            }
        }
        unset($node);

        return $workflowSettings;
    }
}
