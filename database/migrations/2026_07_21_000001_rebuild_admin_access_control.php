<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $legacyScopedTables = [
        'cms_pages',
        'cms_posts',
        'cms_categories',
        'cms_menus',
        'cms_media',
        'cms_featured_categories',
        'cms_side_promos',
        'cms_services',
        'cms_service_categories',
        'cms_projects',
        'cms_project_categories',
        'cms_testimonials',
        'cms_team_members',
        'cms_partners',
        'catalog_products',
        'catalog_categories',
        'site_banners',
        'orders',
    ];

    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            $table->string('status')->default('active')->index()->after('is_active');
            $table->boolean('is_system_owner')->default(false)->index()->after('status');
            $table->boolean('must_change_password')->default(false)->after('is_system_owner');
            $table->unsignedBigInteger('auth_version')->default(1)->after('must_change_password');
            $table->timestamp('password_changed_at')->nullable()->after('auth_version');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->text('two_factor_secret')->nullable()->after('last_login_ip');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('is_system')->default(false)->index()->after('description');
            $table->boolean('is_assignable')->default(true)->after('is_system');
            $table->string('status')->default('active')->index()->after('is_assignable');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
            $table->string('risk_level')->default('normal')->index()->after('module_key');
            $table->boolean('is_active')->default(true)->index()->after('risk_level');
            $table->timestamp('deprecated_at')->nullable()->after('is_active');
        });

        foreach ([
            ['admin.audit.view', 'Admin Audit View', 'admin', 'sensitive'],
            ['cms.order.update', 'CMS Order Update', 'cms', 'sensitive'],
            ['cms.order.delete', 'CMS Order Delete', 'cms', 'critical'],
            ['cms.newsletter.view', 'CMS Newsletter View', 'cms', 'normal'],
            ['cms.newsletter.update', 'CMS Newsletter Update', 'cms', 'sensitive'],
            ['cms.newsletter.delete', 'CMS Newsletter Delete', 'cms', 'critical'],
        ] as [$key, $name, $moduleKey, $riskLevel]) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                [
                    'name' => $name,
                    'description' => null,
                    'module_key' => $moduleKey,
                    'risk_level' => $riskLevel,
                    'is_active' => true,
                    'deprecated_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        Schema::create('admin_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('scope_type')->default('global')->index();
            $table->string('scope_value')->nullable()->index();
            $table->foreignId('assigned_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['admin_id', 'scope_type', 'scope_value'], 'admin_assignment_scope_index');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action')->index();
            $table->string('module_key')->nullable()->index();
            $table->string('website_key')->nullable()->index();
            $table->string('target_type')->nullable()->index();
            $table->string('target_id')->nullable()->index();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        $this->backfillAssignments();

        DB::table('roles')->where('key', 'super-admin')->update([
            'is_system' => true,
            'is_assignable' => false,
            'status' => 'active',
        ]);

        $superAdminRoleId = DB::table('roles')->where('key', 'super-admin')->value('id');
        if ($superAdminRoleId !== null) {
            DB::table('permissions')->where('is_active', true)->pluck('id')->each(
                fn ($permissionId) => DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $superAdminRoleId],
                    ['created_at' => now(), 'updated_at' => now()],
                ),
            );
        }

        $ownerId = DB::table('admins')->orderBy('id')->value('id');

        if ($ownerId !== null) {
            DB::table('admins')->where('id', $ownerId)->update([
                'status' => 'active',
                'is_active' => true,
                'is_system_owner' => true,
            ]);

            if ($superAdminRoleId !== null && ! DB::table('admin_role_assignments')
                ->where('admin_id', $ownerId)
                ->where('role_id', $superAdminRoleId)
                ->where('scope_type', 'global')
                ->exists()) {
                DB::table('admin_role_assignments')->insert([
                    'admin_id' => $ownerId,
                    'role_id' => $superAdminRoleId,
                    'scope_type' => 'global',
                    'scope_value' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::dropIfExists('admin_role_scopes');
        Schema::dropIfExists('admin_role');

        foreach ($this->legacyScopedTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columns = collect(['owner_key', 'tenant_key'])
                ->filter(fn (string $column): bool => Schema::hasColumn($tableName, $column))
                ->all();

            if ($columns !== []) {
                foreach ($columns as $column) {
                    $indexName = $tableName.'_'.$column.'_index';

                    if (Schema::hasIndex($tableName, $indexName)) {
                        Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($indexName));
                    }
                }

                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->legacyScopedTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'owner_key')) {
                    $table->string('owner_key')->nullable()->index();
                }

                if (! Schema::hasColumn($tableName, 'tenant_key')) {
                    $table->string('tenant_key')->nullable()->index();
                }
            });
        }

        Schema::create('admin_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['admin_id', 'role_id']);
        });

        DB::table('admin_role_assignments')
            ->select(['admin_id', 'role_id'])
            ->distinct()
            ->orderBy('admin_id')
            ->each(fn ($assignment) => DB::table('admin_role')->insert([
                'admin_id' => $assignment->admin_id,
                'role_id' => $assignment->role_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('admin_role_assignments');

        Schema::table('permissions', fn (Blueprint $table) => $table->dropColumn([
            'description', 'risk_level', 'is_active', 'deprecated_at',
        ]));
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn([
            'is_system', 'is_assignable', 'status',
        ]));
        Schema::table('admins', fn (Blueprint $table) => $table->dropColumn([
            'status', 'is_system_owner', 'must_change_password', 'auth_version',
            'password_changed_at', 'last_login_ip', 'two_factor_secret',
            'two_factor_recovery_codes', 'two_factor_confirmed_at',
        ]));
    }

    private function backfillAssignments(): void
    {
        if (! Schema::hasTable('admin_role')) {
            return;
        }

        DB::table('admin_role')->orderBy('id')->each(function ($pivot): void {
            $websiteScopes = Schema::hasTable('admin_role_scopes')
                ? DB::table('admin_role_scopes')
                    ->where('admin_id', $pivot->admin_id)
                    ->where('role_id', $pivot->role_id)
                    ->where('scope_type', 'website')
                    ->pluck('scope_value')
                    ->filter()
                    ->unique()
                : collect();

            if ($websiteScopes->isEmpty()) {
                $websiteScopes = collect([null]);
            }

            foreach ($websiteScopes as $websiteKey) {
                DB::table('admin_role_assignments')->insert([
                    'admin_id' => $pivot->admin_id,
                    'role_id' => $pivot->role_id,
                    'scope_type' => $websiteKey === null ? 'global' : 'website',
                    'scope_value' => $websiteKey,
                    'created_at' => $pivot->created_at ?? now(),
                    'updated_at' => $pivot->updated_at ?? now(),
                ]);
            }
        });
    }
};
