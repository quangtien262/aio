<?php

namespace App\Http\Controllers\Customer;

use Illuminate\Contracts\View\View;

class CustomerAccountController
{
    public function __invoke(): View
    {
        return view('customer.account');
    }
}
