<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;

class AdminShellController
{
    public function __invoke(): View
    {
        return view('admin');
    }
}
