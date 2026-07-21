<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Admin;
use App\Support\AuditLogger;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminTwoFactorController
{
    public function __construct(
        private readonly Totp $totp,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function setup(Request $request): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:admin']]);
        /** @var Admin $admin */
        $admin = $request->user('admin');
        abort_if($admin->two_factor_confirmed_at !== null, 422, 'Xác thực hai lớp đã được bật. Hãy tắt trước khi thiết lập lại.');
        $secret = $this->totp->generateSecret();

        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $issuer = config('app.name', 'AIO');

        return response()->json(['data' => [
            'secret' => $secret,
            'provisioning_uri' => $this->totp->provisioningUri($secret, $admin->email ?: $admin->username, $issuer),
        ]]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'size:6']]);
        /** @var Admin $admin */
        $admin = $request->user('admin');

        if (blank($admin->two_factor_secret) || ! $this->totp->verify($admin->two_factor_secret, $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'Mã xác thực không chính xác.']);
        }

        $recoveryCodes = $this->totp->recoveryCodes();
        $admin->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $recoveryCodes),
            'two_factor_confirmed_at' => now(),
            'auth_version' => $admin->auth_version + 1,
        ])->save();
        $request->session()->put('admin_auth_version', $admin->auth_version);

        $this->auditLogger->record('auth.two_factor.enabled', $admin, null, ['enabled' => true], $admin);

        return response()->json(['message' => 'Đã bật xác thực hai lớp.', 'data' => ['recovery_codes' => $recoveryCodes]]);
    }

    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'code' => ['required', 'string'],
        ]);
        /** @var Admin $admin */
        $admin = $request->user('admin');

        if (! $this->validChallenge($admin, $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'Mã xác thực hoặc mã khôi phục không chính xác.']);
        }

        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'auth_version' => $admin->auth_version + 1,
        ])->save();
        $request->session()->put('admin_auth_version', $admin->auth_version);

        $this->auditLogger->record('auth.two_factor.disabled', $admin, ['enabled' => true], ['enabled' => false], $admin);

        return response()->json(['message' => 'Đã tắt xác thực hai lớp.']);
    }

    private function validChallenge(Admin $admin, string $code): bool
    {
        if (filled($admin->two_factor_secret) && $this->totp->verify($admin->two_factor_secret, $code)) {
            return true;
        }

        foreach ($admin->two_factor_recovery_codes ?? [] as $index => $hash) {
            if (Hash::check($code, $hash)) {
                $codes = $admin->two_factor_recovery_codes;
                unset($codes[$index]);
                $admin->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }
}
