<?php

namespace App\Modules\Reports\Models;

use Illuminate\Database\Eloquent\Model;

class ReportView extends Model
{
    protected $table = 'tbl_report_view';

    protected $fillable = [
        'form_id',
        'name',
        'filter_state',
        'created_by',
    ];

    protected $casts = [
        'filter_state' => 'array',
        'form_id' => 'integer',
        'created_by' => 'integer',
    ];
}
