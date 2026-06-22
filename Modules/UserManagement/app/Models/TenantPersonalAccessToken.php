<?php

namespace Modules\UserManagement\App\Models;

use Laravel\Sanctum\PersonalAccessToken;

class TenantPersonalAccessToken extends PersonalAccessToken
{   

    protected $table = 'personal_access_tokens';

    public static function findTokenOnConnection($token, $connection)
    {
        try {
            $instance = new static;
            $instance->setConnection($connection);

            if (strpos($token, '|') === false) {
                return $instance->where(
                    'token',
                    hash('sha256', $token)
                )->first();
            }

            [$id, $token] = explode('|', $token, 2);

            return $instance
                ->where('id', $id)
                ->where('token', hash('sha256', $token))
                ->first();

        } catch (\Throwable $e) {
            return null;
        }
    }
}