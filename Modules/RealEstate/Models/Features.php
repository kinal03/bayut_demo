<?php

namespace Modules\RealEstate\Models;

use Illuminate\Database\Eloquent\Model;

class Features extends Model
{
    protected $table = 're_features';

    protected $fillable = [
        'name',
        'arabic_name',
        'icon',
        'status',
    ];
}