<?php

namespace App\Http\Controllers\Admin;

use App\Support\SiteContext;
use Illuminate\Contracts\View\View;

class AdminShellController
{
    public function __invoke(SiteContext $siteContext): View
    {
        $siteProfile = $siteContext->profile();
        $siteName = trim((string) (
            $siteProfile?->site_name
            ?: data_get($siteProfile?->branding ?? [], 'company_name')
        ));

        return view('admin', [
            'adminDocumentTitle' => ($siteName !== '' ? $siteName : 'AIO Website').' Admin',
        ]);
    }
}
