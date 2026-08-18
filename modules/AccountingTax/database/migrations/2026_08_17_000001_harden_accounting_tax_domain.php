<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->deduplicateWebsiteMappings();
        $this->deduplicateDefaultOrganizations();

        if (! Schema::hasColumn('acct_organizations', 'default_slot')) {
            Schema::table('acct_organizations', function (Blueprint $table): void {
                $table->string('default_slot', 20)->nullable()->after('is_default');
            });
        }

        DB::table('acct_organizations')
            ->where('is_default', true)
            ->update(['default_slot' => 'default']);

        $this->ensureUnique(
            'acct_organizations',
            ['default_slot'],
            'acct_organizations_default_slot_unique',
        );
        $this->reconcileOrganizationWebsiteIndexes();

        if (! Schema::hasColumn('acct_item_sources', 'organization_id')) {
            Schema::table('acct_item_sources', function (Blueprint $table): void {
                $table->foreignId('organization_id')->nullable()->after('id');
            });
        }

        DB::table('acct_item_sources')
            ->join('acct_items', 'acct_items.id', '=', 'acct_item_sources.accounting_item_id')
            ->select([
                'acct_item_sources.id as source_id',
                'acct_items.organization_id as organization_id',
            ])
            ->orderBy('acct_item_sources.id')
            ->chunkById(500, function ($sources): void {
                foreach ($sources as $source) {
                    DB::table('acct_item_sources')
                        ->where('id', $source->source_id)
                        ->update(['organization_id' => $source->organization_id]);
                }
            }, 'acct_item_sources.id', 'source_id');

        DB::table('acct_items')->where('tax_category', 'vat')->update(['tax_category' => 'standard']);

        $this->dropIndexIfExists('acct_item_sources', 'acct_item_sources_source_unique', unique: true);

        if ($this->columnIsNullable('acct_item_sources', 'organization_id')) {
            Schema::table('acct_item_sources', function (Blueprint $table): void {
                $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            });
        }

        $this->ensureForeignKey(
            'acct_item_sources',
            'organization_id',
            'acct_organizations',
            'acct_item_sources_org_fk',
            'restrict',
        );
        $this->ensureUnique(
            'acct_item_sources',
            ['organization_id', 'source_module', 'source_type', 'source_id'],
            'acct_item_sources_org_source_unique',
        );

        $this->dropIndexIfExists('acct_documents', 'acct_documents_idempotency_key_unique', unique: true);
        $this->extendDocuments();

        DB::table('acct_documents')->update([
            'base_currency' => DB::raw('currency'),
            'base_subtotal' => DB::raw('subtotal'),
            'base_discount_total' => DB::raw('discount_total'),
            'base_tax_total' => DB::raw('tax_total'),
            'base_grand_total' => DB::raw('grand_total'),
        ]);

        $this->extendDocumentLines();

        DB::table('acct_document_lines')->update([
            'line_subtotal' => DB::raw('quantity * unit_price'),
            'tax_base' => DB::raw('(quantity * unit_price) - discount_amount'),
        ]);

        if (! Schema::hasTable('acct_document_events')) {
            Schema::create('acct_document_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('document_id')->constrained('acct_documents')->restrictOnDelete();
                $table->string('event_type', 50)->index();
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30)->nullable();
                $table->unsignedBigInteger('actor_admin_id')->nullable();
                $table->unsignedInteger('document_version');
                $table->string('idempotency_key')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(
                    ['document_id', 'event_type', 'idempotency_key'],
                    'acct_document_events_idempotency_unique',
                );
                $table->index(['document_id', 'created_at'], 'acct_document_events_doc_created_idx');
            });
        }

        if (! Schema::hasTable('acct_document_payments')) {
            Schema::create('acct_document_payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('document_id')->constrained('acct_documents')->restrictOnDelete();
                $table->string('kind', 20)->default('payment')->index();
                $table->decimal('amount', 18, 2);
                $table->string('currency', 3);
                $table->dateTime('paid_at');
                $table->string('reference')->nullable();
                $table->string('status', 20)->default('recorded')->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('idempotency_key')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['document_id', 'idempotency_key'], 'acct_document_payments_idempotency_unique');
                $table->index(['document_id', 'status', 'paid_at'], 'acct_document_payments_doc_status_paid_idx');
            });
        }

        $this->replaceLegalDocumentCascadeConstraints();
    }

    private function reconcileOrganizationWebsiteIndexes(): void
    {
        // MySQL will reject dropping the old unique index while it is the only
        // index whose leading column can support the organization foreign key.
        // Create the replacement first; SQLite follows the same safe sequence.
        $this->ensureIndex(
            'acct_organization_websites',
            ['organization_id', 'is_primary'],
            'acct_org_websites_org_primary_idx',
        );
        $this->dropIndexIfExists(
            'acct_organization_websites',
            'acct_org_websites_org_website_unique',
            unique: true,
        );
        $this->ensureUnique(
            'acct_organization_websites',
            ['website_key'],
            'acct_org_websites_website_unique',
        );
    }

    private function extendDocuments(): void
    {
        Schema::table('acct_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('acct_documents', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'version')) {
                $table->unsignedInteger('version')->default(1);
            }

            if (! Schema::hasColumn('acct_documents', 'request_fingerprint')) {
                $table->string('request_fingerprint', 64)->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'seller_snapshot')) {
                $table->json('seller_snapshot')->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'buyer_snapshot')) {
                $table->json('buyer_snapshot')->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'snapshot_hash')) {
                $table->string('snapshot_hash', 64)->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'tax_breakdown')) {
                $table->json('tax_breakdown')->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'tax_period')) {
                $table->string('tax_period', 7)->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'tax_eligibility')) {
                $table->string('tax_eligibility', 30)->default('not_assessed');
            }

            if (! Schema::hasColumn('acct_documents', 'base_currency')) {
                $table->string('base_currency', 3)->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'exchange_rate')) {
                $table->decimal('exchange_rate', 20, 8)->default(1);
            }

            if (! Schema::hasColumn('acct_documents', 'base_subtotal')) {
                $table->decimal('base_subtotal', 18, 2)->default(0);
            }

            if (! Schema::hasColumn('acct_documents', 'base_discount_total')) {
                $table->decimal('base_discount_total', 18, 2)->default(0);
            }

            if (! Schema::hasColumn('acct_documents', 'base_tax_total')) {
                $table->decimal('base_tax_total', 18, 2)->default(0);
            }

            if (! Schema::hasColumn('acct_documents', 'base_grand_total')) {
                $table->decimal('base_grand_total', 18, 2)->default(0);
            }

            if (! Schema::hasColumn('acct_documents', 'paid_amount')) {
                $table->decimal('paid_amount', 18, 2)->default(0);
            }

            if (! Schema::hasColumn('acct_documents', 'original_document_id')) {
                $table->foreignId('original_document_id')->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'correction_type')) {
                $table->string('correction_type', 30)->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'effect_sign')) {
                $table->smallInteger('effect_sign')->default(1);
            }

            if (! Schema::hasColumn('acct_documents', 'reversal_status')) {
                $table->string('reversal_status', 30)->default('none');
            }

            if (! Schema::hasColumn('acct_documents', 'voided_at')) {
                $table->timestamp('voided_at')->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'voided_by')) {
                $table->unsignedBigInteger('voided_by')->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'void_reason')) {
                $table->text('void_reason')->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable();
            }

            if (! Schema::hasColumn('acct_documents', 'reversed_by')) {
                $table->unsignedBigInteger('reversed_by')->nullable();
            }
        });

        $this->ensureIndex('acct_documents', ['tax_period'], 'acct_documents_tax_period_index');
        $this->ensureIndex('acct_documents', ['tax_eligibility'], 'acct_documents_tax_eligibility_index');
        $this->ensureIndex('acct_documents', ['correction_type'], 'acct_documents_correction_type_index');
        $this->ensureIndex('acct_documents', ['reversal_status'], 'acct_documents_reversal_status_index');
        $this->ensureUnique(
            'acct_documents',
            ['organization_id', 'idempotency_key'],
            'acct_documents_org_idempotency_unique',
        );
        $this->ensureIndex(
            'acct_documents',
            ['organization_id', 'workflow_status', 'document_date'],
            'acct_documents_org_workflow_date_idx',
        );
        $this->ensureForeignKey(
            'acct_documents',
            'original_document_id',
            'acct_documents',
            'acct_documents_original_fk',
            'restrict',
        );
    }

    private function extendDocumentLines(): void
    {
        Schema::table('acct_document_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('acct_document_lines', 'tax_category')) {
                $table->string('tax_category', 40)->default('standard');
            }

            if (! Schema::hasColumn('acct_document_lines', 'line_subtotal')) {
                $table->decimal('line_subtotal', 18, 2)->default(0);
            }

            if (! Schema::hasColumn('acct_document_lines', 'tax_base')) {
                $table->decimal('tax_base', 18, 2)->default(0);
            }
        });

        $this->ensureIndex(
            'acct_document_lines',
            ['tax_category'],
            'acct_document_lines_tax_category_index',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('acct_document_payments');
        Schema::dropIfExists('acct_document_events');

        $this->restoreLegalDocumentCascadeConstraints();

        Schema::table('acct_document_lines', function (Blueprint $table): void {
            $table->dropColumn(['tax_category', 'line_subtotal', 'tax_base']);
        });

        Schema::table('acct_documents', function (Blueprint $table): void {
            $table->dropForeign('acct_documents_original_fk');
            $table->dropUnique('acct_documents_org_idempotency_unique');
            $table->dropIndex('acct_documents_org_workflow_date_idx');
            $table->dropColumn([
                'created_by',
                'version',
                'request_fingerprint',
                'seller_snapshot',
                'buyer_snapshot',
                'snapshot_hash',
                'tax_breakdown',
                'tax_period',
                'tax_eligibility',
                'base_currency',
                'exchange_rate',
                'base_subtotal',
                'base_discount_total',
                'base_tax_total',
                'base_grand_total',
                'paid_amount',
                'original_document_id',
                'correction_type',
                'effect_sign',
                'reversal_status',
                'voided_at',
                'voided_by',
                'void_reason',
                'reversed_at',
                'reversed_by',
            ]);
            $table->unique('idempotency_key', 'acct_documents_idempotency_key_unique');
        });

        Schema::table('acct_item_sources', function (Blueprint $table): void {
            $table->dropForeign('acct_item_sources_org_fk');
            $table->dropUnique('acct_item_sources_org_source_unique');
            $table->dropColumn('organization_id');
            $table->unique(
                ['source_module', 'source_type', 'source_id'],
                'acct_item_sources_source_unique',
            );
        });

        Schema::table('acct_organization_websites', function (Blueprint $table): void {
            $table->dropUnique('acct_org_websites_website_unique');
            $table->dropIndex('acct_org_websites_org_primary_idx');
            $table->unique(
                ['organization_id', 'website_key'],
                'acct_org_websites_org_website_unique',
            );
        });

        Schema::table('acct_organizations', function (Blueprint $table): void {
            $table->dropColumn('default_slot');
        });
    }

    private function deduplicateWebsiteMappings(): void
    {
        $duplicates = DB::table('acct_organization_websites')
            ->select('website_key')
            ->groupBy('website_key')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('website_key');

        foreach ($duplicates as $websiteKey) {
            $keepId = DB::table('acct_organization_websites')
                ->where('website_key', $websiteKey)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->value('id');

            DB::table('acct_organization_websites')
                ->where('website_key', $websiteKey)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }

    private function deduplicateDefaultOrganizations(): void
    {
        $defaultIds = DB::table('acct_organizations')
            ->where('is_default', true)
            ->orderBy('id')
            ->pluck('id');

        if ($defaultIds->count() < 2) {
            return;
        }

        DB::table('acct_organizations')
            ->whereIn('id', $defaultIds->slice(1)->all())
            ->update(['is_default' => false]);
    }

    private function replaceLegalDocumentCascadeConstraints(): void
    {
        $this->replaceForeignDeleteRule('acct_documents', 'organization_id', 'acct_organizations', false);
        $this->replaceForeignDeleteRule('acct_document_lines', 'document_id', 'acct_documents', false);
        $this->replaceForeignDeleteRule('acct_einvoice_transmissions', 'document_id', 'acct_documents', false);
        $this->replaceForeignDeleteRule('acct_email_deliveries', 'document_id', 'acct_documents', false);
        $this->replaceForeignDeleteRule('acct_external_invoices', 'organization_id', 'acct_organizations', false);
        $this->replaceForeignDeleteRule('acct_exports', 'organization_id', 'acct_organizations', false);
    }

    private function restoreLegalDocumentCascadeConstraints(): void
    {
        $this->replaceForeignDeleteRule('acct_documents', 'organization_id', 'acct_organizations', true);
        $this->replaceForeignDeleteRule('acct_document_lines', 'document_id', 'acct_documents', true);
        $this->replaceForeignDeleteRule('acct_einvoice_transmissions', 'document_id', 'acct_documents', true);
        $this->replaceForeignDeleteRule('acct_email_deliveries', 'document_id', 'acct_documents', true);
        $this->replaceForeignDeleteRule('acct_external_invoices', 'organization_id', 'acct_organizations', true);
        $this->replaceForeignDeleteRule('acct_exports', 'organization_id', 'acct_organizations', true);
    }

    private function replaceForeignDeleteRule(string $tableName, string $column, string $parent, bool $cascade): void
    {
        $deleteRule = $cascade ? 'cascade' : 'restrict';
        $foreign = $this->foreignKeyForColumn($tableName, $column);

        if ($foreign !== null && strtolower((string) ($foreign['on_delete'] ?? '')) === $deleteRule) {
            return;
        }

        if ($foreign !== null) {
            Schema::table(
                $tableName,
                fn (Blueprint $table): mixed => $table->dropForeign([$column]),
            );
        }

        $this->ensureForeignKey(
            $tableName,
            $column,
            $parent,
            "{$tableName}_{$column}_foreign",
            $deleteRule,
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureIndex(string $tableName, array $columns, string $indexName): void
    {
        if ($this->hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table(
            $tableName,
            fn (Blueprint $table): mixed => $table->index($columns, $indexName),
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureUnique(string $tableName, array $columns, string $indexName): void
    {
        if ($this->hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table(
            $tableName,
            fn (Blueprint $table): mixed => $table->unique($columns, $indexName),
        );
    }

    private function dropIndexIfExists(string $tableName, string $indexName, bool $unique = false): void
    {
        if (! $this->hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName, $unique): void {
            $unique ? $table->dropUnique($indexName) : $table->dropIndex($indexName);
        });
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        return Schema::hasTable($tableName)
            && collect(Schema::getIndexes($tableName))
                ->contains(fn (array $index): bool => ($index['name'] ?? '') === $indexName);
    }

    private function ensureForeignKey(
        string $tableName,
        string $column,
        string $parent,
        string $foreignName,
        string $deleteRule,
    ): void {
        if ($this->foreignKeyForColumn($tableName, $column) !== null) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use (
            $column,
            $parent,
            $foreignName,
            $deleteRule,
        ): void {
            $foreign = $table->foreign($column, $foreignName)->references('id')->on($parent);

            $deleteRule === 'cascade' ? $foreign->cascadeOnDelete() : $foreign->restrictOnDelete();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function foreignKeyForColumn(string $tableName, string $column): ?array
    {
        if (! Schema::hasTable($tableName)) {
            return null;
        }

        return collect(Schema::getForeignKeys($tableName))
            ->first(fn (array $foreign): bool => in_array($column, $foreign['columns'] ?? [], true));
    }

    private function columnIsNullable(string $tableName, string $column): bool
    {
        $definition = collect(Schema::getColumns($tableName))
            ->first(fn (array $definition): bool => ($definition['name'] ?? '') === $column);

        return $definition === null || (bool) ($definition['nullable'] ?? true);
    }
};
