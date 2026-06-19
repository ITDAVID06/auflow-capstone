<?php

namespace App\Modules\FormBuilder\Services;

use App\Modules\FormBuilder\Models\Form;
use Illuminate\Support\Facades\DB;

class FormCodeService
{
    public function nextFamilyCode(): string
    {
        return DB::transaction(function () {
            $maxFamilyCode = Form::withTrashed()
                ->where('form_family_code', 'like', 'AUF-Form-%')
                ->lockForUpdate()
                ->max('form_family_code');

            $maxFamilyNumber = 0;
            if ($maxFamilyCode) {
                preg_match('/AUF-Form-(\d+)/', (string) $maxFamilyCode, $matches);
                $maxFamilyNumber = isset($matches[1]) ? (int) $matches[1] : 0;
            }

            return sprintf('AUF-Form-%05d', $maxFamilyNumber + 1);
        });
    }

    public function buildRevisionCode(string $familyCode, int $version): string
    {
        return sprintf('%s Rev-%02d', $familyCode, $version);
    }

    public function nextCode(): string
    {
        return $this->buildRevisionCode($this->nextFamilyCode(), 1);
    }
}
