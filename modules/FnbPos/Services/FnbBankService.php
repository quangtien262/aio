<?php

namespace Modules\FnbPos\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;

final class FnbBankService
{
    public function __construct(private readonly FnbCommandRunner $commands) {}

    public function account(FnbContext $ctx): ?array
    {
        $row = DB::table('fnb_bank_accounts')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId)->first();
        if (! $row) {
            return null;
        }
        $number = Crypt::decryptString($row->account_number);

        return ['id' => $row->id, 'bank_code' => 'TPB', 'account_number_masked' => str_repeat('*', max(0, strlen($number) - 4)).substr($number, -4),
            'account_name' => Crypt::decryptString($row->account_name), 'enabled' => (bool) $row->enabled, 'version' => (int) $row->version,
            'confirmation_mode' => 'manual', 'provider_connected' => false];
    }

    public function saveAccount(FnbContext $ctx, array $input, string $key): array
    {
        $data = validator($input, ['account_number' => ['nullable', 'regex:/^[0-9]{6,30}$/'], 'account_name' => ['required', 'string', 'max:80'],
            'enabled' => ['required', 'boolean'], 'expected_version' => ['nullable', 'integer', 'min:1']])->validate();

        return $this->commands->run($ctx, 'bank.account.save', $data, $key, function () use ($ctx, $data): array {
            $query = DB::table('fnb_bank_accounts')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId);
            $row = (clone $query)->lockForUpdate()->first();
            if ($row && (int) ($data['expected_version'] ?? 0) !== (int) $row->version) {
                throw new FnbConflictException('Cấu hình tài khoản đã thay đổi.');
            }
            if (! $row && empty($data['account_number'])) {
                abort(422, 'Nhập số tài khoản TPBank.');
            }
            $values = ['bank_code' => 'TPB', 'account_number' => empty($data['account_number']) ? $row->account_number : Crypt::encryptString($data['account_number']),
                'account_name' => Crypt::encryptString($data['account_name']), 'enabled' => $data['enabled'], 'version' => ($row->version ?? 0) + 1, 'updated_at' => now()];
            if ($row) {
                $query->update($values);
            } else {
                $query->insert($values + ['website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId, 'created_at' => now()]);
            }

            return ['resource' => $this->account($ctx)];
        });
    }

    public function intent(FnbContext $ctx, int $checkId, array $input, string $key): array
    {
        $data = validator($input, ['amount_minor' => ['required', 'integer', 'min:1', 'max:9000000000000']])->validate();

        return $this->commands->run($ctx, 'bank.intent.create', $data + ['check_id' => $checkId], $key, function () use ($ctx, $checkId, $data, $key): array {
            $check = DB::table('fnb_checks')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId)->where('id', $checkId)->lockForUpdate()->first();
            abort_unless($check, 404);
            if ($check->currency !== 'VND' || $check->status !== 'finalized' || ! in_array($check->settlement_mode, ['mixed', 'single_non_cash'], true)) {
                throw new FnbConflictException('QR chuyển khoản yêu cầu bill VND đã chốt phương án thanh toán không chỉ tiền mặt.');
            }
            $paid = (int) DB::table('fnb_payments')->where('check_id', $checkId)->whereIn('status', ['reserved', 'processing', 'uncertain', 'reconciling', 'succeeded'])->sum('amount_minor');
            if ((int) $data['amount_minor'] > (int) $check->settlement_total_minor - $paid) {
                throw new FnbConflictException('Số tiền QR vượt số tiền còn phải thu.');
            }
            $account = DB::table('fnb_bank_accounts')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId)->where('enabled', true)->sharedLock()->first();
            abort_unless($account, 422, 'Chưa cấu hình tài khoản TPBank cho điểm bán.');
            $reference = 'FNB'.strtoupper(Str::random(20));
            $number = Crypt::decryptString($account->account_number);
            $qr = $this->vietQr($number, (int) $data['amount_minor'], $reference);
            $expires = now()->addMinutes(15);
            $id = DB::table('fnb_bank_payment_intents')->insertGetId(['website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId,
                'check_id' => $checkId, 'account_id' => $account->id, 'reference' => $reference, 'idempotency_key' => $key,
                'fingerprint' => $this->commands->fingerprint($data), 'amount_minor' => $data['amount_minor'], 'currency' => 'VND',
                'status' => 'pending', 'qr_payload' => $qr, 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);

            return ['resource' => ['id' => $id, 'reference' => $reference, 'amount_minor' => (int) $data['amount_minor'], 'currency' => 'VND',
                'account_number' => $number, 'account_name' => Crypt::decryptString($account->account_name), 'bank_code' => 'TPB',
                'qr_payload' => $qr, 'expires_at' => $expires->toIso8601String(), 'status' => 'pending', 'confirmation_mode' => 'manual',
                'notice' => 'Chỉ xác nhận thanh toán sau khi kiểm tra tiền đã vào tài khoản TPBank. QR không phải bằng chứng đã nhận tiền.']];
        });
    }

    public function vietQr(string $account, int $amount, string $reference): string
    {
        $tlv = static function (string $tag, string $value): string {
            if (strlen($value) > 99) {
                throw new \InvalidArgumentException('QR field too long.');
            }

            return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
        };
        $merchant = $tlv('00', 'A000000727').$tlv('01', $tlv('00', '970423').$tlv('01', $account)).$tlv('02', 'QRIBFTTA');
        $payload = $tlv('00', '01').$tlv('01', '12').$tlv('38', $merchant).$tlv('53', '704').$tlv('54', (string) $amount)
            .$tlv('58', 'VN').$tlv('62', $tlv('08', $reference)).'6304';
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($payload); $i++) {
            $crc ^= ord($payload[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return $payload.strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
