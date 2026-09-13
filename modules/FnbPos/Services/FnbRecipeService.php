<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbAuditLogger;

class FnbRecipeService
{
    public function __construct(private readonly FnbCommandRunner $commands, private readonly FnbAuditLogger $audit) {}

    /** Publishing always creates a new immutable recipe revision. */
    public function publish(FnbContext $ctx, array $input, string $key): array
    {
        $data = validator($input, [
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'variant_id' => ['required', 'integer', 'min:1'],
            'expected_recipe_version' => ['required', 'integer', 'min:0'],
            'yield_quantity' => ['required', 'numeric', 'gt:0', 'max:100000', 'regex:/^\d+(\.\d{1,6})?$/'],
            'yield_unit' => ['required', 'string', 'max:30'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.ingredient_id' => ['required', 'integer', 'min:1', 'distinct'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\d+(\.\d{1,6})?$/'],
            'lines.*.unit' => ['required', Rule::in(['g', 'kg', 'ml', 'l', 'piece'])],
            'lines.*.loss_rate' => ['sometimes', 'numeric', 'min:0', 'max:0.9999', 'regex:/^\d+(\.\d{1,4})?$/'],
        ])->validate();

        return $this->commands->run($ctx, 'recipe.publish', $data, $key, function () use ($ctx, $data): array {
            $variant = DB::table('fnb_item_variants')->where('website_key', $ctx->websiteKey)->where('id', $data['variant_id'])->lockForUpdate()->first();
            abort_unless($variant, 404);
            $recipes = DB::table('fnb_recipes')->where('website_key', $ctx->websiteKey)->where('code', $data['code']);
            $previous = (clone $recipes)->orderByDesc('recipe_version')->lockForUpdate()->first();
            if ((int) ($previous->recipe_version ?? 0) !== (int) $data['expected_recipe_version']) {
                throw new FnbConflictException('Công thức đã có phiên bản mới.', ['current_versions' => ['recipe:'.$data['code'] => (int) ($previous->recipe_version ?? 0)]]);
            }
            $lines = [];
            foreach ($data['lines'] as $index => $line) {
                $ingredient = DB::table('fnb_ingredients')->where('website_key', $ctx->websiteKey)->where('id', $line['ingredient_id'])->where('status', 'active')->first();
                abort_unless($ingredient, 404);
                $factor = $this->conversion((string) $line['unit'], $ingredient->base_unit);
                $lines[] = [
                    'website_key' => $ctx->websiteKey, 'ingredient_id' => $ingredient->id,
                    'quantity' => (string) $line['quantity'], 'unit' => $line['unit'],
                    'base_quantity' => bcmul((string) $line['quantity'], $factor, 6),
                    'loss_rate' => (string) ($line['loss_rate'] ?? '0'), 'sort_order' => $index,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            (clone $recipes)->whereNotNull('current_slot')->update(['current_slot' => null]);
            $id = DB::table('fnb_recipes')->insertGetId([
                'website_key' => $ctx->websiteKey, 'code' => $data['code'], 'name' => $data['name'],
                'recipe_version' => (int) ($previous->recipe_version ?? 0) + 1,
                'yield_quantity' => $data['yield_quantity'], 'yield_unit' => $data['yield_unit'],
                'status' => 'published', 'current_slot' => 'current', 'published_by' => $ctx->actorId,
                'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('fnb_recipe_lines')->insert(array_map(fn ($line) => $line + ['recipe_id' => $id], $lines));
            DB::table('fnb_variant_recipes')->where('website_key', $ctx->websiteKey)->where('variant_id', $variant->id)->whereNotNull('current_slot')->update(['current_slot' => null]);
            DB::table('fnb_variant_recipes')->insert([
                'website_key' => $ctx->websiteKey, 'variant_id' => $variant->id, 'recipe_id' => $id,
                'multiplier' => '1.000000', 'current_slot' => 'current', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record('fnb.recipe.published', $ctx->websiteKey, Admin::findOrFail($ctx->actorId), 'fnb_recipes',
                after: ['recipe_id' => $id, 'variant_id' => $variant->id], outletIds: [$ctx->outletId]);

            return ['resource' => (array) DB::table('fnb_recipes')->where('id', $id)->first() + ['lines' => $lines]];
        });
    }

    private function conversion(string $from, string $to): string
    {
        if ($from === $to) {
            return '1';
        }

        return match ($from.':'.$to) {
            'kg:g', 'l:ml' => '1000',
            'g:kg', 'ml:l' => '0.001',
            default => throw new FnbConflictException('Không thể đổi đơn vị giữa khối lượng, thể tích và số lượng.'),
        };
    }
}
