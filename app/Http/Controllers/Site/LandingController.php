<?php

namespace App\Http\Controllers\Site;

use Illuminate\Contracts\View\View;

class LandingController
{
    public function __invoke(): View
    {
        return view('site');
    }
}
