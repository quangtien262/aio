<?php

namespace Modules\FnbPos\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\FnbPos\Services\FnbCommandRunner;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

final class FnbMenuImageController
{
    public function __invoke(Request $request, FnbApiController $api, FnbOutletAccessService $access, FnbCommandSecurity $security, FnbCommandRunner $commands): JsonResponse
    {
        $ctx = $api->context($request, 'fnb.menu.manage', false, $security->authorization($request, 'item.image.upload', []));
        $access->authorizeHq($request->user('admin'), $ctx->websiteKey, 'fnb.menu.master.manage');
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
        ]);
        $key = (string) $request->header('Idempotency-Key');
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120', 'regex:/^[A-Za-z0-9_.:\\-]+$/']])->validate();
        $file = $data['file'];
        $hash = hash_file('sha256', $file->getRealPath());
        $storedPath = null;
        try {
            $result = $commands->run($ctx, 'item.image.upload', ['sha256' => $hash], $key, function () use ($ctx, $file, $hash, $key, &$storedPath): array {
                $name = hash('sha256', $ctx->outletId.':'.$ctx->actorId.':'.$key.':'.$hash).'.'.$file->extension();
                $storedPath = $file->storeAs('fnb/'.hash('sha256', $ctx->websiteKey).'/menu', $name, 'public');
                if (! $storedPath) throw new \RuntimeException('Không thể lưu ảnh món.');
                $publicPath = parse_url((string) config('filesystems.disks.public.url', '/storage'), PHP_URL_PATH) ?: '/storage';
                return ['resource' => ['url' => url(rtrim($publicPath, '/').'/'.$storedPath), 'name' => basename($storedPath)]];
            });
        } catch (\Throwable $exception) {
            if ($storedPath) Storage::disk('public')->delete($storedPath);
            throw $exception;
        }

        return response()->json($result);
    }
}
