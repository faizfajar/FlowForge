<?php

namespace Database\Seeders;

use App\Enums\StepType;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
            ]);
        }

        $tenants = [
            ['name' => 'Acme Corp', 'slug' => 'acme-corp'],
            ['name' => 'Beta Inc', 'slug' => 'beta-inc'],
        ];

        foreach ($tenants as $tenantData) {
            $tenant = Tenant::query()->create([
                'name' => $tenantData['name'],
                'slug' => $tenantData['slug'],
                'settings' => ['timezone' => 'UTC'],
            ]);

            $users = $this->createUsersForTenant($tenant);

            for ($workflowIndex = 1; $workflowIndex <= 2; $workflowIndex++) {
                $definition = WorkflowDefinition::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                'name' => "{$tenant->name} Workflow {$workflowIndex}",
                'description' => 'Sample workflow definition for development.',
                'schedule_cron' => $workflowIndex === 1 ? null : '*/5 * * * *',
            ]);

                for ($versionNumber = 1; $versionNumber <= 2; $versionNumber++) {
                    $version = WorkflowVersion::query()->create([
                        'workflow_definition_id' => $definition->id,
                        'version_number' => $versionNumber,
                        'dag' => $this->sampleDag(),
                        'is_active' => $versionNumber === 2,
                        'created_by' => $users[UserRole::ADMIN->value]->id,
                    ]);

                    if ($versionNumber === 2) {
                        $definition->forceFill(['active_version_id' => $version->id])->save();
                    }
                }
            }
        }
    }

    /**
     * @return array<string, User>
     */
    private function createUsersForTenant(Tenant $tenant): array
    {
        $users = [];

        foreach (UserRole::cases() as $role) {
            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => Str::headline($role->value).' '.$tenant->name,
                'email' => "{$role->value}@{$tenant->slug}.test",
                'password' => Hash::make('password'),
                'role' => $role,
            ]);

            $user->assignRole($role->value);
            $users[$role->value] = $user;
        }

        return $users;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function sampleDag(): array
    {
        return [
            'steps' => [
                [
                    'id' => 'prepare',
                    'type' => StepType::SCRIPT->value,
                    'name' => 'Prepare Data',
                    'config' => [
                        'expression' => '40 + 2',
                    ],
                    'dependencies' => [],
                ],
                [
                    'id' => 'check',
                    'type' => StepType::CONDITION->value,
                    'name' => 'Check Value',
                    'config' => [
                        'expression' => 'prepare["result"] == 42',
                    ],
                    'dependencies' => ['prepare'],
                ],
                [
                    'id' => 'wait',
                    'type' => StepType::DELAY->value,
                    'name' => 'Wait Briefly',
                    'config' => [
                        'seconds' => 1,
                    ],
                    'dependencies' => ['check'],
                ],
            ],
        ];
    }
}
