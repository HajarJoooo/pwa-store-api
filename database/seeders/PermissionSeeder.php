<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // User permissions
            'pwas.install',
            'reviews.create',
            'reviews.update-own',
            'reviews.delete-own',

            // Developer permissions
            'pwas.submit',
            'pwas.update-own',
            'developer.dashboard',

            // Moderator permissions
            'pwas.review',
            'pwas.view-pending',
        ];

        $guards = ['web', 'sanctum'];

        foreach ($guards as $guard) {
            foreach ($permissions as $permissionName) {
                Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => $guard,
                ]);
            }

            // Assign to User role
            $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => $guard]);
            $userPermissions = [
                'pwas.install',
                'reviews.create',
                'reviews.update-own',
                'reviews.delete-own',
            ];
            $userRole->syncPermissions($userPermissions);

            // Assign to Developer role
            $developerRole = Role::firstOrCreate(['name' => 'developer', 'guard_name' => $guard]);
            $developerPermissions = array_merge($userPermissions, [
                'pwas.submit',
                'pwas.update-own',
                'developer.dashboard',
            ]);
            $developerRole->syncPermissions($developerPermissions);

            // Assign to Moderator role
            $moderatorRole = Role::firstOrCreate(['name' => 'moderator', 'guard_name' => $guard]);
            $moderatorPermissions = [
                'pwas.review',
                'pwas.view-pending',
            ];
            $moderatorRole->syncPermissions($moderatorPermissions);

            // Assign to Admin role (all permissions)
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
            $adminRole->syncPermissions(Permission::where('guard_name', $guard)->get());
        }
    }
}
