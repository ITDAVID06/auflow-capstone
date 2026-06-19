<?php

namespace App\Modules\FormBuilder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $form): void {
            if (! $form->form_family_code && $form->form_code) {
                $form->form_family_code = $form->form_code;
            }
        });
    }

    protected $table = 'tbl_form';

    protected $appends = ['category_name'];

    protected $fillable = [
        'form_name',
        'form_code',
        'form_family_code',
        'parent_form_id',
        'description',
        'form_category_id',
        'version',
        'revision_effective_at',
        'status',
        'created_by',
        'email_notifications',
        'submission_limit',
        'sensitive_fields',
        'is_locked',
        'draft_data',
    ];

    protected $casts = [
        'version' => 'integer',
        'parent_form_id' => 'integer',
        'revision_effective_at' => 'date',
        'email_notifications' => 'boolean',
        'is_locked' => 'boolean',
        'submission_limit' => 'integer',
        'draft_data' => 'array',
        'sensitive_fields' => 'array',
    ];

    public function fields()
    {
        return $this->hasMany(FormField::class, 'form_id', 'id')->orderBy('field_order');
    }

    public function slots()
    {
        return $this->hasMany(Slot::class, 'form_id');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class, 'form_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(
            \App\Modules\UserManagement\Models\Permission::class,
            'tbl_form_permission',
            'form_id',
            'permission_id'
        );
    }

    public function category()
    {
        return $this->belongsTo(FormCategory::class, 'form_category_id');
    }

    public function parentForm()
    {
        return $this->belongsTo(self::class, 'parent_form_id')->withTrashed();
    }

    public function childRevisions()
    {
        return $this->hasMany(self::class, 'parent_form_id');
    }

    public function getCategoryNameAttribute(): ?string
    {
        return $this->category?->name;
    }

    public function scopeRenderable(Builder $query): Builder
    {
        return $query->where('status', 'Active')
            ->where('is_locked', true);
    }

    public function toSchemaArray(): array
    {
        return [
            'id' => $this->id,
            'form_name' => $this->form_name,
            'form_code' => $this->form_code,
            'form_family_code' => $this->form_family_code,
            'description' => $this->description,
            'version' => $this->version,
            'revision_effective_at' => $this->revision_effective_at?->toDateString(),
            'status' => $this->status,
            'submission_limit' => $this->submission_limit,
            'permissions' => $this->relationLoaded('permissions')
                ? $this->permissions->map(fn ($permission) => [
                    'id' => $permission->id,
                    'permission_name' => $permission->permission_name,
                    'slug' => $permission->slug,
                    'resource' => $permission->resource,
                    'action' => $permission->action,
                ])->values()
                : [],
            'fields' => $this->relationLoaded('fields')
                ? $this->fields->map(fn ($field) => [
                    'id' => $field->id,
                    'field_name' => $field->field_name,
                    'label' => $field->label,
                    'data_type' => $field->data_type,
                    'is_required' => (bool) $field->is_required,
                    'field_order' => (int) $field->field_order,
                    'placeholder' => $field->placeholder,
                    'help_text' => $field->help_text,
                    'options' => $field->options ?? [],
                    'options_meta' => $field->options_meta ?: null,
                    'field_options' => $field->field_options ?? [],
                    'conditions' => $field->conditions ?? [],
                    'use_slots' => (bool) ($field->use_slots ?? false),
                    'require_facility' => (bool) ($field->require_facility ?? false),
                    'date_mode' => $field->date_mode,
                ])->values()
                : [],
        ];
    }
}
