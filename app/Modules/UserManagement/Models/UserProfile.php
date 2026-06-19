<?php

namespace App\Modules\UserManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $table = 'tbl_userprofile';

    protected $primaryKey = 'id';

    protected $fillable = [
        'account_id',
        'first_name', 'last_name', 'middle_name',
        'student_id', 'employee_id',
        'phone', 'address', 'date_of_birth', 'gender', 'profile_picture',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id', 'account_id');
    }

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
