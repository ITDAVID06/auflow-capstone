<?php

namespace App\Modules\WorkflowBuilder\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Requests\StoreWorkflowRequest;
use App\Modules\WorkflowBuilder\Requests\UpdateWorkflowRequest;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkflowManageController extends Controller
{
    protected WorkflowService $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'all';
        $search = $request->string('search')->toString() ?: '';
        $perPage = max(1, min((int) $request->integer('per_page', 9), 100));

        $workflows = $this->workflowService->listWorkflows($status, $search, $perPage);

        $metrics = [
            'total' => Workflow::query()->count(),
            'active' => Workflow::query()->where('status', 'Active')->count(),
            'draft' => Workflow::query()->where('status', 'Draft')->count(),
            'archived' => Workflow::query()->where('status', 'Archived')->count(),
        ];

        return Inertia::render('Workflow/WorkflowManagementPage', [
            'workflows' => $workflows,
            'filters' => compact('search', 'status', 'perPage'),
            'metrics' => $metrics,
        ]);
    }

    public function create(Request $request)
    {
        $initialFormId = $request->filled('form_id')
            ? (int) $request->input('form_id')
            : null;

        return Inertia::render('workflow-builder/WorkflowBuilderPage', [
            'forms' => $this->workflowService->getAvailableForms(),
            'users' => $this->workflowService->getAssignableUsers(),
            'initialFormId' => $initialFormId,
        ]);
    }

    public function show(int $id)
    {
        return response()->json(
            $this->workflowService->getWorkflowDetails($id)
        );
    }

    public function store(StoreWorkflowRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->user()->account_id;

        $workflow = $this->workflowService->createWorkflow($data);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'workflow' => $workflow], 201);
        }

        return redirect()->route('workflows.index')
            ->with('success', 'Workflow created successfully.');
    }

    public function update(UpdateWorkflowRequest $request, int $id)
    {
        $data = $request->validated();
        $data['updated_by'] = auth()->user()->account_id ?? null;

        $workflow = $this->workflowService->updateWorkflow($id, $data);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'workflow' => $workflow]);
        }

        return redirect()->route('workflows.index')
            ->with('success', 'Workflow updated successfully.');
    }

    public function publish(int $id, Request $request)
    {
        $workflow = $this->workflowService->publishWorkflow($id);

        return back()->with('success', 'Workflow published successfully.');
    }

    public function duplicate(int $id, Request $request)
    {
        $copy = $this->workflowService->duplicateWorkflow($id);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'workflow' => $copy], 201);
        }

        return redirect()
            ->route('workflows.index')
            ->with('success', 'Workflow duplicated successfully.');
    }

    public function archive(int $id, Request $request)
    {
        $workflow = $this->workflowService->archiveWorkflow($id);

        // JSON for plain AJAX (axios), redirect for Inertia visits
        if (($request->expectsJson() || $request->ajax()) && ! $request->hasHeader('X-Inertia')) {
            return response()->json(['ok' => true, 'workflow' => $workflow]);
        }

        return back()->with('success', 'Workflow archived successfully.');
    }

    public function enable(int $id, Request $request)
    {
        $workflow = $this->workflowService->enableWorkflow($id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'workflow' => $workflow]);
        }

        return back()->with('success', 'Workflow enabled successfully.');
    }

    public function draft(int $id, Request $request)
    {
        $force = (bool) $request->boolean('force', false);
        $workflow = $this->workflowService->draftWorkflow($id, $force);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'workflow' => $workflow]);
        }

        return back()->with('success', 'Workflow moved to draft.');
    }

    public function readiness(int $id)
    {
        return response()->json(
            $this->workflowService->getWorkflowReadiness($id)
        );
    }

    public function edit(int $id)
    {
        $workflow = \App\Modules\WorkflowBuilder\Models\Workflow::findOrFail($id);

        // Return minimal data for initial page load
        // Heavy canvas data (steps, canvas_data) will be fetched asynchronously
        return Inertia::render('workflow-builder/WorkflowBuilderPage', [
            'workflowId' => $id,
            'workflowBasic' => [
                'id' => $workflow->id,
                'workflow_name' => $workflow->workflow_name,
                'workflow_type' => $workflow->workflow_type,
                'description' => $workflow->description,
                'form_id' => $workflow->form_id,
                'status' => $workflow->status,
            ],
            'forms' => $this->workflowService->getAvailableForms(),
            'users' => $this->workflowService->getAssignableUsers(),
            // URL endpoint for async canvas data fetch
            'canvasDataUrl' => route('workflows.canvas-data', ['id' => $id]),
        ]);
    }

    /**
     * Get workflow canvas data asynchronously (heavy data endpoint).
     * This endpoint returns the full workflow with steps and canvas_data.
     */
    public function getCanvasData(int $id)
    {
        $workflow = $this->workflowService->getWorkflowDetails($id);

        return response()->json([
            'workflow' => $workflow,
        ]);
    }
}
