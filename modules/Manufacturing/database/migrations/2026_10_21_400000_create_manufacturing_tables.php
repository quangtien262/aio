<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\Runtime\ManufacturingRuntimeState;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_runtime_states', function (Blueprint $t): void {
            $t->string('module_key', 80)->primary();
            $t->string('operational_state', 20)->default('disabled');
            $t->uuid('operation_id')->nullable();
            $t->string('resume_state', 20)->default('disabled');
            $t->boolean('maintenance_paused')->default(false);
            $t->unsignedBigInteger('paused_by')->nullable();
            $t->text('pause_reason')->nullable();
            $t->unsignedBigInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('mfg_companies', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('code', 50)->unique();
            $t->string('name');
            $t->string('timezone', 60)->default('Asia/Ho_Chi_Minh');
            $t->json('settings')->nullable();
            $t->string('status', 30)->default('active');
            $t->unsignedBigInteger('version')->default(1);
            $t->timestamps();
        });
        $this->table('mfg_factories', function (Blueprint $t): void {
            $t->string('code', 50);
            $t->string('name');
            $t->string('timezone', 60)->default('Asia/Ho_Chi_Minh');
            $t->unsignedBigInteger('warehouse_id')->nullable();
            $t->json('settings')->nullable();
            $t->unique(['company_id', 'code']);
        }, false, 'active');
        $this->table('mfg_workshops', function (Blueprint $t): void {
            $t->string('code', 50);
            $t->string('name');
            $t->unique(['factory_id', 'code']);
        }, true, 'active');
        $this->table('mfg_lines', function (Blueprint $t): void {
            $t->foreignId('workshop_id')->nullable()->constrained('mfg_workshops')->restrictOnDelete();
            $t->string('code', 50);
            $t->string('name');
            $t->decimal('capacity_minutes', 20, 6)->default(0);
            $t->unique(['factory_id', 'code']);
        }, true, 'active');
        $this->table('mfg_shifts', function (Blueprint $t): void {
            $t->string('code', 50);
            $t->string('name');
            $t->time('starts_at');
            $t->time('ends_at');
            $t->json('settings')->nullable();
            $t->unique(['factory_id', 'code']);
        }, true, 'active');
        $this->table('mfg_memberships', function (Blueprint $t): void {
            $t->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
            $t->string('role', 30)->default('reader');
            $t->json('permissions')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->index(['admin_id', 'company_id', 'factory_id'], 'mfg_membership_scope_idx');
        }, true, 'active');
        $this->table('mfg_styles', function (Blueprint $t): void {
            $t->string('code', 60);
            $t->string('name');
            $t->text('description')->nullable();
            $t->unique(['company_id', 'code']);
        }, true, 'active');
        $this->table('mfg_style_revisions', function (Blueprint $t): void {
            $t->foreignId('style_id')->constrained('mfg_styles')->restrictOnDelete();
            $t->unsignedInteger('revision');
            foreach (['bom', 'routing', 'measurements', 'attachments'] as $column) {
                $t->json($column)->nullable();
            }
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->unique(['style_id', 'revision']);
        });
        $this->table('mfg_demands', function (Blueprint $t): void {
            $t->string('code', 60);
            $t->string('source_type', 40)->default('customer_order');
            $t->string('source_id', 120)->nullable();
            $t->json('buyer_snapshot')->nullable();
            $t->date('due_date')->nullable();
            $t->char('currency', 3)->default('VND');
            $t->unique(['company_id', 'code']);
        });
        $this->table('mfg_demand_lines', function (Blueprint $t): void {
            $t->foreignId('demand_id')->constrained('mfg_demands')->restrictOnDelete();
            $t->foreignId('style_id')->nullable()->constrained('mfg_styles')->restrictOnDelete();
            $t->foreignId('style_revision_id')->nullable()->constrained('mfg_style_revisions')->restrictOnDelete();
            $t->unsignedBigInteger('sku_id')->nullable();
            $t->json('variant')->nullable();
            $this->quantities($t, ['quantity', 'allocated_quantity', 'fulfilled_quantity', 'unit_price']);
        });
        $this->table('mfg_orders', function (Blueprint $t): void {
            $t->string('code', 60);
            $t->foreignId('demand_id')->nullable()->constrained('mfg_demands')->restrictOnDelete();
            $t->foreignId('style_revision_id')->nullable()->constrained('mfg_style_revisions')->restrictOnDelete();
            $t->date('due_date')->nullable();
            $t->date('planned_start_date')->nullable();
            $t->char('currency', 3)->default('VND');
            $t->json('released_snapshot')->nullable();
            $t->json('buyer_snapshot')->nullable();
            $t->unsignedBigInteger('released_by')->nullable();
            $t->timestamp('released_at')->nullable();
            $t->unique(['company_id', 'code']);
        });
        $this->table('mfg_order_lines', function (Blueprint $t): void {
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->foreignId('demand_line_id')->nullable()->constrained('mfg_demand_lines')->restrictOnDelete();
            $t->unsignedBigInteger('sku_id')->nullable();
            $t->json('variant')->nullable();
            $this->quantities($t, ['quantity', 'completed_quantity', 'shipped_quantity']);
        });
        $this->table('mfg_allocations', function (Blueprint $t): void {
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->foreignId('order_line_id')->nullable()->constrained('mfg_order_lines')->restrictOnDelete();
            $t->foreignId('workshop_id')->nullable()->constrained('mfg_workshops')->restrictOnDelete();
            $t->foreignId('line_id')->nullable()->constrained('mfg_lines')->restrictOnDelete();
            $t->foreignId('shift_id')->nullable()->constrained('mfg_shifts')->restrictOnDelete();
            $this->quantities($t, ['quantity']);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
        });
        $this->table('mfg_material_requirements', function (Blueprint $t): void {
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->unsignedBigInteger('sku_id');
            $t->string('base_unit', 30);
            $this->quantities($t, ['required_quantity', 'reserved_quantity', 'issued_quantity', 'returned_quantity', 'consumed_quantity']);
            $t->index(['order_id', 'sku_id']);
        });
        $this->table('mfg_bundles', function (Blueprint $t): void {
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->foreignId('order_line_id')->nullable()->constrained('mfg_order_lines')->restrictOnDelete();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('code', 80);
            $t->unsignedBigInteger('sku_id')->nullable();
            $t->json('variant')->nullable();
            $this->quantities($t, ['quantity', 'accepted_quantity', 'rejected_quantity']);
            $t->string('operation_key', 80)->nullable();
            $t->foreignId('workshop_id')->nullable()->constrained('mfg_workshops')->restrictOnDelete();
            $t->foreignId('line_id')->nullable()->constrained('mfg_lines')->restrictOnDelete();
            $t->unique(['company_id', 'code']);
        });
        $this->table('mfg_operations', function (Blueprint $t): void {
            $t->foreignId('bundle_id')->constrained('mfg_bundles')->restrictOnDelete();
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->string('operation_key', 80);
            $t->foreignId('workshop_id')->nullable()->constrained('mfg_workshops')->restrictOnDelete();
            $t->foreignId('line_id')->nullable()->constrained('mfg_lines')->restrictOnDelete();
            $t->foreignId('shift_id')->nullable()->constrained('mfg_shifts')->restrictOnDelete();
            $this->quantities($t, ['quantity', 'accepted_quantity', 'rejected_quantity', 'rework_quantity']);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->unsignedBigInteger('recorded_by')->nullable();
            $t->json('snapshot')->nullable();
        });
        $this->table('mfg_handovers', function (Blueprint $t): void {
            $t->foreignId('bundle_id')->constrained('mfg_bundles')->restrictOnDelete();
            $t->foreignId('from_factory_id')->constrained('mfg_factories')->restrictOnDelete();
            $t->foreignId('to_factory_id')->constrained('mfg_factories')->restrictOnDelete();
            $this->quantities($t, ['quantity', 'received_quantity']);
            $t->unsignedBigInteger('dispatched_by')->nullable();
            $t->unsignedBigInteger('received_by')->nullable();
            $t->timestamp('dispatched_at')->nullable();
            $t->timestamp('received_at')->nullable();
        });
        $this->table('mfg_quality_inspections', function (Blueprint $t): void {
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->foreignId('bundle_id')->nullable()->constrained('mfg_bundles')->restrictOnDelete();
            $t->foreignId('operation_id')->nullable()->constrained('mfg_operations')->restrictOnDelete();
            $t->string('checkpoint', 50);
            $this->quantities($t, ['inspected_quantity', 'accepted_quantity', 'rejected_quantity']);
            $t->json('defects')->nullable();
            $t->json('measurements')->nullable();
            $t->unsignedBigInteger('inspected_by')->nullable();
            $t->timestamp('inspected_at')->nullable();
        });
        $this->table('mfg_reworks', function (Blueprint $t): void {
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->foreignId('bundle_id')->constrained('mfg_bundles')->restrictOnDelete();
            $t->foreignId('inspection_id')->nullable()->constrained('mfg_quality_inspections')->restrictOnDelete();
            $this->quantities($t, ['quantity', 'completed_quantity']);
            $t->text('reason')->nullable();
            $t->string('operation_key', 80)->nullable();
        });
        $this->table('mfg_subcontracts', function (Blueprint $t): void {
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->string('supplier_ref', 120);
            $t->string('code', 80);
            $t->string('operation_key', 80);
            $this->quantities($t, ['quantity', 'sent_quantity', 'received_quantity', 'scrap_quantity', 'fee_amount']);
            $t->char('currency', 3)->default('VND');
            $t->unique(['company_id', 'code']);
        });
        $this->table('mfg_cartons', function (Blueprint $t): void {
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->string('code', 80);
            $t->json('contents');
            $this->quantities($t, ['quantity']);
            $t->unique(['company_id', 'code']);
        });
        $this->table('mfg_shipments', function (Blueprint $t): void {
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->string('code', 80);
            $t->json('destination_snapshot')->nullable();
            $t->json('lines')->nullable();
            $t->json('carton_ids')->nullable();
            $this->quantities($t, ['quantity']);
            $t->timestamp('shipped_at')->nullable();
            $t->unique(['company_id', 'code']);
        });
        $this->table('mfg_shipment_lines', function (Blueprint $t): void {
            $t->foreignId('shipment_id')->constrained('mfg_shipments')->restrictOnDelete();
            $t->foreignId('order_line_id')->constrained('mfg_order_lines')->restrictOnDelete();
            $t->foreignId('carton_id')->nullable()->constrained('mfg_cartons')->restrictOnDelete();
            $this->quantities($t, ['quantity']);
        });
        $this->table('mfg_cost_runs', function (Blueprint $t): void {
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->string('code', 80);
            $this->quantities($t, ['material_cost', 'labor_cost', 'subcontract_cost', 'overhead_cost', 'total_cost', 'output_quantity', 'unit_cost']);
            $t->json('policy')->nullable();
            $t->json('allocations')->nullable();
            $t->unsignedBigInteger('posted_by')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->unique(['company_id', 'code']);
        });
        $this->table('mfg_cost_entries', function (Blueprint $t): void {
            $t->foreignId('order_id')->constrained('mfg_orders')->restrictOnDelete();
            $t->foreignId('cost_run_id')->nullable()->constrained('mfg_cost_runs')->restrictOnDelete();
            $t->string('type', 40);
            $t->string('source_module', 80)->nullable();
            $t->string('source_id', 150)->nullable();
            $this->quantities($t, ['amount']);
            $t->char('currency', 3)->default('VND');
            $t->text('description')->nullable();
        });
        $this->table('mfg_documents', function (Blueprint $t): void {
            $t->string('resource_type', 60);
            $t->string('resource_id', 120);
            $t->string('original_name');
            $t->string('mime_type', 100);
            $t->string('path');
            $t->char('checksum', 64);
            $t->unsignedBigInteger('size');
            $t->unsignedBigInteger('uploaded_by');
        }, true, 'ready');
        $this->table('mfg_samples', function (Blueprint $t): void {
            $t->foreignId('style_id')->constrained('mfg_styles')->restrictOnDelete();
            $t->foreignId('style_revision_id')->nullable()->constrained('mfg_style_revisions')->restrictOnDelete();
            $t->string('type', 40)->default('pp');
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
        });
        $this->table('mfg_artifacts', function (Blueprint $t): void {
            $t->foreignId('style_revision_id')->nullable()->constrained('mfg_style_revisions')->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->constrained('mfg_orders')->restrictOnDelete();
            $t->string('original_name');
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('size');
            $t->string('disk', 50)->default('manufacturing_private');
            $t->string('path');
            $t->char('checksum', 64);
            $t->unsignedBigInteger('uploaded_by');
            $t->timestamp('expires_at')->nullable();
        }, true, 'ready');
        $this->table('mfg_events', function (Blueprint $t): void {
            $t->foreignId('order_id')->nullable()->constrained('mfg_orders')->restrictOnDelete();
            $t->foreignId('bundle_id')->nullable()->constrained('mfg_bundles')->restrictOnDelete();
            $t->string('event_type', 100);
            $t->decimal('quantity', 20, 6)->nullable();
            $t->string('source_ref', 150)->nullable();
            $t->unsignedBigInteger('recorded_by');
            $t->timestamp('occurred_at');
            $t->json('snapshot')->nullable();
        }, true, 'recorded');
        Schema::create('mfg_commands', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->unsignedBigInteger('actor_id');
            $t->unsignedBigInteger('company_id')->nullable();
            $t->unsignedBigInteger('factory_id')->nullable();
            $t->string('command_key', 120);
            $t->string('operation', 100);
            $t->char('fingerprint', 64);
            $t->json('authorization');
            $t->json('result_ref')->nullable();
            $t->string('status', 20)->default('pending');
            $t->timestamps();
            $t->unique(['actor_id', 'command_key']);
        });
        Schema::create('mfg_outbox', function (Blueprint $t): void {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->unsignedBigInteger('factory_id')->nullable();
            $t->string('event_type', 100);
            $t->string('aggregate_type', 60);
            $t->string('aggregate_id', 120);
            $t->unsignedBigInteger('aggregate_version');
            $t->unsignedInteger('schema_version')->default(1);
            $t->json('payload');
            $t->string('destination', 100)->default('audit');
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->index(['status', 'available_at']);
        });
        Schema::create('mfg_inbox', function (Blueprint $t): void {
            $t->id();
            $t->string('consumer', 100);
            $t->uuid('event_id');
            $t->timestamp('processed_at');
            $t->unique(['consumer', 'event_id']);
        });
        app(ManufacturingRuntimeState::class)->install();
    }

    private function table(string $name, Closure $columns, bool $factory = true, string $status = 'draft'): void
    {
        Schema::create($name, function (Blueprint $t) use ($columns, $factory, $status): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('company_id')->constrained('mfg_companies')->restrictOnDelete();
            if ($factory) {
                $t->foreignId('factory_id')->nullable()->constrained('mfg_factories')->restrictOnDelete();
            }
            $columns($t);
            $t->string('status', 30)->default($status);
            $t->unsignedBigInteger('version')->default(1);
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index($factory ? ['company_id', 'factory_id', 'status'] : ['company_id', 'status']);
        });
    }

    private function quantities(Blueprint $t, array $columns): void
    {
        foreach ($columns as $column) {
            $t->decimal($column, 20, 6)->default(0);
        }
    }

    public function down(): void
    {
        foreach (['inbox', 'outbox', 'commands', 'events', 'artifacts', 'samples', 'documents', 'cost_entries', 'cost_runs', 'shipment_lines', 'shipments', 'cartons', 'subcontracts', 'reworks', 'quality_inspections', 'handovers', 'operations', 'bundles', 'material_requirements', 'allocations', 'order_lines', 'orders', 'demand_lines', 'demands', 'style_revisions', 'styles', 'memberships', 'shifts', 'lines', 'workshops', 'factories', 'companies', 'runtime_states'] as $table) {
            Schema::dropIfExists('mfg_'.$table);
        }
    }
};
