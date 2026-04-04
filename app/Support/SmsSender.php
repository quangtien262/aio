<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsSender
{
    public function send(string $phone, string $message): bool
    {
        $driver = (string) config('services.sms.driver', 'log');

        if ($driver === 'log') {
            Log::channel((string) config('services.sms.channel', 'sms'))->info('Outgoing order SMS', [
                'to' => $phone,
                'message' => $message,
            ]);

            return true;
        }

        if ($driver === 'http') {
            $endpoint = (string) config('services.sms.endpoint');

            if ($endpoint === '') {
                return false;
            }

            $response = Http::timeout(10)->acceptJson()->post($endpoint, [
                'to' => $phone,
                'message' => $message,
                'from' => config('services.sms.from'),
                'api_key' => config('services.sms.api_key'),
            ]);

            return $response->successful();
        }

        return false;
    }
}
