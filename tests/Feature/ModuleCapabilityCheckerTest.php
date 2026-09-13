<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Core\Modules\ModuleManager;
use App\Models\ModuleInstallation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleCapabilityCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_is_not_kept_alive_by_stale_service_or_retained_schema(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = app(ModuleManager::class);
        $checker = app(ModuleCapabilityChecker::class);
        $this->assertFalse($checker->has('inventory', 'inventory.documents.write.v1'));
        $manager->install('catalog');
        $manager->enable('catalog');
        $manager->install('inventory');
        $manager->enable('inventory');
        $this->assertTrue($checker->has('inventory', 'inventory.documents.write.v1'));
        $this->assertFalse($checker->has('inventory', 'inventory.documents.write.v2'));
        $manager->disable('inventory');
        $this->assertFalse($checker->has('inventory', 'inventory.documents.write.v1'));
        $this->assertSame('disabled', ModuleInstallation::query()->where('key', 'inventory')->value('status'));
    }
}
