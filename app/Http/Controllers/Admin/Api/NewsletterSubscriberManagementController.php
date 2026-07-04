<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NewsletterSubscriberManagementController
{
    public function update(Request $request, int $subscriber): JsonResponse
    {
        $record = NewsletterSubscriber::query()->findOrFail($subscriber);
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('newsletter_subscribers', 'email')->ignore($record->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $record->update([
            'email' => mb_strtolower($validated['email']),
            'name' => $validated['name'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ]);

        return response()->json([
            'message' => 'Da cap nhat email nhan tin.',
        ]);
    }

    public function destroy(int $subscriber): JsonResponse
    {
        $record = NewsletterSubscriber::query()->findOrFail($subscriber);
        $record->delete();

        return response()->json([
            'message' => 'Da xoa email nhan tin.',
        ]);
    }
}
