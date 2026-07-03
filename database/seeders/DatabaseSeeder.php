<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Modules\UserManagement\Database\Seeders\UserManagementDatabaseSeeder;
use Modules\UserManagement\Database\Seeders\LocationSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UserManagementDatabaseSeeder::class);
        $this->call(LocationSeeder::class);
    }
}
