<?php

namespace Modules\Location\Models;

use Illuminate\Database\Eloquent\Model;

class Cities extends Model
{
    protected $table = 'cities';
    protected $fillable = [
        'name',
        'state_id',
        'country_id',
        'status',
        'is_default',
    ];

    public function state()
    {
        return $this->belongsTo(States::class, 'state_id');
    }

    public function country()
    {
        return $this->belongsTo(Countries::class, 'country_id');
    }
}
