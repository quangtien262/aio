<?php

namespace Modules\Merchandise\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Merchandise\Services\MerchandiseService;

class MerchandiseController
{
    public function __construct(private readonly MerchandiseService $service) {}

    public function catalogs(): JsonResponse
    {
        return response()->json(['data' => DB::table('merch_catalogs')->orderBy('name')->limit(500)->get()]);
    }

    public function createCatalog(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->nativeMutation($request->user('admin'), 'merchandise.manage', fn (): array => $this->service->createCatalog($request->all(), $request->user('admin')->id))], 201);
    }

    public function skus(Request $request, string $catalog): JsonResponse
    {
        $id = $this->catalogId($catalog);

        return response()->json(['data' => $this->service->lookup($id, mb_substr((string) $request->query('query', ''), 0, 128))]);
    }

    public function saveSku(Request $request, string $catalog, ?string $sku = null): JsonResponse
    {
        $result = $this->service->nativeMutation($request->user('admin'), 'merchandise.manage', function () use ($catalog, $sku, $request): array {
            $id = $this->catalogId($catalog);
            $skuId = $sku === null ? null : DB::table('merch_skus')->where('catalog_id', $id)->where('public_id', $sku)->value('id');
            abort_if($sku !== null && $skuId === null, 404);

            return $this->service->saveSku($id, $request->all(), $skuId);
        });

        return response()->json(['data' => $result], $sku === null ? 201 : 200);
    }

    public function import(Request $request, string $catalog): JsonResponse
    {
        $v = $request->validate(['rows' => 'required|array|max:1000', 'commit' => 'sometimes|boolean']);
        $id = $this->catalogId($catalog);

        return response()->json(['data' => $this->service->nativeMutation($request->user('admin'), 'merchandise.import', fn (): array => ($v['commit'] ?? false) ? $this->service->import($id, $v['rows']) : $this->service->previewImport($id, $v['rows']))]);
    }

    private function catalogId(string $public): int
    {
        $id = DB::table('merch_catalogs')->where('public_id', $public)->value('id');
        abort_unless($id, 404);

        return $id;
    }
}
