<?php

namespace Modules\Manufacturing\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;

class ManufacturingShellController
{
    public function __invoke(Request $request, ManufacturingAccessService $access): View
    {
        $actor = $request->user('admin');
        // The password screen needs only the authenticated identity. It loads
        // no manufacturing data until the mandatory password change succeeds.
        if (! $actor->must_change_password) {
            $access->workspace($actor);
        }

        return view('admin', ['adminDocumentTitle' => 'AIO Sản xuất Admin']);
    }
}
