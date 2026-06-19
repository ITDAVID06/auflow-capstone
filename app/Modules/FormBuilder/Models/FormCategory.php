<?php

namespace App\Modules\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;

class FormCategory extends Model
{
    protected $table = 'tbl_form_category';

    protected $fillable = ['name', 'slug'];
}
