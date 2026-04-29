<?php

namespace Modules\Project\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\Permission;
use App\Models\Role;

class ProjectLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void
    {
    }

    public function postInstall(ModuleLifecycleContext $context): void
    {
        $this->ensureProjectManagerRole();
    }

    public function preEnable(ModuleLifecycleContext $context): void
    {
    }

    public function postEnable(ModuleLifecycleContext $context): void
    {
        $this->ensureProjectManagerRole();
    }

    public function preDisable(ModuleLifecycleContext $context): void
    {
    }

    public function postDisable(ModuleLifecycleContext $context): void
    {
    }

    public function preUpgrade(ModuleLifecycleContext $context): void
    {
    }

    public function postUpgrade(ModuleLifecycleContext $context): void
    {
        $this->ensureProjectManagerRole();
    }

    public function preUninstall(ModuleLifecycleContext $context): void
    {
    }

    public function postUninstall(ModuleLifecycleContext $context): void
    {
        Role::query()->where('key', 'project.manager')->first()?->delete();
    }

    private function ensureProjectManagerRole(): void
    {
        $role = Role::query()->firstOrCreate(
            ['key' => 'project.manager'],
            [
                'name' => 'Project Manager',
                'description' => 'Quản lý dự án, công việc, checklist, files, báo cáo và lịch sử hoạt động của module Project.',
            ],
        );

        $permissionIds = Permission::query()
            ->whereIn('key', [
                'project.view',
                'project.create',
                'project.update',
                'project.delete',
                'project.member.manage',
                'project.task.view',
                'project.task.create',
                'project.task.update',
                'project.task.delete',
                'project.checklist.manage',
                'project.file.manage',
                'project.report.view',
                'project.report.create',
                'project.report.update',
                'project.report.delete',
                'project.activity.view',
            ])
            ->pluck('id')
            ->all();

        $role->permissions()->syncWithoutDetaching($permissionIds);
    }
}
