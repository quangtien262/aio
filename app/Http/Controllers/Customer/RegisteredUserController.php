<?php

namespace App\Http\Controllers\Customer;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class RegisteredUserController
{
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
        return view('auth.customer-register');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'redirect_to' => ['nullable', 'string', 'max:5000'],
        ]);

        $customer = Customer::query()->create($payload);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        $redirectTo = $this->normalizeRedirectTarget(
            $payload['redirect_to'] ?? null,
            route('customer.account'),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Đăng ký tài khoản thành công.',
                'data' => [
                    'redirect_to' => $redirectTo,
                ],
            ]);
        }

        /** @var Redirector $redirector */
        $redirector = app('redirect');

        return $redirector->to($redirectTo);
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
}
