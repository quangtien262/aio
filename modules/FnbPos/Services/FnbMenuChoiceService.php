<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbAuditLogger;

/** Reusable naming templates; assigned variants/options retain their own snapshots and prices. */
final class FnbMenuChoiceService
{
    public static function nameKey(string $name): string
    {
        return hash('sha256', Str::lower(Str::squish($name)));
    }

    public static function import(string $websiteKey, string $kind, string $name, ?string $code = null): array
    {
        $name = Str::squish($name);
        $query = DB::table('fnb_menu_choice_definitions')->where('website_key', $websiteKey)->where('kind', $kind);
        $found = (clone $query)->where('name_key', self::nameKey($name))->first();
        if ($found) return (array) $found;
        $base = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $code ?? '') ?: strtoupper(Str::slug($name, '_')) ?: strtoupper($kind), 0, 40);
        $generated = $base;
        for ($i = 2; (clone $query)->whereRaw('LOWER(code) = ?', [strtolower($generated)])->exists(); $i++) {
            $generated = substr($base, 0, 40 - strlen('-'.$i)).'-'.$i;
        }
        $id = DB::table('fnb_menu_choice_definitions')->insertGetId([
            'website_key' => $websiteKey, 'kind' => $kind, 'code' => $generated, 'name' => $name,
            'name_key' => self::nameKey($name), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) DB::table('fnb_menu_choice_definitions')->where('id', $id)->first();
    }

    public static function seedDefaults(string $websiteKey): void
    {
        self::import($websiteKey, 'size', 'Mặc định', 'DEFAULT');
    }

    public function catalog(string $websiteKey, string $kind): array
    {
        return DB::table('fnb_menu_choice_definitions')->where('website_key', $websiteKey)->where('kind', $kind)
            ->whereNull('archived_at')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
    }

    public function save(FnbContext $ctx, string $kind, ?int $id, array $input, string $key): array
    {
        abort_unless(in_array($kind, ['size', 'topping'], true), 404);
        return app(FnbCommandRunner::class)->run($ctx, $kind.'_definition.save', ['id' => $id, 'input' => $input], $key, function () use ($ctx, $kind, $id, $input): array {
            app(FnbMasterDataLock::class)->acquire($ctx->websiteKey);
            $normalized = $input;
            if (isset($normalized['code']) && is_string($normalized['code'])) $normalized['code'] = trim($normalized['code']) ?: null;
            $data = validator($normalized, ['name' => ['required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/']])->validate();
            $name = Str::squish($data['name']);
            validator(['name' => $name], ['name' => ['required']])->validate();
            $query = DB::table('fnb_menu_choice_definitions')->where('website_key', $ctx->websiteKey)->where('kind', $kind);
            $existing = $id ? (clone $query)->where('id', $id)->whereNull('archived_at')->lockForUpdate()->first() : null;
            abort_if($id && ! $existing, 404);
            if ($existing) $this->assertVersion($existing, $input);
            validator(['name' => $name], ['name' => [function ($attribute, $value, $fail) use ($query, $id): void {
                if ((clone $query)->where('name_key', self::nameKey($value))->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) $fail('Tên này đã có trong danh mục.');
            }]])->validate();
            $code = trim($data['code'] ?? '') ?: $existing?->code;
            if ($code !== null) {
                validator(['code' => $code], ['code' => [function ($attribute, $value, $fail) use ($query, $id): void {
                    if ((clone $query)->whereRaw('LOWER(code) = ?', [strtolower($value)])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) $fail('Mã này đã có trong danh mục.');
                }]])->validate();
            }
            if (! $existing) {
                $result = self::import($ctx->websiteKey, $kind, $name, $code);
            } else {
                (clone $query)->where('id', $id)->update(['name' => $name, 'name_key' => self::nameKey($name), 'code' => $code, 'version' => (int) $existing->version + 1, 'updated_at' => now()]);
                $result = (array) (clone $query)->where('id', $id)->first();
            }
            app(FnbAuditLogger::class)->record('fnb.'.$kind.'_definition.saved', $ctx->websiteKey, Admin::findOrFail($ctx->actorId), 'fnb_menu_choice_definitions', after: ['resource_id' => $result['id'], 'version' => $result['version']], outletIds: [$ctx->outletId]);
            return ['resource' => $result];
        });
    }

    public function archive(FnbContext $ctx, string $kind, int $id, array $input, string $key): array
    {
        abort_unless(in_array($kind, ['size', 'topping'], true), 404);
        return app(FnbCommandRunner::class)->run($ctx, $kind.'_definition.archive', ['id' => $id, 'input' => $input], $key, function () use ($ctx, $kind, $id, $input): array {
            app(FnbMasterDataLock::class)->acquire($ctx->websiteKey);
            $query = DB::table('fnb_menu_choice_definitions')->where('website_key', $ctx->websiteKey)->where('kind', $kind)->where('id', $id)->whereNull('archived_at');
            $existing = (clone $query)->lockForUpdate()->first();
            abort_unless($existing, 404);
            $this->assertVersion($existing, $input);
            $query->update(['archived_at' => now(), 'version' => (int) $existing->version + 1, 'updated_at' => now()]);
            app(FnbAuditLogger::class)->record('fnb.'.$kind.'_definition.archived', $ctx->websiteKey, Admin::findOrFail($ctx->actorId), 'fnb_menu_choice_definitions', after: ['resource_id' => $id], outletIds: [$ctx->outletId]);
            return ['resource' => ['id' => $id, 'archived' => true]];
        });
    }

    private function assertVersion(object $existing, array $input): void
    {
        validator($input, ['expected_version' => ['required', 'integer', 'min:1']])->validate();
        if ((int) $existing->version !== (int) $input['expected_version']) throw new FnbConflictException('Danh mục đã thay đổi; vui lòng tải lại.');
    }
}
