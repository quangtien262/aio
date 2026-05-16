<?php

namespace App\Http\Controllers\Admin\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

class ThemeAvatarController
{
    public function __invoke(Request $request, string $key): JsonResponse
    {
        $user = $request->user();

        // Only allow admin with id = 1 to change avatar
        if (! $user || $user->id !== 1) {
            return response()->json(['message' => 'Không có quyền.'], 403);
        }

        $validated = $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);

        $file = $validated['avatar'];

        $relativeDir = 'theme-previews/'.$key;
        $absoluteDir = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relativeDir));

        if (! File::exists($absoluteDir)) {
            File::makeDirectory($absoluteDir, 0755, true);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $fileName = 'avatar.'.$ext;

        // Remove existing avatar.* files
        foreach (glob($absoluteDir.DIRECTORY_SEPARATOR.'avatar.*') as $existing) {
            @unlink($existing);
        }

        $file->move($absoluteDir, $fileName);

        $url = URL::to($relativeDir.'/'.$fileName);

        return response()->json([
            'message' => 'Đã cập nhật ảnh đại diện theme.',
            'data' => ['avatar_url' => $url],
        ]);
    }
}
