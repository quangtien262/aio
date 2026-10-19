<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\FnbPos\Services\FnbMenuChoiceService;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnb_menu_choice_definitions')) {
            Schema::create('fnb_menu_choice_definitions', function (Blueprint $table): void {
                $table->id();
                $table->string('website_key', 120);
                $table->string('kind', 20);
                $table->string('code', 40);
                $table->string('name');
                $table->char('name_key', 64);
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
                $table->unique(['website_key', 'kind', 'code'], 'fnb_choice_code_uq');
                $table->unique(['website_key', 'kind', 'name_key'], 'fnb_choice_name_uq');
            });
        }
        foreach (DB::table('fnb_site_settings')->pluck('website_key') as $websiteKey) FnbMenuChoiceService::seedDefaults($websiteKey);
        foreach (['size' => 'fnb_item_variants', 'topping' => 'fnb_modifier_options'] as $kind => $table) {
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($kind): void {
                foreach ($rows as $row) FnbMenuChoiceService::import($row->website_key, $kind, $row->name, $row->code);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fnb_menu_choice_definitions');
    }
};
