<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class ManufacturingFreshInstallTest extends TestCase
{
    public function test_fresh_production_core_then_manufacturing_lifecycle_without_optional_modules(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'aio_mfg_fresh_');
        try {
            $process = new Process([PHP_BINARY, '-d', 'memory_limit=256M', base_path('tests/Support/manufacturing-fresh-install.php')], base_path(), [
                'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('m', 32)),
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
            ]);
            $process->setTimeout(60)->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertTrue($result['ok']);
            $this->assertTrue($result['optional_schema_absent']);
            $this->assertSame(2, $result['commands']);
            $this->assertSame(1, $result['factories']);
            $this->assertSame('0.5.0', $result['module_versions']['inventory']);
            $this->assertSame('0.1.0-dev.1', $result['module_versions']['manufacturing']);
        } finally {
            foreach ([$database, $database.'-wal', $database.'-shm'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
