<?php

namespace App\Core\Modules\Contracts;

use App\Core\Modules\Support\ModuleLifecycleContext;

interface ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void;

    public function postInstall(ModuleLifecycleContext $context): void;

    public function preEnable(ModuleLifecycleContext $context): void;

    public function postEnable(ModuleLifecycleContext $context): void;

    public function preDisable(ModuleLifecycleContext $context): void;

    public function postDisable(ModuleLifecycleContext $context): void;

    public function preUpgrade(ModuleLifecycleContext $context): void;

    public function postUpgrade(ModuleLifecycleContext $context): void;

    public function preUninstall(ModuleLifecycleContext $context): void;

    public function postUninstall(ModuleLifecycleContext $context): void;
}
