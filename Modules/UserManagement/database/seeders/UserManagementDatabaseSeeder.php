<?php

namespace Modules\UserManagement\Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Modules\UserManagement\App\Models\User;
use Illuminate\Support\Facades\Hash;

use Illuminate\Database\Seeder;

class UserManagementDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('Pa$$w0rd!'),
                'email_verified_at' => now(),
                'user_type' => 'super_admin',
            ]
        );
    }
}
