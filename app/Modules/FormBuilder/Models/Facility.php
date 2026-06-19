<?php

namespace App\Modules\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    protected $table = 'tbl_facility';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class, 'facility_id');
    }
}
