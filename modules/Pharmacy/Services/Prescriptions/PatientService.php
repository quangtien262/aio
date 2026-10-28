<?php

namespace Modules\Pharmacy\Services\Prescriptions;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Modules\Pharmacy\Services\Dispensing\TransactionSupport;

final class PatientService extends TransactionSupport
{
    public function create(Admin $actor, array $input, string $key): array
    {
        return $this->save($actor, $input, $key);
    }

    public function save(Admin $actor, array $input, string $key): array
    {
        return $this->command($actor, 'patient.save', $input, $key,
            fn (Admin $live) => $this->pharmacy($live, $input, 'pharmacy.patient.manage'),
            function (Admin $live) use ($input): array {
                $p = $this->pharmacy($live, $input, 'pharmacy.patient.manage', true);
                $data = validator($input, [
                    'name' => 'required|string|max:255', 'date_of_birth' => 'nullable|date|before_or_equal:today',
                    'phone' => 'nullable|string|max:50', 'identification' => 'nullable|string|max:100',
                    'address' => 'nullable|string|max:1000', 'notes' => 'nullable|string|max:4000',
                    'allergies' => 'nullable|array|max:100', 'allergies.*' => 'string|max:500',
                    'processing_basis' => 'required|string|in:consent,legal_obligation',
                    'processing_evidence' => 'required|string|max:2000',
                ])->validate();
                $data['phone'] = ! empty($data['phone']) ? $this->normalizePhone($data['phone']) : null;
                $phoneLookup = $data['phone'] ? $this->phoneTokens($p, $data['phone'])[0] : null;
                if (! empty($input['patient_id'])) {
                    $row = $this->scopedRow('ph_patients', $input['patient_id'], $p, true);
                    $this->version($row, $input);
                    $row = $this->change('ph_patients', $row, ['encrypted_profile' => $this->encrypt($data), 'phone_lookup' => $phoneLookup]);
                } else {
                    $row = $this->insert('ph_patients', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'encrypted_profile' => $this->encrypt($data), 'phone_lookup' => $phoneLookup]);
                }
                $this->event($live, $p, 'patient.saved', $row);

                return $this->ref($row);
            });
    }

    public function lookup(Admin $actor, array $input, string $key): array
    {
        validator($input, ['phone' => 'required|string|max:50'])->validate();
        $p = $this->pharmacy($actor, $input, 'pharmacy.patient.view');
        $phone = $this->normalizePhone($input['phone']);
        $tokens = $this->phoneTokens($p, $phone);
        // The command journal receives keyed tokens, never a raw phone or a phone in a URL.
        $payload = ['pharmacy_id' => $p->public_id, 'phone_token' => $tokens[0]];

        return $this->command($actor, 'patient.lookup', $payload, $key,
            fn (Admin $live) => $this->pharmacy($live, $payload, 'pharmacy.patient.view'),
            function (Admin $live) use ($payload, $phone): array {
                $p = $this->pharmacy($live, $payload, 'pharmacy.patient.view', true);
                $rows = DB::table('ph_patients')->where('chain_id', $p->chain_id)->where('pharmacy_id', $p->id)->where('status', 'active')->whereIn('phone_lookup', $this->phoneTokens($p, $phone))->orderBy('id')->limit(101)->get();
                $records = $rows->take(100)->map(function ($row): array {
                    $profile = $this->decrypt($row->encrypted_profile);

                    return $this->ref($row) + ['name' => $profile['name'], 'phone_masked' => '+84••••••'.substr($profile['phone'], -3)];
                })->all();

                return ['resource' => 'patient_lookup', 'status' => $records ? 'found' : 'not_found', 'records' => $records, 'meta' => ['per_page' => 100, 'has_more' => $rows->count() > 100, 'auto_merge' => false]];
            });
    }

    public function show(Admin $actor, string $id): array
    {
        $p = $this->scopeFor($actor, 'ph_patients', $id, 'pharmacy.patient.view');
        $this->access()->pharmacy($actor, $p->public_id, 'pharmacy.patient.sensitive');
        $row = $this->scopedRow('ph_patients', $id, $p);
        $this->readAudit($actor, 'patient', $id);

        return $this->ref($row) + ['pharmacy_id' => $p->public_id, ...$this->decrypt($row->encrypted_profile)];
    }

    public function list(Admin $actor, array $filters): array
    {
        $p = $this->pharmacy($actor, $filters, 'pharmacy.patient.view');
        $this->readAudit($actor, 'patient-list');
        $query = $this->filterList(DB::table('ph_patients')->where('pharmacy_id', $p->id), $filters, $p, []);

        return $this->pageList($query, $filters, fn ($r) => $this->ref($r) + ['name' => $this->decrypt($r->encrypted_profile)['name']]);
    }

    private function normalizePhone(string $value): string
    {
        $phone = preg_replace('/[\s().-]+/u', '', trim($value));
        if (! is_string($phone)) {
            $this->invalid('phone', 'Số điện thoại không hợp lệ.');
        }
        if (str_starts_with($phone, '0084')) {
            $phone = '+84'.substr($phone, 4);
        } elseif (str_starts_with($phone, '84')) {
            $phone = '+'.$phone;
        } elseif (str_starts_with($phone, '0')) {
            $phone = '+84'.substr($phone, 1);
        }
        // Mobile and fixed-line numbers are accepted; partial/prefix searches are refused.
        if (! preg_match('/^\+84[1-9]\d{8,9}$/D', $phone)) {
            $this->invalid('phone', 'Nhập đầy đủ số điện thoại Việt Nam bắt đầu bằng 0 hoặc +84.');
        }

        return $phone;
    }

    private function phoneTokens(object $pharmacy, string $phone): array
    {
        $keys = array_values(array_unique(array_merge([(string) config('app.key')], config('app.previous_keys', []))));
        $chain = DB::table('ph_chains')->where('id', $pharmacy->chain_id)->value('public_id');

        return array_map(function (string $key) use ($chain, $phone): string {
            $binary = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
            abort_unless(is_string($binary) && strlen($binary) >= 16, 503, 'Chưa có khóa hợp lệ cho tra cứu bảo mật.');
            $lookupKey = hash_hmac('sha256', 'aio.pharmacy.patient.phone.lookup.v1', $binary, true);

            return hash_hmac('sha256', $chain."\0".$phone, $lookupKey);
        }, $keys);
    }
}
