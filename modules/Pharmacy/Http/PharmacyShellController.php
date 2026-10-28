<?php

namespace Modules\Pharmacy\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;

final class PharmacyShellController
{
    public function __invoke(Request $request, PharmacyAccessService $access): View
    {
        if (! $request->user('admin')->must_change_password) {
            $access->workspace($request->user('admin'));
        }

        return view('admin', ['adminDocumentTitle' => 'AIO Nhà thuốc']);
    }
}
