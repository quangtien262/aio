<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax\Minvoice;

use App\Http\Controllers\Controller;
use App\Models\AcctProviderConnection;
use App\Support\AccountingTax\Providers\Exceptions\ProviderRequestException;
use App\Support\AccountingTax\Providers\Exceptions\ProviderSafetyException;
use App\Support\AccountingTax\Providers\ProviderConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProviderConnectionController extends Controller
{
    public function __construct(
        private readonly ProviderConnectionService $connections,
        private readonly ProviderApiSerializer $serializer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:acct_organizations,id'],
            'channel' => ['nullable', Rule::in(['outbound', 'inbound'])],
        ]);
        $query = AcctProviderConnection::query()
            ->where('organization_id', $validated['organization_id'])
            ->orderBy('channel')
            ->orderBy('name');

        if (isset($validated['channel'])) {
            $query->where('channel', $validated['channel']);
        }

        return response()->json([
            'data' => $query->get()->map(fn ($connection): array => $this->serializer->connection($connection))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $connection = $this->connections->save($this->validatedPayload($request), null, $request->user('admin')?->id);

        return response()->json(['data' => $this->serializer->connection($connection)], 201);
    }

    public function update(Request $request, AcctProviderConnection $connection): JsonResponse
    {
        $payload = $this->validatedPayload($request, true);

        if (isset($payload['organization_id']) && (int) $payload['organization_id'] !== $connection->organization_id) {
            throw ValidationException::withMessages(['organization_id' => ['Không thể chuyển kết nối sang pháp nhân khác.']]);
        }

        $connection = $this->connections->save($payload, $connection, $request->user('admin')?->id);

        return response()->json(['data' => $this->serializer->connection($connection)]);
    }

    public function test(AcctProviderConnection $connection): JsonResponse
    {
        try {
            $result = $this->connections->test($connection);
        } catch (ProviderRequestException|ProviderSafetyException $exception) {
            throw ValidationException::withMessages(['connection' => [$exception->getMessage()]]);
        }

        return response()->json([
            'data' => [
                'connection' => $this->serializer->connection($connection->fresh()),
                'result' => $result,
            ],
        ]);
    }

    public function allowProduction(Request $request, AcctProviderConnection $connection): JsonResponse
    {
        $validated = $request->validate([
            'confirmation' => ['required', 'string', 'max:50'],
            'contract_version' => ['required', 'string', 'max:100'],
        ]);
        $connection = $this->connections->allowProduction(
            $connection,
            $validated['confirmation'],
            $validated['contract_version'],
        );

        return response()->json(['data' => $this->serializer->connection($connection)]);
    }

    public function killSwitch(Request $request, AcctProviderConnection $connection): JsonResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $connection = $this->connections->setKillSwitch($connection, (bool) $validated['enabled']);

        return response()->json(['data' => $this->serializer->connection($connection)]);
    }

    private function validatedPayload(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'organization_id' => [$updating ? 'sometimes' : 'required', 'integer', 'exists:acct_organizations,id'],
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'channel' => [$updating ? 'sometimes' : 'required', Rule::in(['outbound', 'inbound'])],
            'environment' => [$updating ? 'sometimes' : 'required', Rule::in(['sandbox', 'production'])],
            'base_url' => [$updating ? 'sometimes' : 'required', 'url:https', 'max:2048'],
            'credentials' => [$updating ? 'sometimes' : 'required', 'array'],
            'credentials.username' => ['nullable', 'string', 'max:255'],
            'credentials.password' => ['nullable', 'string', 'max:2000'],
            'credentials.ma_dvcs' => ['nullable', 'string', 'max:100'],
            'credentials.tax_code' => ['nullable', 'string', 'max:32'],
            'credentials.api_token' => ['nullable', 'string', 'max:4000'],
            'settings' => ['sometimes', 'array'],
            'settings.authorization_prefix' => ['nullable', Rule::in(['Bear', 'Bearer'])],
            'settings.login_path' => ['nullable', 'string', 'max:255'],
            'settings.series_path' => ['nullable', 'string', 'max:255'],
            'settings.invoice_path' => ['nullable', 'string', 'max:255'],
            'settings.signing_mode' => ['nullable', Rule::in(['draft_then_sign', 'save_sign'])],
            'settings.invoice_type' => ['nullable', 'integer', 'min:1', 'max:20'],
            'settings.msmi_prefix' => ['nullable', Rule::in(['/erp/qlhd-api', '/api/qlhd-api'])],
            'settings.default_series' => ['nullable', 'string', 'max:80'],
            'settings.scheduled_sync_enabled' => ['nullable', 'boolean'],
            'settings.sync_lookback_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);
    }
}
