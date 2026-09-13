<?php

namespace Modules\FnbPos\Services;

use Illuminate\Support\Facades\DB;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Domain\FnbNotFoundException;
use Modules\FnbPos\Domain\FnbValidationException;
use Modules\FnbPos\Domain\MinorMoney;

final class FnbPricingService
{
    /**
     * Calculate tax from an already tax-exclusive, non-negative net basis.
     * Both inclusive and exclusive catalogue prices are normalized to that
     * basis when a line snapshot is first created.
     */
    public function taxOnPreTaxNet(int $netMinor, int $rateBps): int
    {
        MinorMoney::assertMinor($netMinor, 'net_minor');
        if ($rateBps < 0 || $rateBps > 10_000) {
            throw new FnbValidationException('tax_rate_bps must be between 0 and 10000.');
        }

        return $this->roundProductRatio($netMinor, $rateBps, 10_000);
    }

    /**
     * @param  list<int>  $modifierOptionIds
     * @return array<string,mixed>
     */
    public function priceLine(FnbContext $context, int $variantId, string|int|float $quantity, array $modifierOptionIds = []): array
    {
        $quantityMicros = MinorMoney::quantityMicros($quantity);
        $variant = DB::table('fnb_item_variants as variants')
            ->join('fnb_menu_items as items', function ($join): void {
                $join->on('items.website_key', '=', 'variants.website_key')->on('items.id', '=', 'variants.item_id');
            })
            ->where('variants.website_key', $context->websiteKey)
            ->where('variants.id', $variantId)
            ->where('variants.status', 'active')
            ->where('items.status', 'active')
            ->select([
                'variants.*', 'items.code as item_code', 'items.name as item_name',
                'items.tax_category', 'items.tax_rate_bps', 'items.tax_inclusive', 'items.item_type',
            ])->first();
        if ($variant === null) {
            throw new FnbNotFoundException('Active menu variant was not found.');
        }

        $availability = DB::table('fnb_outlet_item_states')
            ->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)
            ->where('item_id', $variant->item_id)
            ->first();
        if ($availability !== null && (! (bool) $availability->is_available)
            && ($availability->sold_out_until === null || now()->lessThan($availability->sold_out_until))) {
            throw new FnbConflictException('Menu item is sold out.', ['item_id' => (int) $variant->item_id]);
        }

        $priceMinor = $this->outletPrice($context, (int) $variant->id, (int) $variant->base_price_minor);
        MinorMoney::assertMinor($priceMinor, 'base_price_minor');
        $modifiers = $this->resolveModifiers($context, (int) $variant->item_id, $modifierOptionIds);
        $unitMinor = $priceMinor + array_sum(array_column($modifiers, 'unit_price_delta_minor'));
        if ($unitMinor < 0) {
            throw new FnbValidationException('Modifier total cannot make an item price negative.');
        }

        $grossBase = MinorMoney::multiply($unitMinor, $quantityMicros);
        $taxRate = (int) $variant->tax_rate_bps;
        if ((bool) $variant->tax_inclusive) {
            $subtotal = $this->roundProductRatio($grossBase, 10_000, 10_000 + $taxRate);
            $tax = $grossBase - $subtotal;
            $total = $grossBase;
        } else {
            $subtotal = $grossBase;
            $tax = $this->roundProductRatio($subtotal, $taxRate, 10_000);
            if ($tax > PHP_INT_MAX - $subtotal) {
                throw new FnbValidationException('Tax-inclusive line total overflow.');
            }
            $total = $subtotal + $tax;
        }

        $route = DB::table('fnb_item_station_routes')
            ->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)
            ->where('item_id', $variant->item_id)
            ->whereIn('variant_key', [(int) $variant->id, 0])
            ->orderByRaw('CASE WHEN variant_key = ? THEN 0 ELSE 1 END', [(int) $variant->id])
            ->orderByDesc('priority')
            ->first();
        if ($variant->item_type === 'prepared' && $route === null) {
            throw new FnbConflictException('Prepared menu item has no station route.', ['item_id' => (int) $variant->item_id]);
        }

        $recipe = $this->recipeSnapshot($context, (int) $variant->id);

        return [
            'item_id' => (int) $variant->item_id,
            'variant_id' => (int) $variant->id,
            'item_code_snapshot' => $variant->item_code,
            'item_name_snapshot' => $variant->item_name,
            'variant_code_snapshot' => $variant->code,
            'variant_name_snapshot' => $variant->name,
            'tax_category_snapshot' => $variant->tax_category,
            'tax_rate_bps_snapshot' => $taxRate,
            'tax_inclusive_snapshot' => (bool) $variant->tax_inclusive,
            'quantity_micros' => $quantityMicros,
            'ordered_quantity' => MinorMoney::formatQuantity($quantityMicros),
            'original_unit_price_minor' => $priceMinor,
            'unit_price_minor' => $unitMinor,
            'subtotal_minor' => $subtotal,
            'discount_total_minor' => 0,
            'tax_total_minor' => $tax,
            'service_charge_total_minor' => 0,
            'pricing_rounding_minor' => 0,
            'total_minor' => $total,
            'prep_station_id' => $route?->prep_station_id,
            'recipe_id' => $recipe['recipe_id'],
            'recipe_snapshot' => $recipe['snapshot'],
            'recipe_snapshot_hash' => $recipe['hash'],
            'modifiers' => $modifiers,
        ];
    }

    private function outletPrice(FnbContext $context, int $variantId, int $fallback): int
    {
        $price = DB::table('fnb_price_book_outlets as assignments')
            ->join('fnb_price_books as books', function ($join): void {
                $join->on('books.website_key', '=', 'assignments.website_key')->on('books.id', '=', 'assignments.price_book_id');
            })
            ->join('fnb_price_book_variant_prices as prices', function ($join) use ($variantId): void {
                $join->on('prices.website_key', '=', 'books.website_key')
                    ->on('prices.price_book_id', '=', 'books.id')
                    ->where('prices.variant_id', '=', $variantId);
            })
            ->where('assignments.website_key', $context->websiteKey)
            ->where('assignments.outlet_id', $context->outletId)
            ->where('assignments.channel', 'pos')
            ->whereNotNull('assignments.default_slot')
            ->where('books.status', 'active')
            ->where(fn ($query) => $query->whereNull('books.valid_from')->orWhere('books.valid_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('books.valid_to')->orWhere('books.valid_to', '>=', now()))
            ->orderByDesc('assignments.priority')
            ->value('prices.amount_minor');

        return $price === null ? $fallback : (int) $price;
    }

    /** @param list<int> $optionIds @return list<array<string,mixed>> */
    private function resolveModifiers(FnbContext $context, int $itemId, array $optionIds): array
    {
        $optionIds = array_values(array_unique(array_map('intval', $optionIds)));
        $limits = DB::table('fnb_item_modifier_groups as links')
            ->join('fnb_modifier_groups as groups', function ($join): void {
                $join->on('groups.website_key', '=', 'links.website_key')->on('groups.id', '=', 'links.group_id');
            })
            ->where('links.website_key', $context->websiteKey)->where('links.item_id', $itemId)
            ->where('groups.status', 'active')
            ->get([
                'groups.id as group_id', 'groups.min_select', 'groups.max_select',
                'links.min_select_override', 'links.max_select_override',
            ])->keyBy('group_id');
        if ($optionIds === []) {
            $required = $limits->contains(fn (object $limit): bool => (int) ($limit->min_select_override ?? $limit->min_select) > 0);
            if ($required) {
                throw new FnbValidationException('Required modifier selection is missing.');
            }

            return [];
        }

        $rows = DB::table('fnb_modifier_options as options')
            ->join('fnb_modifier_groups as groups', function ($join): void {
                $join->on('groups.website_key', '=', 'options.website_key')->on('groups.id', '=', 'options.group_id');
            })
            ->join('fnb_item_modifier_groups as links', function ($join) use ($itemId): void {
                $join->on('links.website_key', '=', 'groups.website_key')->on('links.group_id', '=', 'groups.id')->where('links.item_id', '=', $itemId);
            })
            ->where('options.website_key', $context->websiteKey)
            ->whereIn('options.id', $optionIds)
            ->where('options.status', 'active')
            ->where('groups.status', 'active')
            ->select([
                'options.id', 'options.group_id', 'options.code', 'options.name', 'options.base_price_delta_minor',
                'groups.code as group_code', 'groups.name as group_name', 'groups.min_select', 'groups.max_select',
                'links.min_select_override', 'links.max_select_override',
            ])->get();
        if ($rows->count() !== count($optionIds)) {
            throw new FnbValidationException('A modifier is inactive or does not belong to this item.');
        }

        $selectedByGroup = $rows->groupBy('group_id');
        foreach ($limits as $groupId => $limit) {
            $count = ($selectedByGroup[$groupId] ?? collect())->count();
            $min = (int) ($limit->min_select_override ?? $limit->min_select);
            $max = (int) ($limit->max_select_override ?? $limit->max_select);
            if ($count < $min || $count > $max) {
                throw new FnbValidationException('Modifier selection count is outside its group limits.');
            }
        }

        return $rows->map(function (object $row) use ($context): array {
            $recipe = $this->boundRecipeSnapshot($context, 'modifier', (int) $row->id);

            return [
                'modifier_group_id' => (int) $row->group_id,
                'modifier_option_id' => (int) $row->id,
                'group_code_snapshot' => $row->group_code,
                'group_name_snapshot' => $row->group_name,
                'option_code_snapshot' => $row->code,
                'option_name_snapshot' => $row->name,
                'quantity' => '1.000000',
                'unit_price_delta_minor' => (int) $row->base_price_delta_minor,
                'recipe_snapshot' => $recipe['snapshot'] === null ? null : json_encode($recipe['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'recipe_snapshot_hash' => $recipe['hash'],
            ];
        })->values()->all();
    }

    /** @return array{recipe_id:?int,snapshot:?array<string,mixed>,hash:?string} */
    private function recipeSnapshot(FnbContext $context, int $variantId): array
    {
        return $this->boundRecipeSnapshot($context, 'variant', $variantId);
    }

    /** @return array{recipe_id:?int,snapshot:?array<string,mixed>,hash:?string} */
    private function boundRecipeSnapshot(FnbContext $context, string $bindingType, int $subjectId): array
    {
        $table = $bindingType === 'variant' ? 'fnb_variant_recipes' : 'fnb_modifier_option_recipes';
        $column = $bindingType === 'variant' ? 'variant_id' : 'modifier_option_id';
        $recipe = DB::table($table.' as binding')
            ->join('fnb_recipes as recipe', function ($join): void {
                $join->on('recipe.website_key', '=', 'binding.website_key')->on('recipe.id', '=', 'binding.recipe_id');
            })
            ->where('binding.website_key', $context->websiteKey)
            ->where('binding.'.$column, $subjectId)
            ->whereNotNull('binding.current_slot')
            ->where('recipe.status', 'published')
            ->select('recipe.*', 'binding.multiplier as binding_multiplier')
            ->first();
        if ($recipe === null) {
            return ['recipe_id' => null, 'snapshot' => null, 'hash' => null];
        }

        $lines = DB::table('fnb_recipe_lines as line')
            ->join('fnb_ingredients as ingredient', function ($join): void {
                $join->on('ingredient.website_key', '=', 'line.website_key')->on('ingredient.id', '=', 'line.ingredient_id');
            })
            ->where('line.website_key', $context->websiteKey)
            ->where('line.recipe_id', $recipe->id)
            ->orderBy('line.sort_order')
            ->get([
                'line.ingredient_id', 'ingredient.code as ingredient_code', 'ingredient.name as ingredient_name',
                'line.quantity', 'line.unit', 'line.base_quantity', 'ingredient.base_unit', 'line.loss_rate',
            ])->map(fn (object $line): array => (array) $line)->all();
        $snapshot = [
            'recipe_id' => (int) $recipe->id,
            'code' => $recipe->code,
            'recipe_version' => (int) $recipe->recipe_version,
            'yield_quantity' => (string) $recipe->yield_quantity,
            'yield_unit' => $recipe->yield_unit,
            'binding_multiplier' => (string) $recipe->binding_multiplier,
            'lines' => $lines,
        ];
        $encoded = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return ['recipe_id' => (int) $recipe->id, 'snapshot' => $snapshot, 'hash' => hash('sha256', $encoded)];
    }

    private function roundProductRatio(int $value, int $multiplier, int $denominator): int
    {
        $result = bcdiv(
            bcadd(bcmul((string) $value, (string) $multiplier, 0), (string) intdiv($denominator, 2), 0),
            (string) $denominator,
            0,
        );
        if (bccomp($result, (string) PHP_INT_MAX, 0) > 0) {
            throw new FnbValidationException('Tax calculation overflow.');
        }

        return (int) $result;
    }
}
