<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Seed the reference data needed by every installation.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => config('garage.admin.username')],
            [
                'name' => config('garage.admin.name'),
                'password' => config('garage.admin.password'),
                'role' => UserRole::Gerant,
            ],
        );
    }
}
