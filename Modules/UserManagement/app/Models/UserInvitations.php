<?php
 
namespace Modules\UserManagement\App\Models;
 
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\UserManagement\App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
 
class UserInvitations extends Model
{
    use HasFactory;
 
    public $table = 'super_admin_invitations';
 
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
        'user_type',
        'tenant_id',
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

    /**
     * Get the role associated with this invitation
     */

    /**
     * Get the user who created this invitation
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
 
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}