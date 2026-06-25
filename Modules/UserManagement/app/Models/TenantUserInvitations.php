<?php
 
namespace Modules\UserManagement\App\Models;
 
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
 
class TenantUserInvitations extends Model
{
    use HasFactory;
 
    public $table = 'invitation_users';
 
    protected $dates = [
        'created_at',
        'updated_at',
        'expires_at',
    ];
 
    protected $casts = [
        'expires_at' => 'datetime',
    ];
 
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'token',
        'tenant_id',
        'user_type',
        'status',
        'expires_at',
        'mobile',
        'whatsapp',
        'landline',
        'gender',
        'nationality',
        'experience',
        'languages',
        'specialities',
        'speciality_areas',
        'facebook',
        'instagram',
        'linkedin',
        'twitter',
        'youtube',
        'created_by',
        'created_at',
        'updated_at',
    ];
 
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}