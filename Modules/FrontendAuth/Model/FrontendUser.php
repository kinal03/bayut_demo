<?php

namespace Modules\FrontendAuth\Model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class FrontendUser extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'frontend_user';

    protected $fillable = [
        'name',
        'email',
        'country_code',
        'phone_number',
        'password',
        'whatsapp_number',
        'provider',
        'provider_id',
        'avatar',
        'email_verified_at',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            if (!$user->isForceDeleting()) {
                $user->email =
                    time() . '_' . $user->email;

                $user->saveQuietly();
            }
        });
    }
}