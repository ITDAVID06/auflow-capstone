<?php

namespace App\Modules\FormBuilder\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Requests\SaveFormDraftRequest;
use App\Modules\FormBuilder\Requests\StoreFormRequest;
use App\Modules\FormBuilder\Requests\UpdateFormStatusRequest;
use App\Modules\FormBuilder\Services\FormAuthoringService;
use App\Modules\FormBuilder\Services\FormCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class FormSaveController extends Controller
{
    public function __construct(
        private FormCodeService $codes,
        private FormAuthoringService $authoring,
    ) {}

    public function store(StoreFormRequest $request)
    {
        $data = $request->validated();
        $data['version'] = 1;
        $data['form_family_code'] = $this->codes->nextFamilyCode();
        $data['form_code'] = $this->codes->buildRevisionCode($data['form_family_code'], $data['version']);

        try {
            $form = $this->authoring->create($data, (int) auth()->id());
        } catch (\Throwable $e) {
            \Log::error('Form creation failed', [
                'error' => $e->getMessage(),
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Form creation failed.'], 422)
                : redirect()->back()->withInput()->with('error', 'Form creation failed.');
        }

        return $request->expectsJson()
            ? response()->json(['id' => $form->id])
            : redirect()->route('admin.forms.index')->with('success', 'Form created successfully!');
    }

    public function updateStatus(UpdateFormStatusRequest $request, $id)
    {
        $form = Form::findOrFail($id);

        try {
            $result = $this->authoring->updateStatus($form, $request->validated()['status']);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', $e->errors()['status'][0] ?? 'Form activation failed. No changes were saved.');
        } catch (\Throwable $e) {
            \Log::error('Failed to update form status', [
                'form_id' => $form->id,
                'status' => $request->status,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Form activation failed. No changes were saved.');
        }

        $wasInactive = (bool) ($result['wasActivated'] ?? false);
        $wasActive = (bool) ($result['wasDeactivated'] ?? false);

        if ($wasActive) {
            return redirect()->back()->with('locked_on_inactive', true)->with('success', 'Form set to inactive & locked.');
        }

        return redirect()->back()->with('success', 'Status updated!');
    }

    public function update(StoreFormRequest $request, Form $form): RedirectResponse|JsonResponse
    {
        $form->load('fields');

        $data = $request->validated();

        $this->authoring->update($form, $data);

        return $request->expectsJson()
            ? response()->json(['id' => $form->id])
            : redirect()->route('admin.forms.index')->with('success', 'Form updated successfully!');
    }

    /**
     * Auto-save draft data (no full validation, just raw JSON persistence).
     */
    public function saveDraft(SaveFormDraftRequest $request, Form $form): JsonResponse
    {
        if ($form->is_locked) {
            return response()->json(['message' => 'Form is locked.'], 403);
        }

        $form->update(['draft_data' => $request->validated()['draft_data']]);

        return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
    }
}
