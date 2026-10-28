<?php

namespace Modules\Pharmacy\Services\Administration;

use App\Models\Admin;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\PharmacyData as D;
use Modules\Pharmacy\Services\Setup\PharmacySetupService;

final class PharmacyAdministrationService
{
    public function dispatch(Admin $actor, string $action, array $input, string $key): array
    {
        $permission = match ($action) {
            'import.preview', 'import.commit' => 'pharmacy.product.manage', 'price.publish' => 'pharmacy.price.manage', default => 'pharmacy.view'
        };

        return app(PharmacyCommandService::class)->run($actor, $action, $input, $key,
            fn ($live) => ['pharmacy' => app(PharmacyAccessService::class)->pharmacy($live, $input['pharmacy_id'] ?? '', $permission, true), 'permission' => $permission],
            function ($live, $scope) use ($action, $input, $key) {
                $p = $scope['pharmacy'];

                return match ($action) {
                    'import.preview' => $this->preview($live, $p, $input), 'import.commit' => $this->commit($live, $p, $input),
                    'price.publish' => $this->publish($live, $p, $input, $key), 'filter.save' => $this->filter($live, $p, $input),
                    default => abort(422),
                };
            });
    }

    private function preview(Admin $actor, object $p, array $input): array
    {
        app(PharmacyAccessService::class)->chain($actor, (int) $p->chain_id, 'pharmacy.product.manage', true);
        validator($input, ['rows' => 'required|array|min:1|max:100', 'source_reference' => 'required|string|max:200'])->validate();
        $rows = [];
        $errors = [];
        $codes = [];
        foreach ($input['rows'] as $index => $row) {
            $validator = validator(is_array($row) ? $row : [], ['code' => 'required|string|max:60', 'name' => 'required|string|max:255', 'base_unit' => 'required|string|max:30', 'price_minor' => 'required|regex:/^\d{1,14}$/', 'registration_number' => 'nullable|string|max:255', 'ingredients' => 'nullable', 'strength' => 'nullable|string|max:255', 'dosage_form' => 'nullable|string|max:255', 'requires_prescription' => 'sometimes|boolean', 'controlled' => 'sometimes|boolean', 'cold_chain' => 'sometimes|boolean', 'minimum_increment' => 'sometimes|regex:/^\d{1,12}(\.\d{1,6})?$/']);
            $rowErrors = $validator->errors()->toArray();
            if (isset($row['code'])) {
                $code = mb_strtolower(trim((string) $row['code']));
                if (isset($codes[$code]) || DB::table('ph_products')->where('chain_id', $p->chain_id)->where('code', $row['code'])->exists()) {
                    $rowErrors['code'][] = 'Mã trùng; import không tự ghi đè hoặc gộp thuốc.';
                }
                $codes[$code] = true;
            }
            if ($rowErrors) {
                $errors[] = ['row' => $index + 1, 'fields' => $rowErrors];
            } else {
                $rows[] = $validator->validated();
            }
        }
        $bytes = D::encode(['source_reference' => $input['source_reference'], 'rows' => $rows]);
        $batch = D::insert('ph_import_batches', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'actor_id' => $actor->id, 'kind' => 'products', 'encrypted_rows' => Crypt::encryptString($bytes), 'checksum' => hash('sha256', $bytes), 'errors' => D::encode($errors), 'row_count' => count($input['rows']), 'expires_at' => now()->addDay(), 'status' => 'preview']);

        return D::resource($batch) + ['valid_rows' => count($rows), 'can_commit' => $errors === []];
    }

    private function commit(Admin $actor, object $p, array $input): array
    {
        app(PharmacyAccessService::class)->chain($actor, (int) $p->chain_id, 'pharmacy.product.manage', true);
        app(PharmacyAccessService::class)->pharmacy($actor, (int) $p->id, 'pharmacy.price.manage', true);
        $batch = D::row('ph_import_batches', (string) ($input['import_id'] ?? ''), $p->chain_id, $p->id, true);
        abort_unless((int) $batch->actor_id === (int) $actor->id, 403);
        abort_unless($batch->status === 'preview' && now()->lt($batch->expires_at) && D::json($batch->errors) === [], 409, 'Bản xem trước hết hạn, có xung đột hoặc đã áp dụng.');
        abort_unless((int) ($input['expected_version'] ?? 0) === (int) $batch->version, 409);
        $bytes = Crypt::decryptString($batch->encrypted_rows);
        abort_unless(hash_equals($batch->checksum, hash('sha256', $bytes)), 409);
        $data = D::json($bytes);
        $count = 0;
        foreach ($data['rows'] as $index => $row) {
            abort_if(DB::table('ph_products')->where('chain_id', $p->chain_id)->where('code', $row['code'])->exists(), 409, 'Danh mục đã đổi sau preview; tạo lại bản xem trước.');
            $product = app(PharmacySetupService::class)->dispatch($actor, 'product.save', $row + ['pharmacy_id' => $p->public_id], 'import:'.$batch->public_id.':'.$index);
            DB::table('ph_products')->where('public_id', $product['id'])->update(['metadata' => D::encode(['source' => 'import', 'source_reference' => $data['source_reference'], 'import_id' => $batch->public_id, 'classification_review_required' => true])]);
            $count++;
        }

        return D::resource(D::update('ph_import_batches', $batch, ['status' => 'committed', 'committed_at' => now()], $input['expected_version'])) + ['created_count' => $count];
    }

    private function publish(Admin $actor, object $p, array $input, string $key): array
    {
        app(PharmacyAccessService::class)->chain($actor, (int) $p->chain_id, 'pharmacy.price.manage', true);
        validator($input, ['product_id' => 'required|uuid', 'price_minor' => 'required', 'targets' => 'required|array|min:1|max:100', 'targets.*.pharmacy_id' => 'required|uuid|distinct', 'targets.*.expected_version' => 'nullable|integer|min:1'])->validate();
        $product = D::row('ph_products', $input['product_id'], $p->chain_id, lock: true);
        $price = D::money($input['price_minor']);
        $acks = [];
        $targets = $input['targets'];
        usort($targets, fn ($a, $b) => strcmp($a['pharmacy_id'], $b['pharmacy_id']));
        foreach ($targets as $target) {
            $site = app(PharmacyAccessService::class)->pharmacy($actor, $target['pharmacy_id'], 'pharmacy.price.manage', true);
            abort_unless((int) $site->chain_id === (int) $p->chain_id, 403);
            $result = app(PharmacySetupService::class)->dispatch($actor, 'price.save', ['pharmacy_id' => $site->public_id, 'product_id' => $product->public_id, 'price_minor' => $price, 'expected_version' => $target['expected_version'] ?? null], 'price:'.hash('sha256', $key.'|'.$site->public_id));
            $acks[] = ['pharmacy_id' => $site->public_id, 'price_id' => $result['id'], 'version' => $result['version'], 'applied_at' => now()->toIso8601String()];
        }

        return D::resource(D::insert('ph_price_publications', ['chain_id' => $p->chain_id, 'product_id' => $product->id, 'price_minor' => $price, 'effective_at' => now(), 'targets' => D::encode($targets), 'acknowledgements' => D::encode($acks), 'actor_id' => $actor->id, 'status' => 'applied']));
    }

    private function filter(Admin $actor, object $p, array $input): array
    {
        $v = validator($input, ['name' => 'required|string|max:100', 'resource' => 'required|in:products,inventory,sales,receipts,transfers,stocktakes', 'filters' => 'required|array', 'filters.status' => 'nullable|string|max:40', 'filters.from' => 'nullable|date', 'filters.to' => 'nullable|date', 'filters.per_page' => 'nullable|integer|between:1,100', 'expected_version' => 'nullable|integer|min:1'])->validate();
        $filters = array_intersect_key($v['filters'], array_flip(['status', 'from', 'to', 'per_page']));
        $existing = DB::table('ph_saved_filters')->where('actor_id', $actor->id)->where('pharmacy_id', $p->id)->where('resource', $v['resource'])->where('name', $v['name'])->lockForUpdate()->first();
        $values = ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'actor_id' => $actor->id, 'name' => $v['name'], 'resource' => $v['resource'], 'filters' => D::encode($filters)];

        return D::resource($existing ? D::update('ph_saved_filters', $existing, $values, $v['expected_version'] ?? null) : D::insert('ph_saved_filters', $values));
    }
}
