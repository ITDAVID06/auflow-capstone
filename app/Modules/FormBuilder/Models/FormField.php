<?php

namespace App\Modules\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    protected $table = 'tbl_formfield';

    protected $fillable = [
        'form_id',
        'field_name',
        'label',
        'data_type',
        'is_required',
        'options',
        'options_meta',
        'field_order',
        'placeholder',
        'help_text',
        'use_slots',
        'require_facility',
        'date_mode',
        'field_options',
        'conditions',
        'is_sensitive',
        'is_publicly_verifiable',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'options' => 'array',
        'options_meta' => 'array',
        'field_options' => 'array',
        'conditions' => 'array',
        'use_slots' => 'boolean',
        'require_facility' => 'boolean',
        'date_mode' => 'string',
        'is_sensitive' => 'boolean',
        'is_publicly_verifiable' => 'boolean',
    ];

    public $timestamps = false;
}
