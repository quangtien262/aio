<?php

use App\Core\Modules\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Local-only scratch database owner. Source connection is used for DDL metadata and CREATE/DROP only. */
final class FnbMysqlSandbox
{
    public readonly string $name;

    public readonly string $nonce;

    public readonly string $connection;

    public readonly string $sourceConnection;

    public readonly array $sourceConfig;

    private bool $created = false;

    public static function requireOptIn(array $arguments, string $script, string $extra = ''): void
    {
        if (in_array('--help', $arguments, true)) {
            echo "Usage: php {$script} --allow-isolated-mysql {$extra}\n";
            echo "Requires an existing loopback MySQL core schema and CREATE/DROP DATABASE privileges.\n";
            echo "Copies schema definitions only; writes synthetic data only in a new random aio_fnb_* database.\n";
            exit(0);
        }
        if (! in_array('--allow-isolated-mysql', $arguments, true)) {
            throw new RuntimeException('Refusing database access without --allow-isolated-mysql. Use --help for the isolated gate contract.');
        }
    }

    public function __construct(string $prefix)
    {
        if (! in_array($prefix, ['aio_fnb_smoke_', 'aio_fnb_load_'], true)) {
            throw new InvalidArgumentException('Unsupported scratch database prefix.');
        }
        $this->sourceConnection = DB::getDefaultConnection();
        $this->sourceConfig = DB::connection($this->sourceConnection)->getConfig();
        if (($this->sourceConfig['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Requires an explicitly configured local MySQL connection; no database was created.');
        }
        $host = $this->sourceConfig['host'] ?? null;
        if (! is_string($host) || ! in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)
            || isset($this->sourceConfig['read']) || isset($this->sourceConfig['write'])) {
            throw new RuntimeException('Refusing a non-loopback or read/write-routed MySQL source. Use a local dedicated test server.');
        }
        $suffix = bin2hex(random_bytes(6));
        $this->name = $prefix.$suffix;
        $this->nonce = bin2hex(random_bytes(32));
        $this->connection = 'fnb_gate_'.$suffix;
        config(['cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array']);
    }

    public function create(): void
    {
        DB::connection($this->sourceConnection)->statement('CREATE DATABASE `'.$this->name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->created = true;
        config(['database.connections.'.$this->connection => array_replace($this->sourceConfig, ['database' => $this->name, 'url' => null])]);
        DB::setDefaultConnection($this->connection);
        if ((string) DB::selectOne('SELECT DATABASE() AS db')->db !== $this->name) {
            throw new RuntimeException('Scratch connection identity mismatch.');
        }
        DB::statement('CREATE TABLE fnb_gate_sandbox (nonce_hash CHAR(64) PRIMARY KEY)');
        DB::table('fnb_gate_sandbox')->insert(['nonce_hash' => hash('sha256', $this->nonce)]);
    }

    public function install(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (['admins', 'roles', 'permissions', 'admin_role_assignments', 'permission_role', 'module_installations', 'sites', 'audit_logs', 'audit_log_chain_heads'] as $table) {
                $definition = (array) DB::connection($this->sourceConnection)->selectOne('SHOW CREATE TABLE `'.$table.'`');
                $ddl = $definition['Create Table'];
                if (preg_match('/REFERENCES\s+`[^`]+`\s*\./i', $ddl)) {
                    throw new RuntimeException('Cross-database foreign keys are not allowed in scratch core schema.');
                }
                DB::statement($ddl);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        foreach (['database/migrations/2026_09_05_000001_create_module_lifecycle_security_tables.php', 'database/migrations/2026_10_18_000001_partition_audit_hash_chains.php'] as $path) {
            if (Artisan::call('migrate', ['--path' => $path, '--force' => true, '--no-interaction' => true]) !== 0) {
                throw new RuntimeException('Scratch core migration failed: '.Artisan::output());
            }
        }
        app(ModuleManager::class)->install('fnb-pos');
        app(ModuleManager::class)->enable('fnb-pos');
    }

    public function workerEnvironment(): array
    {
        return ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/'.$this->name.'-unused-config.php',
            'APP_KEY' => (string) config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
            'DB_HOST' => (string) $this->sourceConfig['host'], 'DB_PORT' => (string) $this->sourceConfig['port'], 'DB_SOCKET' => (string) ($this->sourceConfig['unix_socket'] ?? ''),
            'DB_DATABASE' => $this->name, 'DB_USERNAME' => (string) $this->sourceConfig['username'], 'DB_PASSWORD' => (string) $this->sourceConfig['password'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
            'FNB_MYSQL_GATE_WORKER' => '1', 'FNB_MYSQL_GATE_NONCE' => $this->nonce, 'FNB_MYSQL_GATE_DATABASE' => $this->name];
    }

    public static function assertWorkerDatabase(): void
    {
        $name = (string) getenv('FNB_MYSQL_GATE_DATABASE');
        $nonce = (string) getenv('FNB_MYSQL_GATE_NONCE');
        $connection = DB::connection();
        if (getenv('FNB_MYSQL_GATE_WORKER') !== '1' || ! preg_match('/\Aaio_fnb_load_[a-f0-9]{12}\z/', $name)
            || strlen($nonce) !== 64 || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== $name || (string) DB::selectOne('SELECT DATABASE() AS db')->db !== $name) {
            throw new RuntimeException('Worker refused: not attached to its generated isolated database.');
        }
        if (! DB::table('fnb_gate_sandbox')->where('nonce_hash', hash('sha256', $nonce))->exists()) {
            throw new RuntimeException('Worker scratch database ownership proof failed.');
        }
    }

    public function close(): void
    {
        DB::setDefaultConnection($this->sourceConnection);
        DB::purge($this->connection);
        if ($this->created && preg_match('/\Aaio_fnb_(?:smoke|load)_[a-f0-9]{12}\z/', $this->name)) {
            DB::connection($this->sourceConnection)->statement('DROP DATABASE `'.$this->name.'`');
            $this->created = false;
            echo "Generated scratch database removed.\n";
        }
    }
}
