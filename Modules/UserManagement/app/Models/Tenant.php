<?php

namespace Modules\UserManagement\App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'database',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'database',
        ];
    }
}
