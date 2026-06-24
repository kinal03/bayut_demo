<?php

namespace Modules\RealEstate\Model;

use Illuminate\Database\Eloquent\Model;

class PropertyFeatures extends Model
{
    protected $table = 're_property_features';

    protected $fillable = [
        'properties_id',
        'features_id'
    ];
}