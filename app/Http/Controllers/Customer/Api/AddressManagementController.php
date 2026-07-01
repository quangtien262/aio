<?php

namespace App\Http\Controllers\Customer\Api;

use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressManagementController
{
    public function store(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        $validated = $this->validatePayload($request);

        $address = DB::transaction(function () use ($customer, $validated): CustomerAddress {
            if (($validated['is_default'] ?? false) === true || $customer->addresses()->doesntExist()) {
                $customer->addresses()->update(['is_default' => false]);
                $validated['is_default'] = true;
            }

            return $customer->addresses()->create($validated);
        });

        return response()->json([
            'message' => 'Đã thêm địa chỉ nhận hàng.',
            'data' => $this->serializeAddress($address),
        ], 201);
    }

    public function update(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);
        $validated = $this->validatePayload($request);

        $address = DB::transaction(function () use ($request, $address, $validated): CustomerAddress {
            if (($validated['is_default'] ?? false) === true) {
                $request->user('customer')->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->forceFill($validated)->save();

            return $address->refresh();
        });

        return response()->json([
            'message' => 'Đã cập nhật địa chỉ nhận hàng.',
            'data' => $this->serializeAddress($address),
        ]);
    }

    public function markDefault(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);

        DB::transaction(function () use ($request, $address): void {
            $request->user('customer')->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            $address->forceFill(['is_default' => true])->save();
        });

        return response()->json(['message' => 'Đã đặt làm địa chỉ mặc định.']);
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);
        $wasDefault = $address->is_default;

        $address->delete();

        if ($wasDefault) {
            $request->user('customer')->addresses()->oldest('id')->first()?->forceFill(['is_default' => true])->save();
        }

        return response()->json(null, 204);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'receiver_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'province' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'ward' => ['nullable', 'string', 'max:120'],
            'address_line' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeAddress(Request $request, CustomerAddress $address): void
    {
        abort_unless($address->customer_id === $request->user('customer')?->id, 404);
    }

    private function serializeAddress(CustomerAddress $address): array
    {
        return [
            'id' => $address->id,
            'receiver_name' => $address->receiver_name,
            'phone' => $address->phone,
            'email' => $address->email,
            'province' => $address->province,
            'district' => $address->district,
            'ward' => $address->ward,
            'address_line' => $address->address_line,
            'note' => $address->note,
            'is_default' => $address->is_default,
        ];
    }
}
