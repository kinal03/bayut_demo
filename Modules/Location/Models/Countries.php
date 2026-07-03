<?php

namespace Modules\Location\Models;

use Illuminate\Database\Eloquent\Model;

class Countries extends Model
{
    protected $table = 'countries';
    protected $fillable = [
        'name',
        'iso_code',
        'iso3',
        'phone_code',
        'min_length',
        'max_length',
        'currency',
        'currency_symbol',
        'timezones',
        'status',
        'flag',
        'is_default',
    ];
}
