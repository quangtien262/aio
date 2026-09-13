<?php

namespace Modules\FnbPos\Services\Security;

use Illuminate\Support\Str;

final class FnbSessionBinding
{
    private const SESSION_KEY = '_fnb_security_session_nonce';

    public function hash(string $reportedSessionId): string
    {
        $identity = $reportedSessionId;

        if (app()->bound('request')) {
            $request = request();
            if ($request->hasSession()
                && hash_equals((string) $request->session()->getId(), $reportedSessionId)) {
                $nonce = $request->session()->get(self::SESSION_KEY);
                if (! is_string($nonce) || $nonce === '') {
                    $nonce = (string) Str::uuid();
                    $request->session()->put(self::SESSION_KEY, $nonce);
                }
                $identity = 'nonce:'.$nonce;
            }
        }

        return hash_hmac('sha256', $identity, (string) config('app.key'));
    }
}
