<?php

namespace Modules\FrontendAuth\Model;

use Illuminate\Database\Eloquent\Model;

class EmailVerification extends Model
{   
    protected $table = 'email_verification';

    protected $fillable = [
        'email',
        'otp',
        'token',
        'type',
        'payload',
        'expires_at',
        'is_used'
    ];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
        'is_used' => 'boolean'
    ];
}