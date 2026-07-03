<?php

namespace Modules\Location\Models;

use Illuminate\Database\Eloquent\Model;

class States extends Model
{
    protected $table = 'states';
    protected $fillable = [
        'name',
        'state_code',
        'country_id',
        'status',
        'is_default',
    ];
}
