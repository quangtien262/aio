<?php

namespace Modules\Manufacturing\Http;

use App\Http\Controllers\Customer\AuthenticatedSessionController;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Core owns password/TOTP/recovery verification and session establishment. */
class ManufacturingLoginController extends AuthenticatedSessionController
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($actor = $request->user('admin')) {
            try {
                app(ManufacturingAccessService::class)->workspace($actor, true);

                return redirect()->route('admin.manufacturing.shell');
            } catch (HttpException $exception) {
                if (! in_array($exception->getStatusCode(), [403, 404], true)) {
                    throw $exception;
                }
            }
        }

        return view()->file(base_path('modules/Manufacturing/resources/views/login.blade.php'));
    }

    protected function successfulResponse(Request $request, string $redirectTo, string $message, string $guard): RedirectResponse|JsonResponse
    {
        $actor = $guard === 'admin' ? $request->user('admin') : null;
        $eligible = false;
        if ($actor) {
            try {
                app(ManufacturingAccessService::class)->workspace($actor, true);
                $eligible = true;
            } catch (HttpException $exception) {
                if (! in_array($exception->getStatusCode(), [403, 404], true)) {
                    throw $exception;
                }
            }
        }
        if (! $eligible) {
            app(AuditLogger::class)->record('auth.manufacturing.rejected', 'manufacturing-login', null, ['guard' => $guard], $actor, 'manufacturing', '', 'manufacturing');
            Auth::guard('admin')->logout();
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->failedResponse($request, 'Tài khoản chưa được cấp quyền truy cập xưởng sản xuất.', 'login');
        }

        $redirectTo = route('admin.manufacturing.shell');
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'data' => ['guard' => 'admin', 'redirect_to' => $redirectTo]]);
        }

        // Do not honor a storefront/external intended URL in this private entry.
        return redirect()->to($redirectTo);
    }
}
