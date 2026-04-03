<?php

namespace App\Core\Modules\Support;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use RuntimeException;

class ModuleLifecycleHooks
{
    public function dispatch(string $phase, ModuleLifecycleContext $context): void
    {
        foreach ($context->module['hooks'] ?? [] as $hookClass) {
            $hook = $this->resolveHook($hookClass);

            if (! method_exists($hook, $phase)) {
                continue;
            }

            $hook->{$phase}($context);
        }
    }

    private function resolveHook(string $hookClass): ModuleLifecycleHook
    {
        if (! class_exists($hookClass)) {
            $this->loadModuleHookClass($hookClass);
        }

        if (! class_exists($hookClass)) {
            throw new RuntimeException("Module hook [{$hookClass}] does not exist.");
        }

        $hook = app($hookClass);

        if (! $hook instanceof ModuleLifecycleHook) {
            throw new RuntimeException("Module hook [{$hookClass}] must implement ModuleLifecycleHook.");
        }

        return $hook;
    }

    private function loadModuleHookClass(string $hookClass): void
    {
        $relative = str_replace('Modules\\', '', $hookClass);
        $path = base_path('modules/'.str_replace('\\', DIRECTORY_SEPARATOR, $relative).'.php');

        if (is_file($path)) {
            require_once $path;
        }
    }
}
