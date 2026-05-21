<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $rolePermissions = [
        'admin' => [
            'dashboard.view',
            'workflows.view',
            'workflows.create',
            'workflows.update',
            'workflows.delete',
            'workflows.trigger',
            'runs.view',
            'runs.cancel',
        ],
        'editor' => [
            'workflows.view',
            'workflows.create',
            'workflows.update',
            'workflows.trigger',
            'runs.view',
            'runs.cancel',
        ],
        'viewer' => [
            'workflows.view',
            'runs.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_unique(array_merge(...array_values($this->rolePermissions))) as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (UserRole::cases() as $role) {
            $spatieRole = Role::firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
            ]);

            $spatieRole->syncPermissions($this->rolePermissions[$role->value]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
