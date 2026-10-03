<?php

namespace Database\Seeders;

use App\Models\TaskTemplate;
use Illuminate\Database\Seeder;

class TaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = [
            ['Complete personal information', 'employee', 1],
            ['Upload national ID copy', 'employee', 2],
            ['Upload signed contract', 'employee', 3],
            ['Review submitted documents', 'hr', 4],
            ['Create company email account', 'it', 5],
            ['Assign laptop', 'it', 6],
            ['Create system access accounts', 'it', 7],
            ['Final approval', 'manager', 8],
        ];

        foreach ($tasks as [$title, $role, $order]) {
            TaskTemplate::firstOrCreate(
                ['title' => $title],
                ['assigned_role' => $role, 'order' => $order]
            );
        }
    }
}