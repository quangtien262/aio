<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class FnbMysqlGateSafetyTest extends TestCase
{
    #[DataProvider('scripts')]
    public function test_mysql_gate_help_and_missing_opt_in_do_not_boot_or_connect(string $script): void
    {
        $root = dirname(__DIR__, 2);
        // A bogus connection makes an accidental framework boot/connect visibly fail.
        $environment = ['DB_CONNECTION' => 'unconfigured_gate_safety_sentinel', 'FNB_MYSQL_GATE_WORKER' => '0'];
        $help = new Process([PHP_BINARY, $root.'/'.$script, '--help'], $root, $environment);
        $help->setTimeout(5);
        $help->run();
        $this->assertSame(0, $help->getExitCode());
        $this->assertStringContainsString('--allow-isolated-mysql', $help->getOutput());
        $refusal = new Process([PHP_BINARY, $root.'/'.$script], $root, $environment);
        $refusal->setTimeout(5);
        $refusal->run();
        $this->assertNotSame(0, $refusal->getExitCode());
        $this->assertStringContainsString('Refusing database access without --allow-isolated-mysql', $refusal->getErrorOutput());
        $this->assertStringNotContainsString('PDOException', $refusal->getErrorOutput());
    }

    public static function scripts(): array
    {
        return [['tests/Support/fnb-mysql-smoke.php'], ['tests/Support/fnb-mysql-concurrency.php']];
    }

    public function test_worker_cannot_be_started_directly_against_a_configured_connection(): void
    {
        $root = dirname(__DIR__, 2);
        $process = new Process([PHP_BINARY, $root.'/tests/Support/fnb-mysql-concurrency.php', '--worker'], $root,
            ['DB_CONNECTION' => 'unconfigured_gate_safety_sentinel', 'FNB_MYSQL_GATE_WORKER' => '0', 'FNB_MYSQL_GATE_NONCE' => '', 'FNB_MYSQL_GATE_DATABASE' => '']);
        $process->setTimeout(5);
        $process->run();
        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('Worker entry refused before application bootstrap', $process->getErrorOutput());
        $this->assertStringNotContainsString('PDOException', $process->getErrorOutput());
    }
}
