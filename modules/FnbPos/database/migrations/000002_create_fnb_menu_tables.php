<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnb_menus')) {
            Schema::create('fnb_menus', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->string('code', 40);
                $table->string('name');
                $table->string('status', 30)->default('draft');
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('published_at')->nullable();
                $this->actor($table, 'published_by');
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
                $table->unique(['website_key', 'code'], 'fnb_menu_scope_code_uq');
                $table->unique(['website_key', 'id'], 'fnb_menu_scope_id_uq');
            });
        }

        if (! Schema::hasTable('fnb_menu_publications')) {
            Schema::create('fnb_menu_publications', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('menu_id');
                $table->unsignedInteger('revision');
                $table->json('snapshot');
                $table->string('snapshot_hash', 64);
                $table->timestamp('published_at');
                $this->actor($table, 'published_by');
                $table->string('current_slot', 20)->nullable();
                $table->unique(['menu_id', 'revision'], 'fnb_menu_pub_revision_uq');
                $table->unique(['menu_id', 'current_slot'], 'fnb_menu_pub_current_uq');
                $table->foreign(['website_key', 'menu_id'], 'fnb_menu_pub_menu_fk')
                    ->references(['website_key', 'id'])->on('fnb_menus')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_menu_outlets')) {
            Schema::create('fnb_menu_outlets', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('menu_id');
                $table->unsignedBigInteger('outlet_id');
                $table->string('channel', 30)->default('pos');
                $table->string('default_slot', 20)->nullable();
                $table->timestamp('active_from')->nullable();
                $table->timestamp('active_to')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamps();
                $table->unique(['menu_id', 'outlet_id', 'channel'], 'fnb_menu_outlet_channel_uq');
                $table->unique(['outlet_id', 'channel', 'default_slot'], 'fnb_menu_outlet_default_uq');
                $table->foreign(['website_key', 'menu_id'], 'fnb_menu_outlet_menu_fk')
                    ->references(['website_key', 'id'])->on('fnb_menus')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id'], 'fnb_menu_outlet_outlet_fk')
                    ->references(['website_key', 'id'])->on('fnb_outlets')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_menu_categories')) {
            Schema::create('fnb_menu_categories', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('menu_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('code', 40);
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 30)->default('active');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['menu_id', 'code'], 'fnb_menu_category_code_uq');
                $table->unique(['website_key', 'menu_id', 'id'], 'fnb_menu_category_scope_id_uq');
                $table->foreign(['website_key', 'menu_id'], 'fnb_menu_category_menu_fk')
                    ->references(['website_key', 'id'])->on('fnb_menus')->restrictOnDelete();
                $table->foreign(['website_key', 'menu_id', 'parent_id'], 'fnb_menu_category_parent_fk')
                    ->references(['website_key', 'menu_id', 'id'])->on('fnb_menu_categories')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_menu_items')) {
            Schema::create('fnb_menu_items', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->uuid('public_id')->unique();
                $table->string('code', 60);
                $table->string('sku', 100)->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('item_type', 30)->default('prepared');
                $table->string('tax_category', 30)->default('standard');
                $table->unsignedInteger('tax_rate_bps')->default(0);
                $table->boolean('tax_inclusive')->default(true);
                $table->text('image_url')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamp('archived_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['website_key', 'code'], 'fnb_item_scope_code_uq');
                $table->unique(['website_key', 'id'], 'fnb_item_scope_id_uq');
                $table->index(['website_key', 'status', 'name'], 'fnb_item_hot_idx');
            });
        }

        if (! Schema::hasTable('fnb_menu_category_items')) {
            Schema::create('fnb_menu_category_items', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('menu_id');
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('item_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 30)->default('active');
                $table->timestamps();
                $table->unique(['menu_id', 'category_id', 'item_id'], 'fnb_menu_category_item_uq');
                $table->foreign(['website_key', 'menu_id', 'category_id'], 'fnb_mci_category_fk')
                    ->references(['website_key', 'menu_id', 'id'])->on('fnb_menu_categories')->cascadeOnDelete();
                $table->foreign(['website_key', 'item_id'], 'fnb_mci_item_fk')
                    ->references(['website_key', 'id'])->on('fnb_menu_items')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_item_variants')) {
            Schema::create('fnb_item_variants', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('item_id');
                $table->string('code', 60);
                $table->string('name');
                $table->bigInteger('base_price_minor');
                $table->boolean('is_default')->default(false);
                $table->string('default_slot', 20)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 30)->default('active');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['item_id', 'code'], 'fnb_variant_item_code_uq');
                $table->unique(['item_id', 'default_slot'], 'fnb_variant_item_default_uq');
                $table->unique(['website_key', 'id'], 'fnb_variant_scope_id_uq');
                $table->unique(['website_key', 'item_id', 'id'], 'fnb_variant_scope_item_id_uq');
                $table->foreign(['website_key', 'item_id'], 'fnb_variant_item_fk')
                    ->references(['website_key', 'id'])->on('fnb_menu_items')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_modifier_groups')) {
            Schema::create('fnb_modifier_groups', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->string('code', 60);
                $table->string('name');
                $table->unsignedInteger('min_select')->default(0);
                $table->unsignedInteger('max_select')->default(1);
                $table->unsignedInteger('free_quantity')->default(0);
                $table->string('status', 30)->default('active');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['website_key', 'code'], 'fnb_modifier_group_scope_code_uq');
                $table->unique(['website_key', 'id'], 'fnb_modifier_group_scope_id_uq');
            });
        }

        if (! Schema::hasTable('fnb_modifier_options')) {
            Schema::create('fnb_modifier_options', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('group_id');
                $table->string('code', 60);
                $table->string('name');
                $table->bigInteger('base_price_delta_minor')->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 30)->default('active');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['group_id', 'code'], 'fnb_modifier_option_group_code_uq');
                $table->unique(['website_key', 'id'], 'fnb_modifier_option_scope_id_uq');
                $table->unique(['website_key', 'group_id', 'id'], 'fnb_modifier_option_group_id_uq');
                $table->foreign(['website_key', 'group_id'], 'fnb_modifier_option_group_fk')
                    ->references(['website_key', 'id'])->on('fnb_modifier_groups')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_item_modifier_groups')) {
            Schema::create('fnb_item_modifier_groups', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('group_id');
                $table->unsignedInteger('min_select_override')->nullable();
                $table->unsignedInteger('max_select_override')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['item_id', 'group_id'], 'fnb_item_modifier_group_uq');
                $table->foreign(['website_key', 'item_id'], 'fnb_img_item_fk')
                    ->references(['website_key', 'id'])->on('fnb_menu_items')->cascadeOnDelete();
                $table->foreign(['website_key', 'group_id'], 'fnb_img_group_fk')
                    ->references(['website_key', 'id'])->on('fnb_modifier_groups')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_price_books')) {
            Schema::create('fnb_price_books', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->string('code', 60);
                $table->string('name');
                $table->string('currency', 3);
                $table->string('status', 30)->default('active');
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_to')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['website_key', 'code'], 'fnb_price_book_scope_code_uq');
                $table->unique(['website_key', 'id'], 'fnb_price_book_scope_id_uq');
            });
        }

        if (! Schema::hasTable('fnb_price_book_outlets')) {
            Schema::create('fnb_price_book_outlets', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('outlet_id');
                $table->unsignedBigInteger('price_book_id');
                $table->string('channel', 30)->default('pos');
                $table->integer('priority')->default(0);
                $table->string('default_slot', 20)->nullable();
                $table->timestamps();
                $table->unique(['outlet_id', 'channel', 'default_slot'], 'fnb_price_book_outlet_default_uq');
                $table->unique(['outlet_id', 'price_book_id', 'channel'], 'fnb_price_book_outlet_channel_uq');
                $table->foreign(['website_key', 'outlet_id'], 'fnb_pbo_outlet_fk')
                    ->references(['website_key', 'id'])->on('fnb_outlets')->restrictOnDelete();
                $table->foreign(['website_key', 'price_book_id'], 'fnb_pbo_price_book_fk')
                    ->references(['website_key', 'id'])->on('fnb_price_books')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_price_book_variant_prices')) {
            Schema::create('fnb_price_book_variant_prices', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('price_book_id');
                $table->unsignedBigInteger('variant_id');
                $table->bigInteger('amount_minor');
                $table->timestamps();
                $table->unique(['price_book_id', 'variant_id'], 'fnb_price_variant_uq');
                $table->foreign(['website_key', 'price_book_id'], 'fnb_price_variant_book_fk')
                    ->references(['website_key', 'id'])->on('fnb_price_books')->cascadeOnDelete();
                $table->foreign(['website_key', 'variant_id'], 'fnb_price_variant_variant_fk')
                    ->references(['website_key', 'id'])->on('fnb_item_variants')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_price_book_modifier_prices')) {
            Schema::create('fnb_price_book_modifier_prices', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('price_book_id');
                $table->unsignedBigInteger('modifier_option_id');
                $table->bigInteger('amount_minor');
                $table->timestamps();
                $table->unique(['price_book_id', 'modifier_option_id'], 'fnb_price_modifier_uq');
                $table->foreign(['website_key', 'price_book_id'], 'fnb_price_modifier_book_fk')
                    ->references(['website_key', 'id'])->on('fnb_price_books')->cascadeOnDelete();
                $table->foreign(['website_key', 'modifier_option_id'], 'fnb_price_modifier_option_fk')
                    ->references(['website_key', 'id'])->on('fnb_modifier_options')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_outlet_item_states')) {
            Schema::create('fnb_outlet_item_states', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('outlet_id');
                $table->unsignedBigInteger('item_id');
                $table->boolean('is_available')->default(true);
                $table->timestamp('sold_out_until')->nullable();
                $table->text('reason')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['outlet_id', 'item_id'], 'fnb_outlet_item_state_uq');
                $table->foreign(['website_key', 'outlet_id'], 'fnb_outlet_item_outlet_fk')
                    ->references(['website_key', 'id'])->on('fnb_outlets')->cascadeOnDelete();
                $table->foreign(['website_key', 'item_id'], 'fnb_outlet_item_item_fk')
                    ->references(['website_key', 'id'])->on('fnb_menu_items')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('fnb_item_station_routes')) {
            Schema::create('fnb_item_station_routes', function (Blueprint $table): void {
                $table->id();
                $this->website($table);
                $table->unsignedBigInteger('outlet_id');
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('variant_id')->nullable();
                $table->unsignedBigInteger('variant_key')->default(0);
                $table->unsignedBigInteger('prep_station_id');
                $table->integer('priority')->default(0);
                $table->timestamps();
                $table->unique(['outlet_id', 'item_id', 'variant_key'], 'fnb_item_station_route_uq');
                $table->foreign(['website_key', 'outlet_id'], 'fnb_route_outlet_fk')
                    ->references(['website_key', 'id'])->on('fnb_outlets')->cascadeOnDelete();
                $table->foreign(['website_key', 'item_id'], 'fnb_route_item_fk')
                    ->references(['website_key', 'id'])->on('fnb_menu_items')->restrictOnDelete();
                $table->foreign(['website_key', 'item_id', 'variant_id'], 'fnb_route_variant_fk')
                    ->references(['website_key', 'item_id', 'id'])->on('fnb_item_variants')->restrictOnDelete();
                $table->foreign(['website_key', 'outlet_id', 'prep_station_id'], 'fnb_route_station_fk')
                    ->references(['website_key', 'outlet_id', 'id'])->on('fnb_prep_stations')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'fnb_item_station_routes', 'fnb_outlet_item_states', 'fnb_price_book_modifier_prices',
            'fnb_price_book_variant_prices', 'fnb_price_book_outlets', 'fnb_price_books',
            'fnb_item_modifier_groups', 'fnb_modifier_options', 'fnb_modifier_groups', 'fnb_item_variants',
            'fnb_menu_category_items', 'fnb_menu_items', 'fnb_menu_categories', 'fnb_menu_outlets',
            'fnb_menu_publications', 'fnb_menus',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function website(Blueprint $table): void
    {
        $table->string('website_key', 120);
    }

    private function actor(Blueprint $table, string $name): void
    {
        $table->foreignId($name)->nullable()->constrained('admins')->nullOnDelete();
    }
};
