<?php

namespace Modules\RealEstate\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyFeatures extends Model
{
    protected $table = 're_property_features';

    protected $fillable = [
        'properties_id',
        'features_id'
    ];

    public function properties()
    {
        return $this->belongsToMany(
            Properties::class,
            're_property_features',
            'features_id',
            'properties_id'
        );
    }
}