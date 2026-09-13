<?php

namespace Modules\FnbPos\Domain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

abstract class FnbDomainException extends RuntimeException
{
    /** @param array<string,mixed> $details */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|bool
    {
        if (! $request->is('admin/api/fnb', 'admin/api/fnb/*')) {
            return false;
        }

        return response()->json([
            'code' => match ($this->status) {
                404 => 'FNB_RESOURCE_NOT_FOUND',
                409 => 'FNB_STATE_CONFLICT',
                default => 'FNB_VALIDATION_FAILED',
            },
            'message' => $this->getMessage(), 'details' => $this->details,
            'request_id' => $request->header('X-Request-ID'),
        ], $this->status);
    }
}
