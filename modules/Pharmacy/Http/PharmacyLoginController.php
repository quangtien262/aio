<?php

namespace Modules\Pharmacy\Http;

use App\Http\Controllers\Customer\AuthenticatedSessionController;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Core owns password/TOTP/recovery verification and session establishment. */
class PharmacyLoginController extends AuthenticatedSessionController
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($actor = $request->user('admin')) {
            try {
                app(PharmacyAccessService::class)->workspace($actor, true);

                return redirect()->route('admin.pharmacy.shell');
            } catch (HttpException $exception) {
                if (! in_array($exception->getStatusCode(), [403, 404], true)) {
                    throw $exception;
                }
            }
        }

        return view()->file(base_path('modules/Pharmacy/resources/views/login.blade.php'));
    }

    protected function successfulResponse(Request $request, string $redirectTo, string $message, string $guard): RedirectResponse|JsonResponse
    {
        $actor = $guard === 'admin' ? $request->user('admin') : null;
        $eligible = false;
        if ($actor) {
            try {
                app(PharmacyAccessService::class)->workspace($actor, true);
                $eligible = true;
            } catch (HttpException $exception) {
                if (! in_array($exception->getStatusCode(), [403, 404], true)) {
                    throw $exception;
                }
            }
        }
        if (! $eligible) {
            app(AuditLogger::class)->record('auth.pharmacy.rejected', 'pharmacy-login', null, ['guard' => $guard], $actor, 'pharmacy', '', 'pharmacy');
            Auth::guard('admin')->logout();
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->failedResponse($request, 'Tài khoản chưa được cấp quyền truy cập nhà thuốc.', 'login');
        }

        $redirectTo = route('admin.pharmacy.shell');
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'data' => ['guard' => 'admin', 'redirect_to' => $redirectTo]]);
        }

        // Do not honor a storefront/external intended URL in this private entry.
        return redirect()->to($redirectTo);
    }
}
