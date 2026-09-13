<?php

namespace Modules\FnbPos\Services;

use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Domain\FnbNotFoundException;
use Modules\FnbPos\Domain\FnbValidationException;
use Modules\FnbPos\Domain\MinorMoney;

final class FnbSetupService
{
    public function __construct(
        private readonly FnbCommandRunner $commands,
        private readonly FnbOutboxService $outbox,
    ) {}

    /** @param array<string,mixed> $input */
    public function onboard(FnbContext $context, array $input, string $idempotencyKey): array
    {
        if ($context->outletId !== 0) {
            throw new FnbValidationException('Onboarding requires the pre-outlet context.');
        }

        $data = validator($input, [
            'outlet' => ['required', 'array'],
            'outlet.code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'outlet.name' => ['required', 'string', 'max:255'],
            'outlet.timezone' => ['required', 'string', 'max:64'],
            'outlet.currency' => ['required', 'string', 'size:3'],
            'outlet.phone' => ['nullable', 'string', 'max:40'],
            'outlet.address' => ['nullable', 'string', 'max:1000'],
            'terminal' => ['required', 'array'],
            'terminal.code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'terminal.name' => ['required', 'string', 'max:255'],
            'terminal.type' => ['sometimes', Rule::in(['pos', 'kds'])],
            'station' => ['required', 'array'],
            'station.code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'station.name' => ['required', 'string', 'max:255'],
            'station.sla_seconds' => ['sometimes', 'integer', 'min:10', 'max:86400'],
            'payment_methods' => ['sometimes', 'array', 'min:1', 'max:20'],
            'payment_methods.*.code' => ['required', 'string', 'max:40', 'distinct'],
            'payment_methods.*.name' => ['required', 'string', 'max:255'],
            'payment_methods.*.kind' => ['required', Rule::in(['cash', 'transfer', 'card'])],
            'area' => ['sometimes', 'array'],
            'area.code' => ['required_with:area', 'string', 'max:40'],
            'area.name' => ['required_with:area', 'string', 'max:255'],
            'table_count' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'sample_menu' => ['sometimes', 'boolean'],
        ])->validate();

        if (! in_array($data['outlet']['timezone'], DateTimeZone::listIdentifiers(), true)) {
            throw new FnbValidationException('Unknown IANA outlet timezone.');
        }
        $data['outlet']['currency'] = strtoupper($data['outlet']['currency']);

        return $this->commands->run($context, 'onboard', $data, $idempotencyKey, function () use ($context, $data, $idempotencyKey): array {
            if (DB::table('fnb_outlets')->where('website_key', $context->websiteKey)->exists()) {
                throw new FnbConflictException('This website has already completed F&B onboarding.');
            }

            DB::table('fnb_site_settings')->where('website_key', $context->websiteKey)->update([
                'is_active' => true,
                'operational_state' => 'active',
                'default_currency' => $data['outlet']['currency'],
                'default_timezone' => $data['outlet']['timezone'],
                'settings' => json_encode(['cash_rounding_step_minor' => $data['outlet']['currency'] === 'VND' ? 500 : 1], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            $now = now();
            $outletId = DB::table('fnb_outlets')->insertGetId([
                'website_key' => $context->websiteKey,
                'public_id' => (string) Str::uuid(),
                'code' => strtoupper($data['outlet']['code']),
                'name' => $data['outlet']['name'],
                'timezone' => $data['outlet']['timezone'],
                'currency' => $data['outlet']['currency'],
                'phone' => $data['outlet']['phone'] ?? null,
                'address' => $data['outlet']['address'] ?? null,
                'status' => 'active',
                'settings' => json_encode(['cash_rounding_step_minor' => $data['outlet']['currency'] === 'VND' ? 500 : 1], JSON_THROW_ON_ERROR),
                'version' => 1,
                'created_by' => $context->actorId,
                'updated_by' => $context->actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $terminalId = DB::table('fnb_terminals')->insertGetId([
                'website_key' => $context->websiteKey,
                'outlet_id' => $outletId,
                'public_id' => (string) Str::uuid(),
                'code' => strtoupper($data['terminal']['code']),
                'name' => $data['terminal']['name'],
                'type' => $data['terminal']['type'] ?? 'pos',
                'status' => 'active',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $stationId = DB::table('fnb_prep_stations')->insertGetId([
                'website_key' => $context->websiteKey,
                'outlet_id' => $outletId,
                'code' => strtoupper($data['station']['code']),
                'name' => $data['station']['name'],
                'sla_seconds' => $data['station']['sla_seconds'] ?? 600,
                'sort_order' => 0,
                'status' => 'active',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $paymentMethods = $data['payment_methods'] ?? [
                ['code' => 'CASH', 'name' => 'Tiền mặt', 'kind' => 'cash'],
                ['code' => 'TRANSFER', 'name' => 'Chuyển khoản', 'kind' => 'transfer'],
            ];
            $methodIds = [];
            foreach ($paymentMethods as $sort => $method) {
                $methodIds[] = DB::table('fnb_payment_methods')->insertGetId([
                    'website_key' => $context->websiteKey,
                    'outlet_id' => $outletId,
                    'code' => strtoupper($method['code']),
                    'name' => $method['name'],
                    'kind' => $method['kind'],
                    'requires_reference' => $method['kind'] !== 'cash',
                    'sort_order' => $sort,
                    'status' => 'active',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $menuId = DB::table('fnb_menus')->insertGetId([
                'website_key' => $context->websiteKey,
                'code' => 'DEFAULT',
                'name' => 'Menu chính',
                'status' => 'published',
                'version' => 1,
                'published_at' => $now,
                'published_by' => $context->actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('fnb_menu_outlets')->insert([
                'website_key' => $context->websiteKey,
                'menu_id' => $menuId,
                'outlet_id' => $outletId,
                'channel' => 'pos',
                'default_slot' => 'default',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $tableCount = (int) ($data['table_count'] ?? 5);
            $areaId = null;
            $tableIds = [];
            if ($tableCount > 0 || isset($data['area'])) {
                $area = $data['area'] ?? ['code' => 'MAIN', 'name' => 'Khu vực chính'];
                $areaId = DB::table('fnb_service_areas')->insertGetId([
                    'website_key' => $context->websiteKey,
                    'outlet_id' => $outletId,
                    'code' => strtoupper($area['code']),
                    'name' => $area['name'],
                    'sort_order' => 0,
                    'status' => 'active',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                for ($number = 1; $number <= $tableCount; $number++) {
                    $tableIds[] = DB::table('fnb_dining_tables')->insertGetId([
                        'website_key' => $context->websiteKey,
                        'outlet_id' => $outletId,
                        'service_area_id' => $areaId,
                        'code' => sprintf('B%02d', $number),
                        'name' => 'Bàn '.$number,
                        'capacity' => 4,
                        'status' => 'available',
                        'sort_order' => $number - 1,
                        'version' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $sampleItemIds = ($data['sample_menu'] ?? false)
                ? $this->createSampleMenu($context->websiteKey, $outletId, $menuId, $stationId, $now)
                : [];

            DB::table('fnb_outlet_staff')->insertOrIgnore([
                'website_key' => $context->websiteKey,
                'outlet_id' => $outletId,
                'admin_id' => $context->actorId,
                'is_active' => true,
                'is_default' => true,
                'assigned_by' => $context->actorId,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $scoped = new FnbContext($context->websiteKey, $outletId, $context->actorId, $terminalId);
            $event = $this->outbox->emit($scoped, 'outlet', $outletId, 1, 'fnb.outlet.onboarded', [
                'outlet_id' => $outletId,
                'terminal_id' => $terminalId,
                'station_id' => $stationId,
                'idempotency_key_hash' => hash('sha256', $idempotencyKey),
            ]);

            return [
                'resource' => [
                    'outlet' => $this->record('fnb_outlets', $outletId),
                    'terminal' => $this->record('fnb_terminals', $terminalId),
                    'station' => $this->record('fnb_prep_stations', $stationId),
                    'menu' => $this->record('fnb_menus', $menuId),
                    'area_id' => $areaId,
                    'table_ids' => $tableIds,
                    'sample_item_ids' => $sampleItemIds,
                    'payment_method_ids' => $methodIds,
                ],
                'events' => [$event],
                'meta' => ['resource_type' => 'outlet', 'resource_id' => $outletId, 'version' => 1],
            ];
        });
    }

    /** @param array<string,mixed> $filters */
    public function catalog(FnbContext $context, array $filters = []): array
    {
        $this->assertOutlet($context);
        $includeInactive = filter_var($filters['include_inactive'] ?? false, FILTER_VALIDATE_BOOL);
        $items = DB::table('fnb_menu_items')
            ->where('website_key', $context->websiteKey)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(! ($filters['status'] ?? null) && ! $includeInactive, fn ($query) => $query->where('status', 'active'))
            ->when($filters['query'] ?? null, fn ($query, $term) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.addcslashes((string) $term, '%_').'%')
                ->orWhere('code', 'like', '%'.addcslashes((string) $term, '%_').'%')))
            ->whereNull('archived_at')
            ->orderBy('name')
            ->limit(500)
            ->get();
        $ids = $items->pluck('id');
        $variants = DB::table('fnb_item_variants')->where('website_key', $context->websiteKey)
            ->whereIn('item_id', $ids)->when(! $includeInactive, fn ($query) => $query->where('status', 'active'))
            ->orderBy('sort_order')->get()->groupBy('item_id');
        $availability = DB::table('fnb_outlet_item_states')->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)->whereIn('item_id', $ids)->get()->keyBy('item_id');
        $routes = DB::table('fnb_item_station_routes')->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)->whereIn('item_id', $ids)->get()->groupBy('item_id');
        $links = DB::table('fnb_item_modifier_groups')->where('website_key', $context->websiteKey)
            ->whereIn('item_id', $ids)->orderBy('sort_order')->get()->groupBy('item_id');
        $groups = DB::table('fnb_modifier_groups')->where('website_key', $context->websiteKey)
            ->when(! $includeInactive, fn ($query) => $query->where('status', 'active'))->orderBy('name')->get()->keyBy('id');
        $groupIds = $groups->keys();
        $options = DB::table('fnb_modifier_options')->where('website_key', $context->websiteKey)->whereIn('group_id', $groupIds)
            ->when(! $includeInactive, fn ($query) => $query->where('status', 'active'))->orderBy('sort_order')->get()->groupBy('group_id');
        $categoryIds = DB::table('fnb_menu_category_items')->where('website_key', $context->websiteKey)
            ->whereIn('item_id', $ids)->where('status', 'active')->get()->groupBy('item_id');

        $itemResources = $items->map(function (object $item) use ($variants, $availability, $routes, $links, $groups, $options, $categoryIds): array {
            $state = $availability->get($item->id);
            $available = $state === null || (bool) $state->is_available
                || ($state->sold_out_until !== null && now()->greaterThanOrEqualTo($state->sold_out_until));
            $modifierGroups = ($links[$item->id] ?? collect())->map(function (object $link) use ($groups, $options): ?array {
                $group = $groups->get($link->group_id);
                if ($group === null) {
                    return null;
                }

                return $this->decode($group) + [
                    'min_select' => $link->min_select_override ?? $group->min_select,
                    'max_select' => $link->max_select_override ?? $group->max_select,
                    'options' => ($options[$group->id] ?? collect())->map(fn (object $option): array => $this->decode($option))->all(),
                ];
            })->filter()->values()->all();

            return $this->decode($item) + [
                'available' => $available,
                'availability_version' => (int) ($state->version ?? 1),
                'sold_out_until' => $state->sold_out_until ?? null,
                'category_ids' => ($categoryIds[$item->id] ?? collect())->pluck('category_id')->map(fn ($id): int => (int) $id)->values()->all(),
                'variants' => ($variants[$item->id] ?? collect())->map(fn (object $variant): array => $this->decode($variant))->all(),
                'modifier_groups' => $modifierGroups,
                'station_routes' => ($routes[$item->id] ?? collect())->map(fn (object $route): array => $this->decode($route))->all(),
            ];
        })->values()->all();

        return ['resource' => [
            'outlet' => $this->record('fnb_outlets', $context->outletId),
            'menus' => DB::table('fnb_menus')->where('website_key', $context->websiteKey)->whereNull('archived_at')->orderBy('name')->get()->map(fn ($row) => $this->decode($row))->all(),
            'categories' => DB::table('fnb_menu_categories')->where('website_key', $context->websiteKey)
                ->when(! $includeInactive, fn ($query) => $query->where('status', 'active'))->orderBy('sort_order')->get()->map(fn ($row) => $this->decode($row))->all(),
            'items' => $itemResources,
            'stations' => DB::table('fnb_prep_stations')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
                ->when(! $includeInactive, fn ($query) => $query->where('status', 'active'))->orderBy('sort_order')->get()->map(fn ($row) => $this->decode($row))->all(),
            'modifier_groups' => $groups->values()->map(fn (object $group): array => $this->decode($group) + [
                'options' => ($options[$group->id] ?? collect())->map(fn (object $option): array => $this->decode($option))->all(),
            ])->all(),
            'payment_methods' => DB::table('fnb_payment_methods')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('status', 'active')->orderBy('sort_order')->get()->map(fn ($row) => $this->decode($row))->all(),
        ]];
    }

    /** @param array<string,mixed> $input */
    public function saveCatalogItem(FnbContext $context, array $input, string $idempotencyKey, ?int $expectedVersion = null): array
    {
        $normalized = $input;
        $replaceVariants = array_key_exists('variants', $input);
        if (! $replaceVariants && array_key_exists('variant', $input)) {
            $normalized['variants'] = [$input['variant']];
        }
        if (! array_key_exists('modifier_group_ids', $normalized) && array_key_exists('modifier_groups', $input)) {
            $normalized['modifier_group_ids'] = array_map(
                fn ($group) => is_array($group) ? ($group['id'] ?? null) : $group,
                is_array($input['modifier_groups']) ? $input['modifier_groups'] : [],
            );
        }
        $replaceCategories = array_key_exists('category_ids', $input) || array_key_exists('category_id', $input);
        if (! array_key_exists('category_ids', $normalized) && array_key_exists('category_id', $input)) {
            $normalized['category_ids'] = $input['category_id'] === null ? [] : [$input['category_id']];
        }

        $data = validator($normalized, [
            'id' => ['nullable', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'sku' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'item_type' => ['sometimes', Rule::in(['prepared', 'packaged', 'service'])],
            'tax_category' => ['sometimes', Rule::in(['standard', 'zero_rated', 'not_subject', 'exempt'])],
            'tax_rate_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'tax_inclusive' => ['sometimes', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'category_ids' => ['sometimes', 'array', 'max:50'],
            'category_ids.*' => ['integer', 'min:1', 'distinct'],
            'variants' => ['required', 'array', 'min:1', 'max:50'],
            'variants.*' => ['required', 'array'],
            'variants.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'variants.*.code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.base_price_minor' => ['required', 'integer', 'min:0', 'max:9000000000000'],
            'variants.*.is_default' => ['sometimes', 'boolean'],
            'variants.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'variants.*.status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'variants.*.station_id' => ['nullable', 'integer', 'min:1'],
            'station_id' => ['nullable', 'integer', 'min:1'],
            'modifier_group_ids' => ['sometimes', 'array', 'max:30'],
            'modifier_group_ids.*' => ['integer', 'min:1', 'distinct'],
        ])->validate();

        $variantCodes = array_map(fn (array $variant): string => strtoupper($variant['code']), $data['variants']);
        if (count(array_unique($variantCodes)) !== count($variantCodes)) {
            throw new FnbValidationException('Variant codes must be unique within an item.');
        }
        $activeVariantIndexes = array_keys(array_filter($data['variants'], fn (array $variant): bool => ($variant['status'] ?? 'active') === 'active'));
        if ($activeVariantIndexes === []) {
            throw new FnbValidationException('A menu item must retain at least one active variant.');
        }
        $defaultIndexes = array_keys(array_filter($data['variants'], fn (array $variant): bool => (bool) ($variant['is_default'] ?? false)));
        if (count($defaultIndexes) > 1) {
            throw new FnbValidationException('Exactly one variant may be the default.');
        }
        $defaultIndex = $defaultIndexes[0] ?? $activeVariantIndexes[0];
        if (($data['variants'][$defaultIndex]['status'] ?? 'active') !== 'active') {
            throw new FnbValidationException('The default variant must be active.');
        }

        $itemId = isset($data['id']) ? (int) $data['id'] : null;
        if ($itemId !== null && $expectedVersion === null) {
            throw new FnbValidationException('expectedVersion is required when updating a menu item.');
        }

        return $this->commands->run($context, 'catalog.item.save', $data + ['expected_version' => $expectedVersion, 'replace_variants' => $replaceVariants], $idempotencyKey, function () use ($context, $data, $itemId, $expectedVersion, $replaceVariants, $replaceCategories, $defaultIndex): array {
            $now = now();
            $existing = $itemId === null ? null : DB::table('fnb_menu_items')
                ->where('website_key', $context->websiteKey)->where('id', $itemId)->lockForUpdate()->first();
            if ($itemId !== null && $existing === null) {
                throw new FnbNotFoundException('Menu item was not found.');
            }
            if ($existing !== null && (int) $existing->version !== $expectedVersion) {
                throw $this->versionConflict('menu_item', $itemId, (int) $existing->version, $existing);
            }

            $itemValues = [
                'code' => strtoupper($data['code']),
                'sku' => $data['sku'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'item_type' => $data['item_type'] ?? 'prepared',
                'tax_category' => $data['tax_category'] ?? 'standard',
                'tax_rate_bps' => $data['tax_rate_bps'] ?? 0,
                'tax_inclusive' => $data['tax_inclusive'] ?? true,
                'image_url' => $data['image_url'] ?? null,
                'status' => $data['status'] ?? 'active',
                'version' => $existing === null ? 1 : (int) $existing->version + 1,
                'updated_at' => $now,
            ];
            if ($existing === null) {
                $itemId = DB::table('fnb_menu_items')->insertGetId($itemValues + [
                    'website_key' => $context->websiteKey,
                    'public_id' => (string) Str::uuid(),
                    'created_at' => $now,
                ]);
            } else {
                DB::table('fnb_menu_items')->where('id', $itemId)->update($itemValues);
            }

            $existingVariants = DB::table('fnb_item_variants')->where('website_key', $context->websiteKey)
                ->where('item_id', $itemId)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $requestedVariantIds = collect($data['variants'])->pluck('id')->filter()->map(fn ($id): int => (int) $id);
            if ($requestedVariantIds->contains(fn (int $id): bool => ! $existingVariants->has($id))) {
                throw new FnbNotFoundException('A menu variant was not found on this item.');
            }

            // Clear the sparse unique marker before assigning the one canonical
            // default; this also makes default changes safe in one transaction.
            DB::table('fnb_item_variants')->where('website_key', $context->websiteKey)->where('item_id', $itemId)
                ->whereNotNull('default_slot')->update(['is_default' => false, 'default_slot' => null, 'updated_at' => $now]);

            $variantIds = [];
            foreach ($data['variants'] as $index => $variantInput) {
                $variant = isset($variantInput['id']) ? $existingVariants->get((int) $variantInput['id']) : null;
                $isDefault = $index === $defaultIndex;
                $variantValues = [
                    'code' => strtoupper($variantInput['code']),
                    'name' => $variantInput['name'],
                    'base_price_minor' => MinorMoney::assertMinor((int) $variantInput['base_price_minor'], "variants.{$index}.base_price_minor"),
                    'is_default' => $isDefault,
                    'default_slot' => $isDefault ? 'default' : null,
                    'sort_order' => (int) ($variantInput['sort_order'] ?? $index),
                    'status' => $variantInput['status'] ?? 'active',
                    'version' => $variant === null ? 1 : (int) $variant->version + 1,
                    'updated_at' => $now,
                ];
                if ($variant === null) {
                    $variantId = DB::table('fnb_item_variants')->insertGetId($variantValues + [
                        'website_key' => $context->websiteKey,
                        'item_id' => $itemId,
                        'created_at' => $now,
                    ]);
                } else {
                    $variantId = (int) $variant->id;
                    DB::table('fnb_item_variants')->where('id', $variantId)->update($variantValues);
                }
                $variantIds[] = $variantId;

                $hasStation = array_key_exists('station_id', $variantInput) || array_key_exists('station_id', $data);
                $stationId = $variantInput['station_id'] ?? ($data['station_id'] ?? null);
                if ($hasStation) {
                    DB::table('fnb_item_station_routes')->where('website_key', $context->websiteKey)
                        ->where('outlet_id', $context->outletId)->where('item_id', $itemId)->where('variant_key', $variantId)->delete();
                    if ($stationId !== null) {
                        $station = DB::table('fnb_prep_stations')->where('website_key', $context->websiteKey)
                            ->where('outlet_id', $context->outletId)->where('id', $stationId)->where('status', 'active')->first();
                        if ($station === null) {
                            throw new FnbNotFoundException('Preparation station was not found.');
                        }
                        DB::table('fnb_item_station_routes')->insert([
                            'website_key' => $context->websiteKey,
                            'outlet_id' => $context->outletId,
                            'item_id' => $itemId,
                            'variant_id' => $variantId,
                            'variant_key' => $variantId,
                            'prep_station_id' => $station->id,
                            'priority' => 100,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
            if ($replaceVariants) {
                DB::table('fnb_item_variants')->where('website_key', $context->websiteKey)->where('item_id', $itemId)
                    ->whereNotIn('id', $variantIds)->update([
                        'status' => 'inactive', 'is_default' => false, 'default_slot' => null,
                        'version' => DB::raw('version + 1'), 'updated_at' => $now,
                    ]);
            }

            DB::table('fnb_outlet_item_states')->upsert([[
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'item_id' => $itemId,
                'is_available' => true,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['outlet_id', 'item_id'], ['updated_at']);

            if ($replaceCategories) {
                $categoryIds = array_values(array_unique(array_map('intval', $data['category_ids'] ?? [])));
                $categories = DB::table('fnb_menu_categories')->where('website_key', $context->websiteKey)
                    ->whereIn('id', $categoryIds)->get()->keyBy('id');
                if ($categories->count() !== count($categoryIds)) {
                    throw new FnbNotFoundException('One or more menu categories were not found.');
                }
                DB::table('fnb_menu_category_items')->where('website_key', $context->websiteKey)->where('item_id', $itemId)->delete();
                foreach ($categoryIds as $sort => $categoryId) {
                    $category = $categories->get($categoryId);
                    DB::table('fnb_menu_category_items')->insert([
                        'website_key' => $context->websiteKey,
                        'menu_id' => $category->menu_id,
                        'category_id' => $category->id,
                        'item_id' => $itemId,
                        'sort_order' => $sort,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (array_key_exists('modifier_group_ids', $data)) {
                $groupIds = array_values(array_unique(array_map('intval', $data['modifier_group_ids'])));
                $found = DB::table('fnb_modifier_groups')->where('website_key', $context->websiteKey)->whereIn('id', $groupIds)->count();
                if ($found !== count($groupIds)) {
                    throw new FnbNotFoundException('One or more modifier groups were not found.');
                }
                DB::table('fnb_item_modifier_groups')->where('website_key', $context->websiteKey)->where('item_id', $itemId)->delete();
                foreach ($groupIds as $sort => $groupId) {
                    DB::table('fnb_item_modifier_groups')->insert([
                        'website_key' => $context->websiteKey,
                        'item_id' => $itemId,
                        'group_id' => $groupId,
                        'sort_order' => $sort,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $version = (int) $itemValues['version'];
            $event = $this->outbox->emit($context, 'menu_item', $itemId, $version, 'fnb.menu_item.saved', [
                'item_id' => $itemId,
                'variant_ids' => $variantIds,
                'version' => $version,
            ]);

            return [
                'resource' => $this->itemResource($context, $itemId),
                'events' => [$event],
                'meta' => ['resource_type' => 'menu_item', 'resource_id' => $itemId, 'version' => $version],
            ];
        });
    }

    public function setAvailability(FnbContext $context, int $itemId, bool $available, ?string $soldOutUntil, ?string $reason, string $idempotencyKey, int $expectedVersion): array
    {
        if ($soldOutUntil !== null && strtotime($soldOutUntil) === false) {
            throw new FnbValidationException('soldOutUntil must be an ISO-compatible date-time.');
        }

        $payload = compact('itemId', 'available', 'soldOutUntil', 'reason', 'expectedVersion');

        return $this->commands->run($context, 'catalog.item.availability', $payload, $idempotencyKey, function () use ($context, $itemId, $available, $soldOutUntil, $reason, $expectedVersion): array {
            $itemExists = DB::table('fnb_menu_items')->where('website_key', $context->websiteKey)->where('id', $itemId)->exists();
            if (! $itemExists) {
                throw new FnbNotFoundException('Menu item was not found.');
            }
            $state = DB::table('fnb_outlet_item_states')->where('website_key', $context->websiteKey)
                ->where('outlet_id', $context->outletId)->where('item_id', $itemId)->lockForUpdate()->first();
            if ($state === null) {
                throw new FnbConflictException('Outlet item state is missing; save the catalog item first.');
            }
            if ((int) $state->version !== $expectedVersion) {
                throw $this->versionConflict('outlet_item_state', $itemId, (int) $state->version, $state);
            }

            $version = (int) $state->version + 1;
            DB::table('fnb_outlet_item_states')->where('id', $state->id)->update([
                'is_available' => $available,
                'sold_out_until' => $available ? null : $soldOutUntil,
                'reason' => $available ? null : $reason,
                'version' => $version,
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'outlet_item_state', $state->id, $version, 'fnb.menu_item.availability_changed', [
                'item_id' => $itemId,
                'available' => $available,
                'sold_out_until' => $available ? null : $soldOutUntil,
            ]);

            return [
                'resource' => $this->decode(DB::table('fnb_outlet_item_states')->where('id', $state->id)->firstOrFail()),
                'events' => [$event],
                'meta' => ['resource_type' => 'outlet_item_state', 'resource_id' => $state->id, 'version' => $version],
            ];
        });
    }

    public function searchCustomers(FnbContext $context, string $query, int $limit = 20): array
    {
        $this->assertOutlet($context);
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return ['resource' => ['items' => []]];
        }
        $phone = preg_replace('/\D+/', '', $query);
        $escaped = addcslashes($query, '%_');
        $customers = DB::table('fnb_customer_profiles')->where('website_key', $context->websiteKey)
            ->where('status', 'active')
            ->where(fn ($builder) => $builder
                ->where('code', 'like', $escaped.'%')
                ->orWhere('name', 'like', '%'.$escaped.'%')
                ->when($phone !== '', fn ($inner) => $inner->orWhere('phone_normalized', 'like', '%'.$phone)))
            ->orderBy('name')->limit(max(1, min($limit, 50)))
            ->get(['id', 'public_id', 'code', 'name', 'phone_normalized', 'email', 'status', 'version']);

        return ['resource' => ['items' => $customers->map(fn ($row) => $this->decode($row))->all()]];
    }

    /** @param array<string,mixed> $input */
    public function createCustomer(FnbContext $context, array $input, string $idempotencyKey): array
    {
        $data = validator($input, [
            'code' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'privacy_consent' => ['sometimes', 'boolean'],
            'marketing_consent' => ['sometimes', 'boolean'],
        ])->validate();
        $phone = isset($data['phone']) ? preg_replace('/\D+/', '', $data['phone']) : null;
        if ($phone === '') {
            $phone = null;
        }
        $payload = $data + ['phone_normalized' => $phone];

        return $this->commands->run($context, 'customer.create', $payload, $idempotencyKey, function () use ($context, $data, $phone): array {
            if ($phone !== null && DB::table('fnb_customer_profiles')->where('website_key', $context->websiteKey)->where('phone_normalized', $phone)->exists()) {
                throw new FnbConflictException('A customer with this phone already exists.');
            }
            $now = now();
            $id = DB::table('fnb_customer_profiles')->insertGetId([
                'website_key' => $context->websiteKey,
                'public_id' => (string) Str::uuid(),
                'code' => $data['code'] ?? null,
                'name' => $data['name'],
                'phone_normalized' => $phone,
                'email' => isset($data['email']) ? mb_strtolower($data['email']) : null,
                'birthday' => $data['birthday'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'active',
                'privacy_consented_at' => ($data['privacy_consent'] ?? false) ? $now : null,
                'marketing_consented_at' => ($data['marketing_consent'] ?? false) ? $now : null,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('fnb_customer_events')->insert([
                'website_key' => $context->websiteKey,
                'customer_profile_id' => $id,
                'event_type' => 'created',
                'actor_id' => $context->actorId,
                'changed_fields' => json_encode(array_keys($data), JSON_THROW_ON_ERROR),
                'evidence_hash' => hash('sha256', json_encode([$phone, $data['email'] ?? null], JSON_THROW_ON_ERROR)),
                'occurred_at' => $now,
            ]);

            return [
                'resource' => $this->record('fnb_customer_profiles', $id),
                'meta' => ['resource_type' => 'customer_profile', 'resource_id' => $id, 'version' => 1],
            ];
        });
    }

    private function assertOutlet(FnbContext $context): void
    {
        if ($context->outletId < 1 || ! DB::table('fnb_outlets')->where('website_key', $context->websiteKey)->where('id', $context->outletId)->where('status', 'active')->exists()) {
            throw new FnbNotFoundException('Active outlet was not found.');
        }
    }

    /** @return list<int> */
    private function createSampleMenu(string $websiteKey, int $outletId, int $menuId, int $stationId, mixed $now): array
    {
        $categoryId = DB::table('fnb_menu_categories')->insertGetId([
            'website_key' => $websiteKey,
            'menu_id' => $menuId,
            'code' => 'COFFEE',
            'name' => 'Cà phê',
            'sort_order' => 0,
            'status' => 'active',
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $samples = [
            ['code' => 'SAMPLE_BLACK_COFFEE', 'name' => 'Cà phê đen', 'price' => 25000],
            ['code' => 'SAMPLE_MILK_COFFEE', 'name' => 'Cà phê sữa', 'price' => 29000],
        ];
        $itemIds = [];
        foreach ($samples as $sort => $sample) {
            $itemId = DB::table('fnb_menu_items')->insertGetId([
                'website_key' => $websiteKey,
                'public_id' => (string) Str::uuid(),
                'code' => $sample['code'],
                'name' => $sample['name'],
                'item_type' => 'prepared',
                'tax_category' => 'standard',
                'tax_rate_bps' => 0,
                'tax_inclusive' => true,
                'status' => 'active',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $variantId = DB::table('fnb_item_variants')->insertGetId([
                'website_key' => $websiteKey,
                'item_id' => $itemId,
                'code' => 'DEFAULT',
                'name' => 'Mặc định',
                'base_price_minor' => $sample['price'],
                'is_default' => true,
                'default_slot' => 'default',
                'sort_order' => 0,
                'status' => 'active',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('fnb_menu_category_items')->insert([
                'website_key' => $websiteKey,
                'menu_id' => $menuId,
                'category_id' => $categoryId,
                'item_id' => $itemId,
                'sort_order' => $sort,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('fnb_item_station_routes')->insert([
                'website_key' => $websiteKey,
                'outlet_id' => $outletId,
                'item_id' => $itemId,
                'variant_id' => $variantId,
                'variant_key' => $variantId,
                'prep_station_id' => $stationId,
                'priority' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('fnb_outlet_item_states')->insert([
                'website_key' => $websiteKey,
                'outlet_id' => $outletId,
                'item_id' => $itemId,
                'is_available' => true,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $itemIds[] = $itemId;
        }

        return $itemIds;
    }

    /** @return array<string,mixed> */
    private function itemResource(FnbContext $context, int $itemId): array
    {
        $item = DB::table('fnb_menu_items')->where('website_key', $context->websiteKey)->where('id', $itemId)->firstOrFail();
        $state = DB::table('fnb_outlet_item_states')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('item_id', $itemId)->first();
        $groupLinks = DB::table('fnb_item_modifier_groups')->where('website_key', $context->websiteKey)
            ->where('item_id', $itemId)->orderBy('sort_order')->get();

        return $this->decode($item) + [
            'availability' => $state ? $this->decode($state) : null,
            'category_ids' => DB::table('fnb_menu_category_items')->where('website_key', $context->websiteKey)
                ->where('item_id', $itemId)->where('status', 'active')->orderBy('sort_order')->pluck('category_id')->map(fn ($id): int => (int) $id)->all(),
            'variants' => DB::table('fnb_item_variants')->where('website_key', $context->websiteKey)->where('item_id', $itemId)->orderBy('sort_order')->get()->map(fn ($row) => $this->decode($row))->all(),
            'station_routes' => DB::table('fnb_item_station_routes')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('item_id', $itemId)->get()->map(fn ($row) => $this->decode($row))->all(),
            'modifier_group_ids' => $groupLinks->pluck('group_id')->map(fn ($id): int => (int) $id)->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function record(string $table, int $id): array
    {
        return $this->decode(DB::table($table)->where('id', $id)->firstOrFail());
    }

    /** @return array<string,mixed> */
    private function decode(object $record): array
    {
        $values = (array) $record;
        foreach ($values as $key => $value) {
            if (is_string($value) && (str_ends_with($key, '_snapshot') || in_array($key, ['settings', 'service_charge_policy'], true))) {
                $values[$key] = json_decode($value, true) ?? $value;
            }
            $numericId = ($key === 'id' || str_ends_with($key, '_id'))
                && $value !== null && preg_match('/^\d+$/', (string) $value) === 1;
            if (str_ends_with($key, '_minor') || $numericId || in_array($key, ['version', 'tax_rate_bps'], true)) {
                $values[$key] = $value === null ? null : (int) $value;
            }
        }

        return $values;
    }

    private function versionConflict(string $type, int $id, int $version, object $snapshot): FnbConflictException
    {
        return new FnbConflictException('Aggregate version is stale.', [
            'current_versions' => ["{$type}:{$id}" => $version],
            'current_snapshot' => $this->decode($snapshot),
        ]);
    }
}
