<?php

namespace Modules\Manufacturing\Services\Documents;

use App\Models\Admin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Modules\Manufacturing\Services\Commands\ManufacturingCommandService;
use Modules\Manufacturing\Services\ManufacturingData as D;

class ManufacturingDocumentService
{
    public function listing(Admin $actor, string $factoryId, array $query): array
    {
        $access = app(ManufacturingAccessService::class);
        $live = $access->actor($actor);
        $factory = $access->factory($live, $factoryId, 'manufacturing.documents');
        $v = validator($query, ['resource_type' => 'required|in:style,order', 'resource_id' => 'required|uuid', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100'])->validate();
        $this->resource($factory, $v['resource_type'], $v['resource_id']);
        $builder = DB::table('mfg_documents')->where('company_id', $factory->company_id)->where('factory_id', $factory->id)->where('resource_type', $v['resource_type'])->where('resource_id', $v['resource_id']);
        $total = (clone $builder)->count();
        $page = $v['page'] ?? 1;
        $perPage = $v['per_page'] ?? 25;
        $items = $builder->latest('id')->offset(($page - 1) * $perPage)->limit($perPage)->get(['public_id', 'original_name', 'mime_type', 'size', 'status', 'checksum', 'version', 'created_at'])
            ->map(fn ($row) => (array) $row + ['id' => $row->public_id])->all();

        return compact('items', 'total', 'page') + ['per_page' => $perPage];
    }

    public function upload(Admin $actor, string $factoryId, string $resourceType, string $resourceId, UploadedFile $file, string $key): array
    {
        validator(['factory_id' => $factoryId, 'resource_type' => $resourceType, 'resource_id' => $resourceId, 'file' => $file], [
            'factory_id' => 'required|uuid', 'resource_type' => 'required|in:style,order', 'resource_id' => 'required|uuid',
            'file' => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,webp,txt,csv,docx,xlsx,zip',
        ])->validate();
        $checksum = hash_file('sha256', $file->getRealPath());
        $originalName = mb_substr((string) preg_replace('/[\x00-\x1F\x7F]/u', '_', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 255);
        $public = (string) Str::uuid();
        $path = 'documents/'.$public.'.'.strtolower($file->getClientOriginalExtension());
        $disk = Storage::disk('manufacturing_private');
        try {
            return app(ManufacturingCommandService::class)->run($actor, 'document.upload', [
                'factory_id' => $factoryId, 'resource_type' => $resourceType, 'resource_id' => $resourceId, 'checksum' => $checksum,
                'original_name' => $originalName, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
            ], $key, function (Admin $live) use ($factoryId, $resourceType, $resourceId): array {
                $factory = app(ManufacturingAccessService::class)->factory($live, $factoryId, 'manufacturing.documents', true);
                $this->resource($factory, $resourceType, $resourceId);

                return ['factory' => $factory, 'permission' => 'manufacturing.documents'];
            }, function (Admin $live, array $scope) use ($resourceType, $resourceId, $file, $checksum, $public, $path, $disk, $originalName): array {
                $stream = fopen($file->getRealPath(), 'rb');
                try {
                    $disk->put($path, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                DB::afterRollBack(fn () => $disk->delete($path));
                $row = D::insert('mfg_documents', ['public_id' => $public, 'company_id' => $scope['factory']->company_id, 'factory_id' => $scope['factory']->id,
                    'resource_type' => $resourceType, 'resource_id' => $resourceId, 'original_name' => $originalName,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'path' => $path, 'checksum' => $checksum, 'size' => $file->getSize(), 'uploaded_by' => $live->id, 'status' => 'ready']);

                return ['id' => $row->public_id, 'public_id' => $row->public_id, 'status' => $row->status, 'version' => $row->version,
                    'original_name' => $row->original_name, 'mime_type' => $row->mime_type, 'size' => $row->size, 'checksum' => $row->checksum];
            });
        } catch (\Throwable $exception) {
            if (! DB::table('mfg_documents')->where('public_id', $public)->exists()) {
                $disk->delete($path);
            }
            throw $exception;
        }
    }

    public function authorized(Admin $actor, string $publicId): object
    {
        $access = app(ManufacturingAccessService::class);
        $actor = $access->actor($actor);
        $document = DB::table('mfg_documents')->where('public_id', $publicId)->where('status', 'ready')->first();
        abort_unless($document, 404);
        $factory = $access->factory($actor, (int) $document->factory_id, 'manufacturing.documents');
        $this->resource($factory, $document->resource_type, $document->resource_id);
        $disk = Storage::disk('manufacturing_private');
        abort_unless($disk->exists($document->path), 404, 'Tệp không còn tồn tại.');
        abort_unless(hash_equals($document->checksum, hash_file('sha256', $disk->path($document->path))), 409, 'Checksum tài liệu không khớp; cần kiểm tra dữ liệu lưu trữ.');

        return $document;
    }

    private function resource(object $factory, string $type, string $publicId): object
    {
        return match ($type) {
            'style' => D::row('mfg_styles', $publicId, $factory->company_id),
            'order' => D::row('mfg_orders', $publicId, $factory->company_id, $factory->id),
            default => abort(422, 'Loại tài liệu không hợp lệ.'),
        };
    }
}
