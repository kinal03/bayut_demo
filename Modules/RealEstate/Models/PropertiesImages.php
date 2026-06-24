<?php

namespace Modules\RealEstate\Model;

use Illuminate\Database\Eloquent\Model;

class PropertiesImages extends Model
{
    protected $table = 're_properties_images';

    protected $fillable = [
        'properties_id',
        'image_path'
    ];
}