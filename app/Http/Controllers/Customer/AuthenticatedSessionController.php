<?php

namespace App\Http\Controllers\Customer;

use App\Models\Admin;
use App\Models\SiteProfile;
use App\Support\AuditLogger;
use App\Support\Totp;
use Illuminate\Support\Arr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class AuthenticatedSessionController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly Totp $totp,
    )
    {
    }

    private const REDIRECT_QUERY_KEYS_TO_DROP = [
        'loginSegment',
        'registerSegment',
        'accountSegment',
        'favoriteSegment',
        'newsletterSegment',
        'subscribeSegment',
        'previewSegment',
        'pagesSegment',
        'postsSegment',
        'productsSegment',
        'blogSegment',
        'contactSegment',
        'cartSegment',
        'buyNowSegment',
        'cartUpdateSegment',
        'cartRemoveSegment',
        'checkoutSegment',
        'checkoutSuccessSegment',
        'searchSegment',
        'suggestionsSegment',
        'categorySegment',
        'productSegment',
    ];

    public function create(): View
    {
        return view('auth.customer-login', [
            'siteProfile' => SiteProfile::query()->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $payload = $request->validate([
            'login' => ['nullable', 'string', 'max:255', 'required_without:email'],
            'email' => ['nullable', 'string', 'max:255', 'required_without:login'],
            'password' => ['required', 'string'],
            'two_factor_code' => ['nullable', 'string', 'max:32'],
            'redirect_to' => ['nullable', 'string', 'max:5000'],
        ]);

        $remember = $request->boolean('remember');
        $identifier = trim((string) ($payload['login'] ?? $payload['email'] ?? ''));
        $errorField = $request->filled('login') ? 'login' : 'email';
        $customerCredentials = [
            'email' => $identifier,
            'password' => $payload['password'],
        ];

        foreach (['username', 'email'] as $adminIdentifierField) {
            if (! Auth::guard('admin')->attempt([
                $adminIdentifierField => $identifier,
                'password' => $payload['password'],
            ], $remember)) {
                continue;
            }

            /** @var Admin|null $admin */
            $admin = Auth::guard('admin')->user();

            if (! $admin?->isAvailable()) {
                Auth::guard('admin')->logout();

                $this->auditLogger->record('auth.admin.rejected', Admin::class, null, ['identity' => $identifier]);

                return $this->failedResponse(
                    $request,
                    'Thông tin đăng nhập không chính xác hoặc tài khoản không khả dụng.',
                    $errorField,
                );
            }

            if ($admin->two_factor_confirmed_at !== null && ! $this->validTwoFactorChallenge($admin, (string) ($payload['two_factor_code'] ?? ''))) {
                Auth::guard('admin')->logout();
                $this->auditLogger->record('auth.admin.two_factor_failed', $admin, null, null, $admin);

                return $this->failedResponse(
                    $request,
                    'Vui lòng nhập mã xác thực hai lớp hợp lệ.',
                    'two_factor_code',
                );
            }

            $request->session()->regenerate();
            $request->session()->put('admin_auth_version', $admin->auth_version);

            $admin->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            $this->auditLogger->record('auth.admin.login', $admin, null, ['remember' => $remember], $admin);

            return $this->successfulResponse($request, route('admin.index'), 'Đăng nhập admin thành công.', 'admin');
        }

        if (! Auth::guard('customer')->attempt($customerCredentials, $remember)) {
            return $this->failedResponse(
                $request,
                'Thông tin đăng nhập không chính xác hoặc tài khoản không khả dụng.',
                $errorField,
            );
        }

        $request->session()->regenerate();

        $redirectTo = $this->normalizeRedirectTarget(
            $payload['redirect_to'] ?? null,
            route('customer.account'),
        );

        return $this->successfulResponse($request, $redirectTo, 'Đăng nhập thành công.', 'customer');
    }

    protected function successfulResponse(Request $request, string $redirectTo, string $message, string $guard): RedirectResponse|JsonResponse
    {
        /** @var Redirector $redirector */
        $redirector = app('redirect');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => [
                    'guard' => $guard,
                    'redirect_to' => $redirectTo,
                ],
            ]);
        }

        return $redirector->intended($redirectTo);
    }

    protected function failedResponse(Request $request, string $message, string $field): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'errors' => [
                    $field => [$message],
                ],
            ], 422);
        }

        return back()
            ->withErrors([$field => $message])
            ->onlyInput($field);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $locale = $request->session()->get('frontend_locale');

        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        /** @var Redirector $redirector */
        $redirector = app('redirect');

        return $redirector->to('/'.\App\Support\FrontendLocalization::resolveLocale($locale));
    }

    private function normalizeRedirectTarget(?string $redirectTo, string $fallback): string
    {
        $candidate = trim((string) ($redirectTo ?? ''));

        if ($candidate === '') {
            return $fallback;
        }

        $fallbackHost = parse_url($fallback, PHP_URL_HOST);
        $candidateHost = parse_url($candidate, PHP_URL_HOST);

        if ($candidateHost !== null && $fallbackHost !== null && ! hash_equals((string) $fallbackHost, (string) $candidateHost)) {
            return $fallback;
        }

        $path = (string) (parse_url($candidate, PHP_URL_PATH) ?: parse_url($fallback, PHP_URL_PATH) ?: '/');
        $fragment = (string) (parse_url($candidate, PHP_URL_FRAGMENT) ?: '');

        parse_str((string) (parse_url($candidate, PHP_URL_QUERY) ?? ''), $query);

        $query = Arr::except($query, self::REDIRECT_QUERY_KEYS_TO_DROP);
        $queryString = http_build_query($query);
        $normalized = URL::to($path).($queryString !== '' ? '?'.$queryString : '');

        if ($fragment !== '') {
            $normalized .= '#'.$fragment;
        }

        return $normalized;
    }

    private function validTwoFactorChallenge(Admin $admin, string $code): bool
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
