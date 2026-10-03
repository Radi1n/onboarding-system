<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'hr', 'manager', 'it', 'employee'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
    }
}