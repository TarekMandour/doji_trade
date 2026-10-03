<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = [
            // Dashboard
            'dashboard view',

            //Settings
            'settings edit',

            // Users
            'users view', 'users create', 'users edit', 'users delete',

            // Admins (employees)
            'admins view', 'admins create', 'admins edit', 'admins delete',

            // Notifications
            'notifications view', 'notifications create', 'notifications delete',

            // Pages
            'pages view', 'pages create', 'pages edit', 'pages delete',

            // Stocks
            'stocks view', 'stocks create', 'stocks edit', 'stocks delete',

            // Watchlists
            'watchlists view', 'watchlists create', 'watchlists edit', 'watchlists delete',

            // Analysis
            'analysis view', 'analysis create', 'analysis delete',

            // Roles
            'roles view', 'roles create', 'roles edit', 'roles delete',

            // Settings (legacy)
            'setting', 'employee list', 'employee add',
        ];

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        // ── Super Admin ──────────────────────────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super admin', 'guard_name' => 'admin']);
        $superAdmin->syncPermissions($allPermissions);

        // ── Admin ─────────────────────────────────────────────────────────────
        // All permissions except role/admin management
        $adminPermissions = array_diff($allPermissions, [
            'roles create', 'roles edit', 'roles delete',
            'admins create', 'admins edit', 'admins delete',
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        $adminRole->syncPermissions(array_values($adminPermissions));

        $driverRole = Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'admin']);
    }
}
