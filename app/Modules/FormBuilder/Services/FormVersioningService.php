<?php

namespace App\Modules\FormBuilder\Services;

use App\Modules\FormBuilder\Models\Form;

class FormVersioningService
{
    /**
     * Remove a trailing " v<number>" suffix from form_name.
     * Examples:
     *  - "Template Test v2" => "Template Test"
     *  - "Template Test"    => "Template Test"
     */
    public function extractBaseName(string $name): string
    {
        return trim(preg_replace('/\s+v\d+$/i', '', $name) ?? $name);
    }

    /**
     * Compute the next version for a base name by checking existing forms.
     * If none found, returns 2 (original is considered v1).
     */
    public function nextVersionForBase(string $baseName): int
    {
        // Look at any form with exact base name OR base name followed by " v#"
        $maxVersion = (int) Form::withTrashed()
            ->where(function ($q) use ($baseName) {
                $q->where('form_name', $baseName)
                    ->orWhere('form_name', 'like', $baseName.' v%');
            })
            ->max('version');

        // If max is 0 (no rows), treat original as v1 and produce v2
        return ($maxVersion > 0 ? $maxVersion : 1) + 1;
    }

    public function nextVersionForForm(Form $form): int
    {
        if ($form->form_family_code) {
            $maxVersion = (int) Form::withTrashed()
                ->where('form_family_code', $form->form_family_code)
                ->max('version');

            return ($maxVersion > 0 ? $maxVersion : (int) $form->version) + 1;
        }

        return $this->nextVersionForBase($this->extractBaseName($form->form_name));
    }
}
