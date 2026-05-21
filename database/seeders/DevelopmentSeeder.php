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

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RbacSeeder::class);

        $tenants = [
            ['name' => 'Acme Corp', 'slug' => 'acme-corp'],
            ['name' => 'Beta Inc', 'slug' => 'beta-inc'],
        ];

        foreach ($tenants as $tenantData) {
            $tenant = Tenant::query()->create([
                'name' => $tenantData['name'],
                'slug' => $tenantData['slug'],
                'settings' => ['timezone' => 'Asia/Jakarta'],
            ]);

            $users = $this->createUsersForTenant($tenant);

            for ($workflowIndex = 1; $workflowIndex <= 2; $workflowIndex++) {
                $definition = WorkflowDefinition::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'name' => "{$tenant->name} Order Review {$workflowIndex}",
                    'description' => 'Sample order review workflow for queue monitoring.',
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
                    'id' => 'order',
                    'type' => StepType::SCRIPT->value,
                    'name' => 'Load Order Reference',
                    'config' => [
                        'expression' => '"FF-ORDER-20260521-0001"',
                    ],
                    'dependencies' => [],
                ],
                [
                    'id' => 'amount',
                    'type' => StepType::SCRIPT->value,
                    'name' => 'Calculate Order Amount',
                    'config' => [
                        'expression' => '1575000',
                    ],
                    'dependencies' => [],
                ],
                [
                    'id' => 'risk',
                    'type' => StepType::CONDITION->value,
                    'name' => 'Check Manual Review Threshold',
                    'config' => [
                        'expression' => 'amount["result"] >= 1000000',
                    ],
                    'dependencies' => ['amount'],
                ],
                [
                    'id' => 'wait',
                    'type' => StepType::DELAY->value,
                    'name' => 'Hold Queue Before Notification',
                    'config' => [
                        'seconds' => 2,
                    ],
                    'dependencies' => ['risk'],
                ],
                [
                    'id' => 'notify',
                    'type' => StepType::SCRIPT->value,
                    'name' => 'Build Review Notification Payload',
                    'config' => [
                        'expression' => '"manual-review-notification-created"',
                    ],
                    'dependencies' => ['order', 'wait'],
                ],
            ],
        ];
    }
}
