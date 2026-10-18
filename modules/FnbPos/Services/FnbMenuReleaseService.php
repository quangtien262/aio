<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbAuditLogger;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

final class FnbMenuReleaseService
{
    public function __construct(private readonly FnbCommandRunner $commands, private readonly FnbOutletAccessService $access, private readonly FnbAuditLogger $audit) {}

    public function overview(Admin $actor, string $website): array
    {
        $this->access->authorizeHq($actor, $website, 'fnb.menu.master.manage');
        $books = DB::table('fnb_price_books')->where('website_key', $website)->orderByDesc('id')->limit(100)->get();
        $links = DB::table('fnb_price_book_outlets')->where('website_key', $website)->whereIn('price_book_id', $books->pluck('id'))->get()->groupBy('price_book_id');
        $prices = DB::table('fnb_price_book_variant_prices')->where('website_key', $website)->whereIn('price_book_id', $books->pluck('id'))->get(['price_book_id', 'variant_id', 'amount_minor'])->groupBy('price_book_id');

        return ['menus' => DB::table('fnb_menus')->where('website_key', $website)->whereNull('archived_at')->get()->all(),
            'publications' => DB::table('fnb_menu_publications')->where('website_key', $website)->orderByDesc('id')->limit(100)->get(['id', 'menu_id', 'revision', 'published_at', 'snapshot_hash'])->all(),
            'outlet_publications' => DB::table('fnb_menu_outlets')->where('website_key', $website)->whereNotNull('default_slot')->get(['outlet_id', 'menu_id', 'menu_publication_id', 'version'])->all(),
            'variants' => DB::table('fnb_item_variants as variants')->join('fnb_menu_items as items', fn ($join) => $join->on('items.website_key', '=', 'variants.website_key')->on('items.id', '=', 'variants.item_id'))
                ->where('variants.website_key', $website)->where('variants.status', 'active')->where('items.status', 'active')->orderBy('items.name')->get(['variants.id', 'items.name as item_name', 'variants.name', 'variants.code', 'variants.base_price_minor'])->all(),
            'price_books' => $books->map(fn ($book) => (array) $book + ['outlet_ids' => ($links[$book->id] ?? collect())->pluck('outlet_id')->all(), 'prices' => ($prices[$book->id] ?? collect())->all()])->all()];
    }

    public function create(FnbContext $ctx, array $input, string $key): array
    {
        $data = validator($input, ['code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'], 'name' => ['required', 'string', 'max:255']])->validate();

        return $this->commands->run($ctx, 'chain.menu.create', $data, $key, function () use ($ctx, $data): array {
            $this->hq($ctx);
            app(FnbMasterDataLock::class)->acquire($ctx->websiteKey);
            validator($data, ['code' => [Rule::unique('fnb_menus', 'code')->where('website_key', $ctx->websiteKey)]])->validate();
            $id = DB::table('fnb_menus')->insertGetId($data + ['website_key' => $ctx->websiteKey, 'status' => 'draft', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

            return ['resource' => ['id' => $id, 'code' => $data['code'], 'name' => $data['name'], 'version' => 1]];
        });
    }

    public function publish(FnbContext $ctx, array $input, string $key): array
    {
        $data = validator($input, ['menu_id' => ['required', 'integer', 'min:1'], 'expected_version' => ['required', 'integer', 'min:1'], 'outlet_ids' => ['required', 'array', 'min:1', 'max:100'], 'outlet_ids.*' => ['integer', 'distinct', 'min:1']])->validate();

        return $this->commands->run($ctx, 'chain.menu.publish', $data, $key, function () use ($ctx, $data): array {
            $this->hq($ctx);
            app(FnbMasterDataLock::class)->acquire($ctx->websiteKey);
            $this->outlets($ctx, $data['outlet_ids']);
            $menu = DB::table('fnb_menus')->where('website_key', $ctx->websiteKey)->where('id', $data['menu_id'])->lockForUpdate()->first();
            abort_unless($menu, 404);
            if ((int) $menu->version !== (int) $data['expected_version']) {
                throw new FnbConflictException('Thực đơn đã thay đổi; vui lòng tải lại.');
            }
            $outletCtx = new FnbContext($ctx->websiteKey, (int) $data['outlet_ids'][0], $ctx->actorId);
            $catalog = app(FnbSetupService::class)->catalog($outletCtx, ['draft' => true])['resource'];
            $categoryIds = DB::table('fnb_menu_categories')->where('website_key', $ctx->websiteKey)->where('menu_id', $menu->id)->where('status', 'active')->pluck('id')->all();
            $itemIds = DB::table('fnb_menu_category_items')->where('website_key', $ctx->websiteKey)->where('menu_id', $menu->id)->whereIn('category_id', $categoryIds)->where('status', 'active')->pluck('item_id')->unique()->all();
            // A categorized menu publishes only its own active items; an uncategorized legacy menu keeps its original all-items behavior.
            $menuItems = $categoryIds === [] && $menu->code === 'DEFAULT' ? $catalog['items'] : array_values(array_filter($catalog['items'], fn ($item) => in_array($item['id'], $itemIds, true)));
            abort_if($menuItems === [], 422, 'Thực đơn chưa có món hoạt động; hãy thêm nhóm món và gán món trước khi phát hành.');
            $snapshot = ['items' => $menuItems, 'categories' => array_values(array_filter($catalog['categories'], fn ($category) => in_array($category['id'], $categoryIds, true))), 'modifier_groups' => $catalog['modifier_groups'] ?? [], 'menu_id' => $menu->id];
            // Availability and station routes remain operational per outlet, never part of the HQ menu payload.
            foreach ($snapshot['items'] as &$item) {
                unset($item['available'], $item['availability_version'], $item['sold_out_until'], $item['station_routes']);
            } unset($item);
            $snapshot['_recipe_bindings'] = [];
            foreach (['variant' => ['fnb_variant_recipes', 'variant_id'], 'modifier' => ['fnb_modifier_option_recipes', 'modifier_option_id']] as $kind => [$table, $column]) {
                foreach (DB::table($table)->where('website_key', $ctx->websiteKey)->whereNotNull('current_slot')->get() as $binding) {
                    $snapshot['_recipe_bindings'][$kind][(string) $binding->$column] = ['recipe_id' => (int) $binding->recipe_id, 'multiplier' => (string) $binding->multiplier];
                }
            }
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $revision = (int) DB::table('fnb_menu_publications')->where('menu_id', $menu->id)->max('revision') + 1;
            DB::table('fnb_menu_publications')->where('menu_id', $menu->id)->whereNotNull('current_slot')->update(['current_slot' => null]);
            $id = DB::table('fnb_menu_publications')->insertGetId(['website_key' => $ctx->websiteKey, 'menu_id' => $menu->id, 'revision' => $revision, 'snapshot' => $json, 'snapshot_hash' => $this->commands->fingerprint($snapshot), 'published_at' => now(), 'published_by' => $ctx->actorId, 'current_slot' => 'current']);
            $this->rollout($ctx, $menu->id, $id, $data['outlet_ids']);
            DB::table('fnb_menus')->where('id', $menu->id)->update(['status' => 'published', 'version' => $menu->version + 1, 'published_by' => $ctx->actorId, 'published_at' => now(), 'updated_at' => now()]);
            $this->audit->record('fnb.menu.published', $ctx->websiteKey, Admin::findOrFail($ctx->actorId), 'fnb_menu_publications:'.$id, after: ['publication_id' => $id, 'revision' => $revision, 'snapshot_hash' => $this->commands->fingerprint($snapshot)], outletIds: $data['outlet_ids']);

            return ['resource' => ['id' => $id, 'menu_id' => (int) $menu->id, 'revision' => $revision, 'menu_version' => $menu->version + 1, 'outlet_ids' => $data['outlet_ids']]];
        });
    }

    public function rollback(FnbContext $ctx, int $publicationId, array $input, string $key): array
    {
        $data = validator($input, ['outlet_ids' => ['required', 'array', 'min:1', 'max:100'], 'outlet_ids.*' => ['required', 'integer', 'distinct', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500']])->validate();

        return $this->commands->run($ctx, 'chain.menu.rollback', $data + ['publication_id' => $publicationId], $key, function () use ($ctx, $data, $publicationId): array {
            $this->hq($ctx);
            app(FnbMasterDataLock::class)->acquire($ctx->websiteKey);
            $this->outlets($ctx, $data['outlet_ids']);
            $publication = DB::table('fnb_menu_publications')->where('website_key', $ctx->websiteKey)->where('id', $publicationId)->first();
            abort_unless($publication, 404);
            $this->rollout($ctx, (int) $publication->menu_id, $publicationId, $data['outlet_ids']);
            $this->audit->record('fnb.menu.rollback', $ctx->websiteKey, Admin::findOrFail($ctx->actorId), 'fnb_menu_publications:'.$publicationId, after: ['reason' => $data['reason'], 'publication_id' => $publicationId], outletIds: $data['outlet_ids']);

            return ['resource' => ['id' => $publicationId, 'revision' => $publication->revision, 'outlet_ids' => $data['outlet_ids']]];
        });
    }

    public function priceBook(FnbContext $ctx, array $input, string $key): array
    {
        $data = validator($input, ['code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'], 'name' => ['required', 'string', 'max:255'], 'currency' => ['required', 'in:VND'],
            'outlet_ids' => ['required', 'array', 'min:1', 'max:100'], 'outlet_ids.*' => ['integer', 'min:1', 'distinct'],
            'prices' => ['required', 'array', 'min:1', 'max:2000'], 'prices.*.variant_id' => ['required', 'integer', 'min:1', 'distinct'], 'prices.*.amount_minor' => ['required', 'integer', 'min:0', 'max:9000000000000'],
            'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date']])->validate();

        foreach (['valid_from', 'valid_to'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = CarbonImmutable::parse($data[$field])->utc()->toDateTimeString();
            }
        }
        if (! empty($data['valid_from']) && ! empty($data['valid_to']) && $data['valid_to'] <= $data['valid_from']) {
            throw ValidationException::withMessages(['valid_to' => ['Thời điểm kết thúc phải sau thời điểm bắt đầu.']]);
        }

        return $this->commands->run($ctx, 'chain.pricebook.save', $data, $key, function () use ($ctx, $data): array {
            $this->hq($ctx);
            app(FnbMasterDataLock::class)->acquire($ctx->websiteKey);
            $this->outlets($ctx, $data['outlet_ids']);
            validator($data, ['code' => [Rule::unique('fnb_price_books', 'code')->where('website_key', $ctx->websiteKey)]])->validate();
            $ids = array_column($data['prices'], 'variant_id');
            abort_unless(DB::table('fnb_item_variants')->where('website_key', $ctx->websiteKey)->whereIn('id', $ids)->count() === count($ids), 422, 'Size không thuộc website hiện tại.');
            abort_if(DB::table('fnb_outlets')->where('website_key', $ctx->websiteKey)->whereIn('id', $data['outlet_ids'])->where('currency', '!=', $data['currency'])->exists(), 422, 'Bảng giá phải cùng tiền tệ với cửa hàng.');
            $id = DB::table('fnb_price_books')->insertGetId(array_intersect_key($data, array_flip(['code', 'name', 'currency', 'valid_from', 'valid_to'])) + ['website_key' => $ctx->websiteKey, 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['prices'] as $price) {
                DB::table('fnb_price_book_variant_prices')->insert($price + ['website_key' => $ctx->websiteKey, 'price_book_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($data['outlet_ids'] as $outlet) {
                $priority = (int) DB::table('fnb_price_book_outlets')->where('website_key', $ctx->websiteKey)->where('outlet_id', $outlet)->max('priority') + 1;
                DB::table('fnb_price_book_outlets')->where('website_key', $ctx->websiteKey)->where('outlet_id', $outlet)->where('channel', 'pos')->update(['default_slot' => null]);
                DB::table('fnb_price_book_outlets')->insert(['website_key' => $ctx->websiteKey, 'outlet_id' => $outlet, 'price_book_id' => $id, 'channel' => 'pos', 'priority' => $priority, 'default_slot' => 'default', 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit->record('fnb.pricebook.created', $ctx->websiteKey, Admin::findOrFail($ctx->actorId), 'fnb_price_books:'.$id, after: ['code' => $data['code'], 'price_book_id' => $id], outletIds: $data['outlet_ids']);

            return ['resource' => ['id' => $id, 'code' => $data['code'], 'version' => 1, 'outlet_ids' => $data['outlet_ids']]];
        });
    }

    public function releasedCatalog(FnbContext $ctx): ?array
    {
        if (! DB::getSchemaBuilder()->hasColumn('fnb_menu_outlets', 'menu_publication_id')) {
            return null;
        }
        $publication = DB::table('fnb_menu_outlets as links')->join('fnb_menu_publications as publications', fn ($join) => $join->on('publications.website_key', '=', 'links.website_key')->on('publications.id', '=', 'links.menu_publication_id'))
            ->where('links.website_key', $ctx->websiteKey)->where('links.outlet_id', $ctx->outletId)->where('links.channel', 'pos')->where('links.status', 'active')->whereNotNull('links.default_slot')->first(['publications.snapshot', 'publications.snapshot_hash', 'publications.id', 'publications.revision']);
        if (! $publication) {
            return null;
        }
        $snapshot = is_string($publication->snapshot) ? json_decode($publication->snapshot, true, 512, JSON_THROW_ON_ERROR) : (array) $publication->snapshot;
        // MySQL normalizes JSON whitespace; validate semantic canonical payload rather than database formatting.
        if (! hash_equals($publication->snapshot_hash, $this->commands->fingerprint($snapshot))) {
            throw new FnbConflictException('Bản thực đơn không khớp checksum đã công bố.');
        }
        $snapshot['publication_id'] = (int) $publication->id;
        $snapshot['revision'] = (int) $publication->revision;

        return $snapshot;
    }

    public function variant(FnbContext $ctx, int $variantId): ?array
    {
        $catalog = $this->releasedCatalog($ctx);
        if ($catalog === null) {
            return null;
        }
        foreach ($catalog['items'] as $item) {
            foreach ($item['variants'] as $variant) {
                if ((int) $variant['id'] === $variantId) {
                    return $variant + ['item_code' => $item['code'], 'item_name' => $item['name'], 'item_type' => $item['item_type'], 'tax_category' => $item['tax_category'], 'tax_rate_bps' => $item['tax_rate_bps'], 'tax_inclusive' => $item['tax_inclusive']];
                }
            }
        }
        throw new FnbConflictException('Món chưa có trong thực đơn đang áp dụng tại cửa hàng.');
    }

    private function rollout(FnbContext $ctx, int $menuId, int $publicationId, array $outlets): void
    {
        foreach ($outlets as $outlet) {
            DB::table('fnb_menu_outlets')->where('website_key', $ctx->websiteKey)->where('outlet_id', $outlet)->where('channel', 'pos')->lockForUpdate()->get();
            DB::table('fnb_menu_outlets')->where('website_key', $ctx->websiteKey)->where('outlet_id', $outlet)->where('channel', 'pos')->update(['default_slot' => null]);
            $link = DB::table('fnb_menu_outlets')->where('website_key', $ctx->websiteKey)->where('outlet_id', $outlet)->where('menu_id', $menuId)->where('channel', 'pos')->first();
            $values = ['menu_publication_id' => $publicationId, 'status' => 'active', 'default_slot' => 'default', 'version' => (int) ($link->version ?? 0) + 1, 'updated_at' => now()];
            if ($link) {
                DB::table('fnb_menu_outlets')->where('id', $link->id)->update($values);
            } else {
                DB::table('fnb_menu_outlets')->insert($values + ['website_key' => $ctx->websiteKey, 'outlet_id' => $outlet, 'menu_id' => $menuId, 'channel' => 'pos', 'created_at' => now()]);
            }
        }
    }

    private function hq(FnbContext $ctx): void
    {
        $this->access->authorizeHq(Admin::findOrFail($ctx->actorId), $ctx->websiteKey, 'fnb.menu.master.manage');
    }

    private function outlets(FnbContext $ctx, array $ids): void
    {
        $rows = DB::table('fnb_outlets')->where('website_key', $ctx->websiteKey)->whereIn('id', $ids)->where('status', 'active')->orderBy('id')->lockForUpdate()->get();
        abort_unless($rows->count() === count($ids), 422, 'Cửa hàng không thuộc website hoặc đã ngừng hoạt động.');
    }
}
