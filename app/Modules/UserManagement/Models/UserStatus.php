<?php

namespace App\Modules\UserManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserStatus extends Model
{
    protected $table = 'tbl_user_status';

    protected $primaryKey = 'id';

    protected $fillable = ['status_name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_status_id');
    }
}
