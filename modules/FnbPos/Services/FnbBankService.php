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

    public static function banks(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__).'/resources/banks.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function query(FnbContext $ctx): \Illuminate\Database\Query\Builder
    {
        return DB::table('fnb_bank_accounts')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId);
    }

    private function present(object $row): array
    {
        $number = Crypt::decryptString($row->account_number);
        $bank = collect(self::banks())->firstWhere('code', $row->bank_code);
        return ['id' => (int) $row->id, 'bank_code' => $row->bank_code, 'bank_bin' => $row->bank_bin,
            'bank_name' => $bank['name'] ?? $row->bank_code,
            'account_number_masked' => str_repeat('*', max(0, strlen($number) - 4)).substr($number, -4),
            'account_name' => Crypt::decryptString($row->account_name), 'enabled' => (bool) $row->enabled,
            'is_default' => $row->default_slot === 'default', 'version' => (int) $row->version,
            'confirmation_mode' => 'manual', 'provider_connected' => false];
    }

    public function accounts(FnbContext $ctx, bool $enabledOnly = false): array
    {
        return $this->query($ctx)->when($enabledOnly, fn ($q) => $q->where('enabled', true))
            ->orderByDesc('default_slot')->orderBy('id')->get()->map(fn ($row) => $this->present($row))->all();
    }

    public function account(FnbContext $ctx): ?array
    {
        return $this->accounts($ctx)[0] ?? null;
    }

    public function saveAccount(FnbContext $ctx, array $input, string $key, ?int $id = null, bool $create = false): array
    {
        if (isset($input['account_number']) && is_string($input['account_number'])) $input['account_number'] = trim($input['account_number']) ?: null;
        $data = validator($input, ['bank_code' => ['sometimes', 'string', \Illuminate\Validation\Rule::in(array_column(self::banks(), 'code'))],
            'account_number' => ['nullable', 'string', 'regex:/^[0-9]{6,30}$/'], 'account_name' => ['required', 'string', 'max:80'],
            'enabled' => ['required', 'boolean'], 'is_default' => ['sometimes', 'boolean'], 'expected_version' => ['nullable', 'integer', 'min:1']])->validate();
        if (!empty($data['is_default']) && !$data['enabled']) abort(422, 'Bật tài khoản trước khi đặt làm mặc định.');
        $data['account_name'] = Str::squish($data['account_name']);
        validator($data, ['account_name' => ['required']])->validate();
        return $this->commands->run($ctx, 'bank.account.save', $data + ['id' => $id, 'create' => $create], $key, function () use ($ctx, $data, $id, $create): array {
            DB::table('fnb_outlets')->where('website_key', $ctx->websiteKey)->where('id', $ctx->outletId)->lockForUpdate()->firstOrFail();
            $query = $this->query($ctx);
            $row = $create ? null : (clone $query)->when($id, fn ($q) => $q->where('id', $id))->orderByDesc('default_slot')->orderBy('id')->lockForUpdate()->first();
            abort_if($id && !$row, 404);
            if ($row && (int) ($data['expected_version'] ?? 0) !== (int) $row->version) throw new FnbConflictException('Cấu hình tài khoản đã thay đổi.');
            if (!$row && empty($data['account_number'])) abort(422, 'Nhập số tài khoản ngân hàng.');
            $bankCode = $data['bank_code'] ?? $row?->bank_code ?? 'TPB';
            $bank = collect(self::banks())->firstWhere('code', $bankCode);
            if ($row && $bankCode !== $row->bank_code && empty($data['account_number'])) abort(422, 'Nhập lại số tài khoản khi đổi ngân hàng.');
            $number = $data['account_number'] ?? ($row ? Crypt::decryptString($row->account_number) : '');
            foreach ((clone $query)->where('bank_code', $bankCode)->when($row, fn ($q) => $q->where('id', '!=', $row->id))->get() as $other) {
                if (hash_equals(Crypt::decryptString($other->account_number), $number)) abort(422, 'Tài khoản này đã được cấu hình.');
            }
            $wantDefault = (bool) $data['enabled'] && ($data['is_default'] ?? ($row?->default_slot === 'default'));
            if ($wantDefault) (clone $query)->where('default_slot', 'default')->when($row, fn ($q) => $q->where('id', '!=', $row->id))->update(['default_slot' => null, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            $values = ['bank_code' => $bankCode, 'bank_bin' => $bank['bin'], 'account_number' => Crypt::encryptString($number),
                'account_name' => Crypt::encryptString($data['account_name']), 'enabled' => $data['enabled'], 'default_slot' => $wantDefault ? 'default' : null,
                'version' => ($row->version ?? 0) + 1, 'updated_at' => now()];
            if ($row) { $savedId = $row->id; (clone $query)->where('id', $row->id)->update($values); }
            else $savedId = DB::table('fnb_bank_accounts')->insertGetId($values + ['website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId, 'created_at' => now()]);
            if (!(clone $query)->where('enabled', true)->where('default_slot', 'default')->exists()) {
                $fallback = (clone $query)->where('enabled', true)->orderBy('id')->first();
                if ($fallback) (clone $query)->where('id', $fallback->id)->update(['default_slot' => 'default', 'version' => DB::raw('version + '.((int) $fallback->id === (int) $savedId ? '0' : '1')), 'updated_at' => now()]);
            }
            return ['resource' => $this->present((clone $query)->where('id', $savedId)->firstOrFail())];
        });
    }

    public function intent(FnbContext $ctx, int $checkId, array $input, string $key): array
    {
        $data = validator($input, ['amount_minor' => ['required', 'integer', 'min:1', 'max:9000000000000'], 'account_id' => ['nullable', 'integer', 'min:1']])->validate();

        return $this->commands->run($ctx, 'bank.intent.create', $data + ['check_id' => $checkId], $key, function () use ($ctx, $checkId, $data, $key): array {
            $check = DB::table('fnb_checks')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId)->where('id', $checkId)->lockForUpdate()->first();
            abort_unless($check, 404);
            if ($check->currency !== 'VND' || $check->status !== 'finalized' || ! in_array($check->settlement_mode, [null, 'mixed', 'single_non_cash'], true)) {
                throw new FnbConflictException('QR chuyển khoản yêu cầu bill VND chưa thanh toán và phương án không chỉ tiền mặt.');
            }
            $paid = (int) DB::table('fnb_payments')->where('check_id', $checkId)->whereIn('status', ['reserved', 'processing', 'uncertain', 'reconciling', 'succeeded'])->sum('amount_minor');
            if ((int) $data['amount_minor'] > (int) ($check->settlement_total_minor ?? $check->grand_total_minor) - $paid) {
                throw new FnbConflictException('Số tiền QR vượt số tiền còn phải thu.');
            }
            $account = $this->query($ctx)->where('enabled', true)->when(!empty($data['account_id']), fn ($q) => $q->where('id', $data['account_id']))->orderByDesc('default_slot')->orderBy('id')->sharedLock()->first();
            abort_unless($account, 422, 'Chưa có tài khoản ngân hàng đang bật cho điểm bán này.');
            $reference = 'FNB'.strtoupper(Str::random(20));
            $number = Crypt::decryptString($account->account_number);
            $qr = $this->vietQr($number, (int) $data['amount_minor'], $reference, $account->bank_bin);
            $expires = now()->addMinutes(15);
            $id = DB::table('fnb_bank_payment_intents')->insertGetId(['website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId,
                'check_id' => $checkId, 'account_id' => $account->id, 'reference' => $reference, 'idempotency_key' => $key,
                'fingerprint' => $this->commands->fingerprint($data), 'amount_minor' => $data['amount_minor'], 'currency' => 'VND',
                'status' => 'pending', 'qr_payload' => $qr, 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);

            return ['resource' => ['id' => $id, 'reference' => $reference, 'amount_minor' => (int) $data['amount_minor'], 'currency' => 'VND',
                'account_id' => (int) $account->id, 'account_version' => (int) $account->version, 'check_id' => $checkId, 'account_number' => $number, 'account_name' => Crypt::decryptString($account->account_name), 'bank_code' => $account->bank_code, 'bank_bin' => $account->bank_bin, 'bank_name' => $this->present($account)['bank_name'],
                'qr_payload' => $qr, 'expires_at' => $expires->toIso8601String(), 'status' => 'pending', 'confirmation_mode' => 'manual',
                'notice' => 'Chỉ xác nhận thanh toán sau khi kiểm tra tiền đã vào tài khoản ngân hàng. QR không phải bằng chứng đã nhận tiền.']];
        });
    }

    public function vietQr(string $account, int $amount, string $reference, string $bankBin = '970423'): string
    {
        $tlv = static function (string $tag, string $value): string {
            if (strlen($value) > 99) {
                throw new \InvalidArgumentException('QR field too long.');
            }

            return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
        };
        $merchant = $tlv('00', 'A000000727').$tlv('01', $tlv('00', $bankBin).$tlv('01', $account)).$tlv('02', 'QRIBFTTA');
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
