<?php

namespace Modules\RealEstate\Model;

use Illuminate\Database\Eloquent\Model;
use App\Models\Countries;
use App\Models\States;
use App\Models\Cities;

class Properties extends Model
{
    protected $table = 're_properties';

    protected $fillable = [
        'name',
        'permalink',
        'tenant_id',
        'created_by',
        'user_type',
        'type',
        'description',
        'content',
        'location',
        'total_bedroom',
        'total_bathroom',
        'total_floor',
        'square',
        'price',
        'currency',
        'is_featured',
        'status',
        'moderation_status',
        'reject_reason',
        'expire_date',
        'auto_renew',
        'never_expired',
        'latitude',
        'longitude',
        'zip_code',
        'views',
        'country_id',
        'state_id',
        'city_id',
        'unique_id',
        'private_notes',
        'video_url',
        'video_thumbnail',
        'agency_id',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'auto_renew' => 'boolean',
        'never_expired' => 'boolean',
        'price' => 'decimal:2',
        'square' => 'float',
        'expire_date' => 'date',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];


    public function getFullUrlAttribute()
    {
        return url('/properties/' . $this->tenant_id . '/' . $this->permalink);
    }

    public function country()
    {
        return $this->belongsTo(Countries::class, 'country_id');
    }

    public function state()
    {
        return $this->belongsTo(States::class, 'state_id');
    }

    public function city()
    {
        return $this->belongsTo(Cities::class, 'city_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Categories::class, 're_property_categories');
    }

    public function features()
    {
        return $this->belongsToMany(Features::class, 're_property_features');
    }

    public function facilities()
    {
        return $this->hasMany(PropertyFacilities::class, 'properties_id');
    }

    public function images()
    {
        return $this->hasMany(PropertiesImages::class, 'properties_id');
    }

    public function floorplans()
    {
        return $this->hasMany(PropertyFloorplan::class, 'properties_id');
    }

    public function customFields()
    {
        return $this->hasMany(PropertiesCustomFields::class, 'properties_id');
    }

    public function toSearchableArray(): array
    {
        return [

            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'permalink' => $this->permalink,
            'description' => strip_tags($this->description),
            'content' => strip_tags($this->content),
            'type' => $this->type,
            'status' => $this->status,
            'price' => (float) $this->price,
            'square' => (float) $this->square,
            'total_bedroom' => $this->total_bedroom,
            'total_bathroom' => $this->total_bathroom,
            'total_floor' => $this->total_floor,
            'location_text' => $this->location,
            'zip_code' => $this->zip_code,
            'views' => $this->views,
            'is_featured' => $this->is_featured,
            'location' => [
                'lat' => (float) $this->latitude,
                'lon' => (float) $this->longitude,
            ],
            'country' => optional($this->country)->name,
            'state' => optional($this->state)->name,
            'city' => optional($this->city)->name,
            'categories' => $this->categories
                ->pluck('name')
                ->toArray(),
            'features' => $this->features
                ->pluck('name')
                ->toArray(),
            'facilities' => $this->facilities
                ->pluck('facility_name')
                ->toArray(),
            'images' => $this->images
                ->pluck('image')
                ->toArray(),
            'created_at' => optional($this->created_at)
                ->toISOString(),
        ];
    }
}