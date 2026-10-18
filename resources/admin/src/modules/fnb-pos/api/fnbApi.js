import { adminApi } from '../../../shared/config/routes';
import { prepareCommand, finishCommand } from '../state/fnbCommandJournal';

const FNB_BASE = adminApi('fnb');

const normalizeSegment = (value) => encodeURIComponent(String(value ?? ''));

export const createIdempotencyKey = (scope = 'fnb') => {
    const uuid = globalThis.crypto?.randomUUID?.();

    return uuid ? `${scope}:${uuid}` : `${scope}:${Date.now()}:${Math.random().toString(16).slice(2)}`;
};

export const appendQuery = (path, params = {}) => {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            search.set(key, String(value));
        }
    });

    const query = search.toString();

    return query ? `${path}${path.includes('?') ? '&' : '?'}${query}` : path;
};

const unwrap = (payload) => {
    if (payload === null || payload === undefined) {
        return { data: null, meta: {} };
    }

    if (Object.prototype.hasOwnProperty.call(payload, 'data')) {
        return { data: payload.data, meta: payload.meta ?? {} };
    }

    if (Object.prototype.hasOwnProperty.call(payload, 'resource')) {
        return {
            data: payload.resource,
            meta: {
                ...(payload.meta ?? {}),
                replayed: payload.replayed ?? false,
                events: payload.events ?? [],
            },
        };
    }

    return { data: payload, meta: {} };
};

export function createFnbApi(callAdminApi, scope = {}) {
    const scopeHeaders = () => ({
        ...(scope.outletId ? { 'X-FNB-Outlet': String(scope.outletId) } : {}),
        ...(scope.terminalId ? { 'X-FNB-Terminal': String(scope.terminalId) } : {}),
    });

    const request = async (path, options = {}) => {
        const response = await callAdminApi(`${FNB_BASE}/${String(path).replace(/^\/+/, '')}`, {
            ...options,
            headers: {
                ...scopeHeaders(),
                ...(options.headers ?? {}),
            },
        });

        return unwrap(response);
    };

    const read = async (path, params = {}) => request(appendQuery(path, params));

    const command = async (path, payload = {}, options = {}) => {
        const proposedKey = options.idempotencyKey ?? createIdempotencyKey(options.scope ?? path);
        const method = options.method ?? 'POST';
        // Reauthentication secrets and short-lived security tokens never enter the journal.
        const journaled = scope.identity && !(payload instanceof FormData) && !/^(reauth\/|approvals|.*staff-candidates\/)/.test(path);
        const journalOutlet = scope.outletId ?? (path.startsWith('chain/integrations') ? payload?.outlet_id : undefined);
        const prepared = journaled ? await prepareCommand({ identity: scope.identity, outletId: journalOutlet, terminalId: scope.terminalId, path, method, payload, idempotencyKey: proposedKey },
            (pending) => createFnbApi(callAdminApi, { identity: scope.identity, outletId: pending.outletId, terminalId: pending.terminalId }).read('command-status', { key: pending.idempotencyKey })) : { entry: null, payload };
        const { entry } = prepared;
        const idempotencyKey = entry?.idempotencyKey ?? proposedKey;
        const headers = {
            'Idempotency-Key': idempotencyKey,
            ...(options.reauthProof ? { 'X-FNB-Reauth-Proof': options.reauthProof } : {}),
            ...(options.approvalToken ? { 'X-FNB-Approval-Token': options.approvalToken } : {}),
            ...(options.headers ?? {}),
        };
        const body = prepared.payload instanceof FormData ? prepared.payload : JSON.stringify(prepared.payload ?? {});

        try {
            const result = await request(path, { method, body, headers });
            await finishCommand(entry);
            return result;
        } catch (error) {
            await finishCommand(entry, error);
            throw error;
        }
    };

    const download = (path) => callAdminApi(`${FNB_BASE}/${String(path).replace(/^\/+/, '')}`, {
        credentials: 'same-origin',
        responseType: 'blob',
        headers: scopeHeaders(),
    });

    const outletPath = (suffix = '') => {
        if (!scope.outletId) {
            throw new Error('Vui lòng chọn điểm bán trước khi tiếp tục.');
        }

        return `outlets/${normalizeSegment(scope.outletId)}${suffix ? `/${suffix}` : ''}`;
    };

    return {
        read,
        request,
        command,
        outletPath,
        bootstrap: () => read('bootstrap'),
        onboard: (payload, options) => command('onboarding/presets/cafe', payload, options),
        dashboard: (filters) => read(outletPath('dashboard'), filters),
        pos: () => read(outletPath('pos'), { terminal_id: scope.terminalId }),
        catalog: (filters) => read(outletPath('menu'), filters),
        recipes: (filters) => read(outletPath('recipes'), filters),
        kitchen: (filters) => read(outletPath('kitchen/tickets'), filters),
        shifts: (filters) => read(outletPath('shifts'), filters),
        currentBusinessDay: () => read(outletPath('business-days/current')),
        report: (filters) => read(outletPath('reports/summary'), filters),
        reportExports: (filters) => read(outletPath('reports/exports'), filters),
        downloadExport: (id) => download(`reports/exports/${normalizeSegment(id)}/download`),
        settings: () => read(outletPath('settings')),
        staff: () => read(outletPath('staff')),
        chainReport: (filters) => read('chain/reports', filters),
        chain: () => read('chain/bootstrap'),
        integrationOperations: () => read('chain/integrations'),
        commandStatus: (entry) => createFnbApi(callAdminApi, { identity: scope.identity, outletId: entry.outletId, terminalId: entry.terminalId }).read('command-status', { key: entry.idempotencyKey }),
        forScope: (overrides) => createFnbApi(callAdminApi, { ...scope, ...overrides }),
    };
}
