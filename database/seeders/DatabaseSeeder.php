<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $users = [
            ['Admin',   'admin@example.com',    'admin'],
            ['HR',      'hr@example.com',       'hr'],
            ['Manager', 'manager@example.com',  'manager'],
            ['IT',      'it@example.com',       'it'],
            ['Sara',    'employee@example.com', 'employee'],
        ];

        foreach ($users as [$name, $email, $role]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password123',
                    'role_id' => Role::where('name', $role)->first()->id,
                ]
            );
        }
    }
}