<?php

namespace App\Modules\ErrorReports\Models;

use App\Modules\UserManagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorReport extends Model
{
    protected $table = 'tbl_error_reports';

    protected $fillable = [
        'message',
        'stack',
        'url',
        'user_agent',
        'comment',
        'user_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'account_id');
    }
}
