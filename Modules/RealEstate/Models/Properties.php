<?php

namespace Modules\RealEstate\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\RealEstate\Models\PropertiesImages;
use Modules\RealEstate\Models\Features;

class Properties extends Model
{
    protected $table = 're_properties';

    protected $fillable = [
        'title',
        'permalink',
        'tenant_id',
        'created_by',
        'user_type',
        'purpose',
        'price',
        'type',
        'completion_status',
        'furnishing_status',
        'reference_no',
        'trucheck_on',
        'added_on',
        'neighborhood',
        'area_size',
        'total_bedroom',
        'total_bathroom',
        'balcony_size',
        'usage',
        'ownership',
        'parking_availability',
        'description',
        'project_name',
        'developer',
        'project_status',
        'last_inspected',
        'handover_year',
        'handover_quarter',
        'building_name',
        'parking_spaces',
        'building_floors',
        'building_area',
        'swimming_pools',
        'elevators',
        'permit_number',
        'zone_name',
        'registered_agency',
        'rera_orn',
        'agent_brn',
        'location',
        'currency',
        'status',
        'moderation_status',
        'reject_reason',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'area_size' => 'double',
        'balcony_size' => 'double',
        'building_floors' => 'integer',
        'building_area' => 'integer',
        'swimming_pools' => 'integer',
        'elevators' => 'integer',
        'parking_availability' => 'boolean',
        'trucheck_on' => 'date',
        'added_on' => 'date',
        'last_inspected' => 'date',
    ];


    public function getFullUrlAttribute()
    {
        return url('/properties/' . $this->tenant_id . '/' . $this->permalink);
    }

    public function features()
    {
        return $this->belongsToMany(
            Features::class,
            're_property_features',
            'properties_id',
            'features_id'
        );
    }

    public function images()
    {
        return $this->hasMany(PropertiesImages::class, 'properties_id')->orderByDesc('id');
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