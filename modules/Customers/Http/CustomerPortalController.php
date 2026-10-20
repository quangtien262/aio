<?php

namespace Modules\Customers\Http;

use Illuminate\Http\Request;
use Modules\Customers\Services\CustomerAccountLinkService;

class CustomerPortalController
{
    public function __construct(private readonly CustomerAccountLinkService $service) {}

    public function profiles(Request $r)
    {
        return response()->json(['data' => $this->service->profiles($r->user('customer'))])->header('Cache-Control', 'private, no-store');
    }

    public function accept(Request $r)
    {
        $v = $r->validate(['token' => 'required|string|size:64', 'password' => 'required|string|max:255']);

        return response()->json(['data' => $this->service->accept($r->user('customer'), $v['token'], $v['password'])])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $r, string $profile)
    {
        return response()->json(['data' => $this->service->show($r->user('customer'), $profile)])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $r, string $profile)
    {
        return response()->json(['data' => $this->service->update($r->user('customer'), $profile, $r->all())])->header('Cache-Control', 'private, no-store');
    }

    public function consent(Request $r, string $profile)
    {
        return response()->json(['data' => $this->service->consent($r->user('customer'), $profile, $r->all())])->header('Cache-Control', 'private, no-store');
    }
}
