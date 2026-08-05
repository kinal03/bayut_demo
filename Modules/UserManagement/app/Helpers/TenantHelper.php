<?php

use Illuminate\Support\Facades\DB;
use Modules\UserManagement\App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

if (!function_exists('setTenantConnection')) {

    function setTenantConnection($user)
    {
        // Default: central DB
        tenancy()->end();

        if (!$user) {
            return;
        }

        if (in_array($user->user_type, ['agency', 'agent'])) {
            $tenant = Tenant::find($user->tenancy_id);

            if (!$tenant) {
                throw new \Exception("Tenant not found");
            }

            // dynamic DB connection
            $connection = config('database.connections.mysql');

            $connection['database'] = $tenant->database;

            config([
                'database.connections.tenant_dynamic' => $connection,
            ]);

            DB::purge('tenant_dynamic');
            DB::reconnect('tenant_dynamic');

            config(['database.default' => 'tenant_dynamic']);
        } else {
            config(['database.default' => 'mysql']); // central
        }
    }
}

if (!function_exists('getTenantConnection')) {

    function getTenantConnection($tenant)
    {
        // Base the tenant connection on the real 'mysql' connection config
        // (resolved via config(), not env()) so this keeps working when
        // config is cached (php artisan config:cache), where env() calls
        // outside config/*.php return null.
        $connection = config('database.connections.mysql');

        $connection['database'] = $tenant->database;

        config([
            'database.connections.tenant_mysql' => $connection,
        ]);

        DB::purge('tenant_mysql');
        DB::reconnect('tenant_mysql');

        return 'tenant_mysql';
    }
}
