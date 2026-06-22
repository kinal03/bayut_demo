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
            config([
                'database.connections.tenant_dynamic' => [
                    'driver' => 'mysql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => '3306',
                    'database' => $tenant->database,
                    'username' => env('DB_USERNAME', 'root'),
                    'password' => env('DB_PASSWORD', ''),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ]
            ]);

            DB::purge('tenant_dynamic');
            DB::reconnect('tenant_dynamic');

            // set default connection
            config(['database.default' => 'tenant_dynamic']);
        } else {
            config(['database.default' => 'mysql']); // central
        }
    }
}

if (!function_exists('getTenantConnection')) {

    function getTenantConnection($tenant)
    {
        config([
            "database.connections.tenant_mysql" => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => '3306',
                'database' => $tenant->database,
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]
        ]);

        DB::purge('tenant_mysql');
        DB::reconnect('tenant_mysql');

        return 'tenant_mysql';
    }
}
