<?php

namespace Tests\Feature;

use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FreshProductionInstallTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = tempnam(sys_get_temp_dir(), 'aio-fresh-install-');
        $this->assertNotFalse($databasePath);
        $this->databasePath = $databasePath;
    }

    protected function tearDown(): void
    {
        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_production_fresh_migrate_then_module_lifecycle_reaches_latest_schema(): void
    {
        $coreMigration = $this->artisanProcess([
            'migrate',
            '--force',
            '--no-interaction',
        ]);

        $this->assertProcessSucceeded($coreMigration, 'Core production migration');

        $database = $this->database();

        $this->assertTrue($this->hasTable($database, 'cache'));
        $this->assertTrue($this->hasTable($database, 'module_installations'));
        $this->assertTrue($this->hasTable($database, 'orders'));
        $this->assertFalse(
            $this->hasTable($database, 'catalog_products'),
            'Production core migrations must not aggregate optional module migrations.',
        );
        $this->assertFalse($this->hasTable($database, 'catalog_categories'));

        $moduleLifecycle = $this->moduleLifecycleProcess();

        $this->assertProcessSucceeded($moduleLifecycle, 'CMS, Catalog, AccountingTax and Minvoice module lifecycle');

        $this->assertTrue($this->hasTable($database, 'cms_page_translations'));
        $this->assertTrue($this->hasTable($database, 'cms_media_folders'));
        $this->assertTrue($this->hasTable($database, 'cms_service_categories'));
        $this->assertTrue($this->hasTable($database, 'cms_project_categories'));
        $this->assertTrue($this->hasTable($database, 'cms_post_comments'));
        $this->assertContains('meta_keywords', $this->columns($database, 'cms_pages'));
        $this->assertContains('is_highlight', $this->columns($database, 'cms_posts'));
        $this->assertContains('cms_service_category_id', $this->columns($database, 'cms_services'));
        $this->assertContains('cms_project_category_id', $this->columns($database, 'cms_projects'));
        $this->assertContains('cms_pages_website_slug_unique', $this->indexes($database, 'cms_pages'));
        $this->assertSame(3, $this->tableCount($database, 'cms_page_translations'));
        $this->assertContains(
            'cms_services',
            $this->foreignTables($database, 'customer_service_interests'),
        );

        $this->assertTrue($this->hasTable($database, 'catalog_categories'));
        $this->assertTrue($this->hasTable($database, 'catalog_products'));
        $this->assertTrue($this->hasTable($database, 'catalog_product_images'));
        $this->assertNotContains('owner_key', $this->columns($database, 'catalog_categories'));
        $this->assertNotContains('tenant_key', $this->columns($database, 'catalog_categories'));

        $this->assertEqualsCanonicalizing(
            ['meta_title', 'meta_description'],
            array_values(array_intersect(
                ['meta_title', 'meta_description'],
                $this->columns($database, 'catalog_categories'),
            )),
        );
        $this->assertEqualsCanonicalizing(
            ['meta_keywords', 'is_highlight'],
            array_values(array_intersect(
                ['meta_keywords', 'is_highlight'],
                $this->columns($database, 'catalog_products'),
            )),
        );

        $this->assertContains(
            'catalog_categories_website_slug_unique',
            $this->indexes($database, 'catalog_categories'),
        );
        $this->assertContains(
            'catalog_products_website_sku_unique',
            $this->indexes($database, 'catalog_products'),
        );
        $this->assertContains(
            'catalog_products_website_category_idx',
            $this->indexes($database, 'catalog_products'),
        );

        $this->assertContains(
            'catalog_products',
            $this->foreignTables($database, 'order_items'),
        );
        $this->assertContains(
            'catalog_products',
            $this->foreignTables($database, 'customer_favorites'),
        );

        $this->assertTrue($this->hasTable($database, 'acct_organizations'));
        $this->assertTrue($this->hasTable($database, 'acct_documents'));
        $this->assertTrue($this->hasTable($database, 'acct_document_events'));
        $this->assertTrue($this->hasTable($database, 'acct_tax_periods'));
        $this->assertTrue($this->hasTable($database, 'acct_exports'));
        $this->assertTrue($this->hasTable($database, 'acct_email_delivery_attempts'));
        $this->assertTrue($this->hasTable($database, 'acct_provider_connections'));
        $this->assertTrue($this->hasTable($database, 'acct_external_invoice_lines'));
        $this->assertTrue($this->hasTable($database, 'acct_external_invoice_vat_breakdowns'));
        $this->assertTrue($this->hasTable($database, 'acct_einvoice_transmissions'));
        $this->assertTrue($this->hasTable($database, 'acct_inventory_warehouse_mappings'));
        $this->assertSame(
            'enabled',
            $database->query("select status from module_installations where key = 'accounting-tax'")->fetchColumn(),
        );
        $this->assertSame(
            'enabled',
            $database->query("select status from module_installations where key = 'minvoice-connector'")->fetchColumn(),
        );
    }

    /**
     * @param  list<string>  $arguments
     */
    private function artisanProcess(array $arguments): Process
    {
        $process = new Process(
            [PHP_BINARY, base_path('artisan'), ...$arguments],
            base_path(),
            $this->productionEnvironment(),
        );
        $process->setTimeout(120);
        $process->run();

        return $process;
    }

    private function moduleLifecycleProcess(): Process
    {
        $script = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$modules = $app->make(App\Core\Modules\ModuleManager::class);
$modules->install('cms');
$modules->enable('cms');
$modules->install('catalog');
$modules->enable('catalog');
$modules->install('accounting-tax');
$modules->enable('accounting-tax');
$modules->install('minvoice-connector');
$modules->enable('minvoice-connector');
PHP;

        $process = new Process(
            [PHP_BINARY, '-r', $script],
            base_path(),
            $this->productionEnvironment(),
        );
        $process->setTimeout(120);
        $process->run();

        return $process;
    }

    /**
     * @return array<string, string>
     */
    private function productionEnvironment(): array
    {
        return [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->databasePath,
            'DB_URL' => '',
            'CACHE_STORE' => 'database',
            'SESSION_DRIVER' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'MAIL_MAILER' => 'array',
        ];
    }

    private function assertProcessSucceeded(Process $process, string $operation): void
    {
        $this->assertSame(
            0,
            $process->getExitCode(),
            $operation." failed:\n".$process->getOutput().$process->getErrorOutput(),
        );
    }

    private function database(): PDO
    {
        $database = new PDO('sqlite:'.$this->databasePath);
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $database;
    }

    private function hasTable(PDO $database, string $table): bool
    {
        $statement = $database->prepare(
            "select count(*) from sqlite_master where type = 'table' and name = :table",
        );
        $statement->execute(['table' => $table]);

        return (int) $statement->fetchColumn() === 1;
    }

    private function tableCount(PDO $database, string $table): int
    {
        return (int) $database->query("select count(*) from '{$table}'")->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private function columns(PDO $database, string $table): array
    {
        return array_column(
            $database->query("pragma table_info('{$table}')")->fetchAll(PDO::FETCH_ASSOC),
            'name',
        );
    }

    /**
     * @return list<string>
     */
    private function indexes(PDO $database, string $table): array
    {
        return array_column(
            $database->query("pragma index_list('{$table}')")->fetchAll(PDO::FETCH_ASSOC),
            'name',
        );
    }

    /**
     * @return list<string>
     */
    private function foreignTables(PDO $database, string $table): array
    {
        return array_column(
            $database->query("pragma foreign_key_list('{$table}')")->fetchAll(PDO::FETCH_ASSOC),
            'table',
        );
    }
}
