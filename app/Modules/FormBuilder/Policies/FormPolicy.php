<?php

namespace App\Modules\FormBuilder\Policies;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\UserManagement\Models\User;
use Illuminate\Support\Facades\DB;

class FormPolicy
{
    private function canManageForms(User $user): bool
    {
        return $user->hasPermission('forms.manage');
    }

    /** Any authenticated user with Manage Forms permission can view (checked via middleware). */
    public function view(User $user, Form $form): bool
    {
        return $this->canManageForms($user);
    }

    /**
     * A submitter (student / staff) may view a form only when:
     *   1. The user has the admin forms.manage permission, OR
     *   2. The form is Active + locked, AND the user holds at least one
     *      active, non-expired role that grants a permission the form requires.
     */
    public function viewAsSubmitter(User $user, Form $form): bool
    {
        if ($this->canManageForms($user)) {
            return true;
        }

        if ($form->status !== 'Active' || ! $form->is_locked) {
            return false;
        }

        return DB::table('tbl_user_role')
            ->join('tbl_role_permission', 'tbl_user_role.role_id', '=', 'tbl_role_permission.role_id')
            ->join('tbl_form_permission', 'tbl_role_permission.permission_id', '=', 'tbl_form_permission.permission_id')
            ->where('tbl_user_role.account_id', $user->account_id)
            ->where('tbl_user_role.is_active', 1)
            ->where('tbl_form_permission.form_id', $form->id)
            ->where(function ($q): void {
                $q->whereNull('tbl_user_role.expiry_date')
                    ->orWhere('tbl_user_role.expiry_date', '>', now());
            })
            ->exists();
    }

    /** Creating a form only requires the permission (middleware). */
    public function create(User $user): bool
    {
        return $this->canManageForms($user);
    }

    /** Active and locked forms must be revised instead of edited in place. */
    public function update(User $user, Form $form): bool
    {
        return $this->canManageForms($user)
            && ! $form->trashed()
            && $form->status !== 'Active'
            && ! $form->is_locked;
    }

    /** Can archive any non-archived form. */
    public function archive(User $user, Form $form): bool
    {
        return $this->canManageForms($user) && ! $form->trashed();
    }

    /** Can restore only soft-deleted (archived) forms. */
    public function restore(User $user, Form $form): bool
    {
        return $this->canManageForms($user) && $form->trashed();
    }

    /** Can duplicate any form. */
    public function duplicate(User $user, Form $form): bool
    {
        return $this->canManageForms($user);
    }

    /** Can create a new revision for any form. */
    public function revise(User $user, Form $form): bool
    {
        return $this->canManageForms($user);
    }

    /** Permanent delete — only trashed forms. */
    public function forceDelete(User $user, Form $form): bool
    {
        return false;
    }
}
