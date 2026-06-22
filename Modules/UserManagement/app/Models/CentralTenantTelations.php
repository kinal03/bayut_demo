<?php

namespace Modules\UserManagement\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentralTenantTelations extends Model
{   
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'central_tenant_relations';

    protected $fillable = ['email','tenant_id','status'];
}
